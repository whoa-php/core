<?php

/**
 * Copyright 2015-2019 info@neomerx.com
 * Modification Copyright 2021-2022 info@whoaphp.com
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

declare(strict_types=1);

namespace Whoa\Core\Routing;

use Closure;
use Whoa\Common\Reflection\CheckCallableTrait;
use Whoa\Contracts\Routing\GroupInterface;
use Whoa\Contracts\Routing\RouteInterface;
use Whoa\Core\Routing\Traits\CallableTrait;
use Whoa\Core\Routing\Traits\HasConfiguratorsTrait;
use Whoa\Core\Routing\Traits\HasMiddlewareTrait;
use Whoa\Core\Routing\Traits\HasRequestFactoryTrait;
use Whoa\Core\Routing\Traits\UriTrait;
use ReflectionException;

use function array_key_exists;
use function array_merge;

/**
 * @package Whoa\Core
 */
abstract class BaseGroup implements GroupInterface
{
    use CallableTrait;
    use CheckCallableTrait;
    use HasRequestFactoryTrait;
    use UriTrait;

    use HasMiddlewareTrait {
        addMiddleware as private addMiddlewareImpl;
    }

    use HasConfiguratorsTrait {
        addConfigurators as private addConfiguratorsImpl;
    }

    /** Default value if routes should use request factory from its group */
    public const USE_FACTORY_FROM_GROUP_DEFAULT = true;

    /**
     * @var null|GroupInterface
     */
    private ?GroupInterface $parent = null;

    /**
     * @var string
     */
    private string $uriPrefix = '';

    /**
     * @var string|null
     */
    private ?string $name = null;

    /**
     * @var array
     */
    private array $items = [];

    /**
     * @var bool
     */
    private bool $trailSlashes = false;

    /**
     * @return self
     */
    abstract protected function createGroup(): self;

    /**
     * @param GroupInterface $parent
     * @return $this
     */
    public function setParentGroup(GroupInterface $parent): BaseGroup
    {
        $this->parent = $parent;

        return $this;
    }

    /**
     * @param string $uriPrefix
     * @return self
     */
    public function setUriPrefix(string $uriPrefix): self
    {
        $this->uriPrefix = $uriPrefix;

        return $this;
    }

    /**
     * @param string $name
     * @return self
     */
    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return self
     */
    public function clearName(): self
    {
        $this->name = null;

        return $this;
    }

