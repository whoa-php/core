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

use Whoa\Common\Reflection\CheckCallableTrait;
use Whoa\Contracts\Routing\GroupInterface;
use Whoa\Contracts\Routing\RouteInterface;
use Whoa\Core\Routing\Traits\CallableTrait;
use Whoa\Core\Routing\Traits\HasConfiguratorsTrait;
use Whoa\Core\Routing\Traits\HasMiddlewareTrait;
use Whoa\Core\Routing\Traits\HasRequestFactoryTrait;
use Whoa\Core\Routing\Traits\UriTrait;
use LogicException;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionException;

use function array_merge;

/**
 * @package Whoa\Core
 */
class Route implements RouteInterface
{
    use CallableTrait;
    use UriTrait;
    use HasConfiguratorsTrait;
    use HasMiddlewareTrait;
    use HasRequestFactoryTrait;
    use CheckCallableTrait {
        CheckCallableTrait::checkPublicStaticCallable insteadof HasMiddlewareTrait;
        CheckCallableTrait::checkPublicStaticCallable insteadof HasConfiguratorsTrait;
        CheckCallableTrait::checkPublicStaticCallable insteadof HasRequestFactoryTrait;
    }

    /**
     * @var GroupInterface
     */
    private GroupInterface $group;

    /**
     * @var string
     */
    private string $method;

    /**
     * @var string
     */
    private string $uriPath;

    /**
     * @var callable
     */
    private $handler;

    /**
     * @var bool
     */
    private bool $isGroupRequestFactory = true;

    /**
     * @var string|null
     */
    private ?string $name;

    /**
     * @param GroupInterface $group
     * @param string $method
     * @param string $uriPath
     * @param callable $handler
     * @throws ReflectionException
     */
    public function __construct(GroupInterface $group, string $method, string $uriPath, callable $handler)
    {
        $this->group = $group;
        $this->method = $method;
        $this->uriPath = $uriPath;

        $this->setHandler($handler);
    }

    /**
     * @param string|null $name
     * @return self
     */
    public function setName(string $name = null): self
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getGroup(): GroupInterface
    {
        return $this->group;
    }

    /**
     * @inheritdoc
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * @inheritdoc
     */
    public function getUriPath(): string
    {
        $uriPath = $this->concatUri($this->getGroup()->getUriPrefix(), $this->uriPath);

        return $this->normalizeUri($uriPath, $this->getGroup()->hasTrailSlash());
    }

    /**
     * @inheritdoc
     */
    public function getMiddleware(): array
    {
        return array_merge($this->getGroup()->getMiddleware(), $this->middleware);
    }

    /**
     * @inheritdoc
     */
    public function getHandler(): callable
    {
        return $this->handler;
    }

    /**
     * @inheritdoc
     */
    public function getContainerConfigurators(): array
    {
        return array_merge($this->getGroup()->getContainerConfigurators(), $this->configurators);
    }

    /**
     * @inheritdoc
     */
    public function getRequestFactory(): ?callable
    {
        if ($this->isUseGroupRequestFactory() === true) {
            return $this->getGroup()->getRequestFactory();
        }

        return $this->isRequestFactorySet() === true ? $this->requestFactory : $this->getDefaultRequestFactory();
    }

    /**
     * @return bool
     */
    public function isUseGroupRequestFactory(): bool
    {
        return $this->isGroupRequestFactory;
    }

    /**
     * @param bool $isGroupFactory
     * @return self
     */
    public function setUseGroupRequestFactory(bool $isGroupFactory): self
    {
        $this->isGroupRequestFactory = $isGroupFactory;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getName(): ?string
    {
        return $this->name !== null ? (string)$this->getGroup()->getName() . $this->name : null;
    }

    /**
     * @param callable $handler
     * @return self
     * @throws ReflectionException
     */
    protected function setHandler(callable $handler): self
    {
        $isValidHandler = $this->checkPublicStaticCallable($handler, [
            'array',
            ContainerInterface::class,
            ServerRequestInterface::class,
        ], ResponseInterface::class);
        if ($isValidHandler === false) {
            // Handler method should have signature
            // `public static methodName(array, ContainerInterface, ServerRequestInterface): ResponseInterface`'
            throw new LogicException($this->getCallableToCacheMessage());
        }

        $this->handler = $handler;

        return $this;
    }
}