    /**
     * @param bool $trailSlashes
     * @return self
     */
    public function setHasTrailSlash(bool $trailSlashes): self
    {
        $this->trailSlashes = $trailSlashes;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function parentGroup(): ?GroupInterface
    {
        return $this->parent;
    }

    /**
     * @inheritdoc
     */
    public function getUriPrefix(): string
    {
        $parentPrefix = $this->getParentUriPrefix();
        if ($parentPrefix !== null) {
            return $this->normalizeUri($this->concatUri($parentPrefix, $this->uriPrefix), $this->hasTrailSlash());
        }

        return $this->normalizeUri($this->uriPrefix, $this->hasTrailSlash());
    }

    /**
     * @inheritdoc
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * @inheritdoc
     */
    public function getMiddleware(): array
    {
        $result = array_merge($this->getParentMiddleware(), $this->middleware);

        return $result;
    }

    /**
     * @inheritdoc
     */
    public function addMiddleware(array $middleware): GroupInterface
    {
        return $this->addMiddlewareImpl($middleware);
    }

    /**
     * @inheritdoc
     */
    public function getContainerConfigurators(): array
    {
        return array_merge($this->getParentConfigurators(), $this->configurators);
    }

    /**
     * @inheritdoc
     * @throws ReflectionException
     */
    public function addContainerConfigurators(array $configurators): GroupInterface
    {
        return $this->addConfiguratorsImpl($configurators);
    }

    /**
     * @inheritdoc
     */
    public function getRequestFactory(): ?callable
    {
        if ($this->isRequestFactorySet() === true) {
            return $this->requestFactory;
        }

        $parent = $this->parentGroup();
        return $parent === null ? $this->getDefaultRequestFactory() : $parent->getRequestFactory();
    }

    /**
     * @inheritdoc
     */
    public function getRoutes(): iterable
    {
        foreach ($this->items as $routeOrGroup) {
            if ($routeOrGroup instanceof RouteInterface) {
                /** @var RouteInterface $routeOrGroup */
                yield $routeOrGroup;
                continue;
            }

            /** @var GroupInterface $routeOrGroup */
            foreach ($routeOrGroup->getRoutes() as $route) {
                yield $route;
            }
        }
    }

    /**
     * @inheritdoc
     * @throws ReflectionException
     */
    public function group(string $prefix, Closure $closure, array $parameters = []): GroupInterface
    {
        [$middleware, $configurators, $factoryWasGiven, $requestFactory, $name] =
            $this->normalizeGroupParameters($parameters);

        $group = $this->createGroup()
            ->setUriPrefix($prefix)
            ->setMiddleware($middleware)
            ->setConfigurators($configurators);
        $name === null ? $group->clearName() : $group->setName($name);

        $factoryWasGiven === false ?: $group->setRequestFactory($requestFactory);

        return $this->addGroup($closure, $group);
    }

    /**
     * @inheritdoc
     */
    public function addGroup(Closure $closure, GroupInterface $group): GroupInterface
    {
        $closure($group);

        $this->items[] = $group;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function addRoute(RouteInterface $route): GroupInterface
    {
        $this->items[] = $route;

        return $this;
    }

    /**
     * @inheritdoc
     * @throws ReflectionException
     */
    public function method(string $method, string $uriPath, callable $handler, array $parameters = []): GroupInterface
    {
        [$middleware, $configurators, $requestFactory, $useGroupFactory, $name] =
            $this->normalizeRouteParameters($parameters);

        $uriPath = $this->normalizeUri($uriPath, $this->hasTrailSlash());

        $route = $this->createRoute($this, $method, $uriPath, $handler)
            ->setUseGroupRequestFactory($useGroupFactory)
            ->setRequestFactory($requestFactory)
            ->setConfigurators($configurators)
            ->setMiddleware($middleware)
            ->setName($name);

        return $this->addRoute($route);
    }

    /**
     * @inheritdoc
     * @throws ReflectionException
     */
    public function get(string $uriPath, callable $handler, array $parameters = []): GroupInterface
    {
        return $this->method('GET', $uriPath, $handler, $parameters);
    }

    /**
     * @inheritdoc
     * @throws ReflectionException
     */
    public function post(string $uriPath, callable $handler, array $parameters = []): GroupInterface
    {
        return $this->method('POST', $uriPath, $handler, $parameters);
    }

    /**
     * @inheritdoc
     * @throws ReflectionException
     */
    public function put(string $uriPath, callable $handler, array $parameters = []): GroupInterface
    {
        return $this->method('PUT', $uriPath, $handler, $parameters);
    }

    /**
     * @inheritdoc
     * @throws ReflectionException
     */
    public function patch(string $uriPath, callable $handler, array $parameters = []): GroupInterface
    {
        return $this->method('PATCH', $uriPath, $handler, $parameters);
    }

    /**
     * @inheritdoc
     * @throws ReflectionException
     */
    public function delete(string $uriPath, callable $handler, array $parameters = []): GroupInterface
    {
        return $this->method('DELETE', $uriPath, $handler, $parameters);
    }

    /**
     * @inheritdoc
     */
    public function hasTrailSlash(): bool
    {
        return $this->trailSlashes;
    }

    /**
     * @param GroupInterface $group
     * @param string $method
     * @param string $uriPath
     * @param callable $handler
     * @return Route
     * @throws ReflectionException
     */
    protected function createRoute(GroupInterface $group, string $method, string $uriPath, callable $handler): Route
    {
        return (new Route($group, $method, $uriPath, $handler));
    }

    /**
     * @param array $parameters
     * @return array
     */
    protected function normalizeRouteParameters(array $parameters): array
    {
        $factoryWasGiven = array_key_exists(RouteInterface::PARAM_REQUEST_FACTORY, $parameters);
        $useGroupFactory =
            $parameters[RouteInterface::PARAM_FACTORY_FROM_GROUP] ?? self::USE_FACTORY_FROM_GROUP_DEFAULT;

        return [
            $parameters[RouteInterface::PARAM_MIDDLEWARE_LIST] ?? [],
            $parameters[RouteInterface::PARAM_CONTAINER_CONFIGURATORS] ?? [],
            $parameters[RouteInterface::PARAM_REQUEST_FACTORY] ?? null,
            $factoryWasGiven === true ? false : $useGroupFactory,
            $parameters[RouteInterface::PARAM_NAME] ?? null,
        ];
    }

    /**
     * @param array $parameters
     * @return array
     */
    protected function normalizeGroupParameters(array $parameters): array
    {
        $factoryWasGiven = array_key_exists(GroupInterface::PARAM_REQUEST_FACTORY, $parameters);

        return [
            $parameters[GroupInterface::PARAM_MIDDLEWARE_LIST] ?? [],
            $parameters[GroupInterface::PARAM_CONTAINER_CONFIGURATORS] ?? [],
            $factoryWasGiven,
            $parameters[GroupInterface::PARAM_REQUEST_FACTORY] ?? null,
            $parameters[GroupInterface::PARAM_NAME_PREFIX] ?? null,
        ];
    }

    /**
     * @return null|string
     */
    private function getParentUriPrefix(): ?string
    {
        $parent = $this->parentGroup();
        return $parent === null ? null : $parent->getUriPrefix();
    }

    /**
     * @return array
     */
    private function getParentMiddleware(): array
    {
        $parent = $this->parentGroup();
        return $parent === null ? [] : $parent->getMiddleware();
    }

    /**
     * @return array
     */
    private function getParentConfigurators(): array
    {
        $parent = $this->parentGroup();
        return $parent === null ? [] : $parent->getContainerConfigurators();
    }
}
