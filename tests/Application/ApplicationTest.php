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

namespace Whoa\Tests\Core\Application;

use Closure;
use Exception;
use FastRoute\DataGenerator\GroupCountBased as GroupCountBasedGenerator;
use Laminas\Diactoros\Response\TextResponse;
use Laminas\Diactoros\ServerRequestFactory;
use Laminas\HttpHandlerRunner\Emitter\EmitterInterface;
use Psr\Container\ContainerExceptionInterface;
use Whoa\Container\Container;
use Whoa\Contracts\Container\ContainerInterface as WhoaContainerInterface;
use Whoa\Contracts\Core\SapiInterface;
use Whoa\Contracts\Exceptions\ThrowableHandlerInterface;
use Whoa\Contracts\Http\ThrowableResponseInterface;
use Whoa\Contracts\Routing\GroupInterface;
use Whoa\Contracts\Routing\RouterInterface;
use Whoa\Core\Application\Application;
use Whoa\Core\Application\Sapi;
use Whoa\Core\Application\ThrowableResponseTrait;
use Whoa\Core\Routing\Dispatcher\GroupCountBased as GroupCountBasedDispatcher;
use Whoa\Core\Routing\Group;
use Whoa\Core\Routing\Router;
use Whoa\Tests\Core\Application\Data\CoreData;
use Whoa\Tests\Core\TestCase;
use LogicException;
use Mockery;
use Mockery\Mock;
use Psr\Container\ContainerInterface as PsrContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use ReflectionException;
use ReflectionMethod;
use Throwable;

/**
 * @package Whoa\Tests\Core
 */
class ApplicationTest extends TestCase
{
    /** Filed name for storing response in SAPI mock */
    public const FIELD_RESPONSE = 'response';

    /**
     * @var bool
     */
    private static bool $isRouteConfiguratorCalled;

    /**
     * @var bool
     */
    private static bool $isGlobalConfiguratorCalled;

    /**
     * @var bool
     */
    private static bool $isGlobalMiddleware1Called;

    /**
     * @var bool
     */
    private static bool $isGlobalMiddleware2Called;

    /**
     * @var bool
     */
    private static bool $isRouteMiddlewareCalled;

    /**
     * @var bool
     */
    private static bool $isRequestFactoryCalled;

    /**
     * @var bool
     */
    private static bool $isHomeIndexCalled;

    /**
     * Set up tests.
     */
    protected function setUp(): void
    {
        parent::setUp();

        self::$isGlobalConfiguratorCalled = false;
        self::$isRouteConfiguratorCalled = false;
        self::$isGlobalMiddleware1Called = false;
        self::$isGlobalMiddleware2Called = false;
        self::$isRouteMiddlewareCalled = false;
        self::$isRequestFactoryCalled = false;
        self::$isHomeIndexCalled = false;
    }

    /**
     * Test page.
     * @throws Exception
     */
    public function testHomeIndex(): void
    {
        /** @var Application $app */
        /** @var SapiInterface $sapi */
        [$app, $sapi] = $this->createApp('GET', '/', $this->getRoutesData());
        $app->run();

        /** @var ResponseInterface $response */
        $response = $sapi->{self::FIELD_RESPONSE};
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('home index', $this->getText($response->getBody()));

        $this->assertTrue(self::$isGlobalConfiguratorCalled);
        $this->assertFalse(self::$isRouteConfiguratorCalled);
        $this->assertTrue(self::$isGlobalMiddleware1Called);
        $this->assertFalse(self::$isRouteMiddlewareCalled);
        $this->assertFalse(self::$isRequestFactoryCalled);
        $this->assertTrue(self::$isHomeIndexCalled);
    }

    /**
     * Test page.
     * @throws Exception
     */
    public function testPostsIndex(): void
    {
        /** @var Application $app */
        /** @var SapiInterface $sapi */
        [$app, $sapi] = $this->createApp('POST', '/posts', $this->getRoutesData());
        $app->run();

        /** @var ResponseInterface $response */
        $response = $sapi->{self::FIELD_RESPONSE};
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('post create', $this->getText($response->getBody()));

        $this->assertTrue(self::$isRouteConfiguratorCalled);
        $this->assertTrue(self::$isGlobalMiddleware1Called);
        $this->assertTrue(self::$isRouteMiddlewareCalled);
        $this->assertTrue(self::$isRequestFactoryCalled);
    }

    /**
     * Test page.
     * @throws ReflectionException
     * @throws ContainerExceptionInterface
     */
    public function test404(): void
    {
        /** @var Application $app */
        /** @var SapiInterface $sapi */
        [$app, $sapi] = $this->createApp('GET', '/posts/XYZ', $this->getRoutesData());
        $app->run();

        /** @var ResponseInterface $response */
        $response = $sapi->{self::FIELD_RESPONSE};
        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals(0, $response->getBody()->getSize());

        $this->assertFalse(self::$isRouteConfiguratorCalled);
        $this->assertTrue(self::$isGlobalMiddleware1Called);
        $this->assertFalse(self::$isRouteMiddlewareCalled);
        $this->assertFalse(self::$isRequestFactoryCalled);
    }

    /**
     * Test page.
     * @throws ContainerExceptionInterface
     * @throws ReflectionException
     */
    public function test405(): void
    {
        /** @var Application $app */
        /** @var SapiInterface $sapi */
        [$app, $sapi] = $this->createApp('INFO', '/', $this->getRoutesData());
        $app->run();

        /** @var ResponseInterface $response */
        $response = $sapi->{self::FIELD_RESPONSE};
        $this->assertEquals(405, $response->getStatusCode());
        $this->assertEquals(0, $response->getBody()->getSize());
        $this->assertEquals(['GET'], $response->getHeader('Accept'));

        $this->assertFalse(self::$isRouteConfiguratorCalled);
        $this->assertTrue(self::$isGlobalMiddleware1Called);
        $this->assertFalse(self::$isRouteMiddlewareCalled);
        $this->assertFalse(self::$isRequestFactoryCalled);
    }

    /**
     * Test Application not configured.
     * @throws ContainerExceptionInterface
     */
    public function testSapiNotSet(): void
    {
        $this->expectException(LogicException::class);

        $app = Mockery::mock(Application::class)->makePartial()->shouldAllowMockingProtectedMethods();

        /** @var Application $app */

        $app->run();
    }

    /**
     * Test user can ask not to create request.
     * @throws ContainerExceptionInterface
     * @throws ReflectionException
     */
    public function testNoRequestInController(): void
    {
        /** @var Application $app */
        /** @var SapiInterface $sapi */
        [$app, $sapi] = $this->createApp('GET', '/', $this->getRoutesDataForNoRequest(), []);
        $app->run();

        /** @var ResponseInterface $response */
        $response = $sapi->{self::FIELD_RESPONSE};
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('home index', $this->getText($response->getBody()));

        $this->assertTrue(self::$isRouteConfiguratorCalled);
        $this->assertFalse(self::$isGlobalMiddleware1Called);
        $this->assertFalse(self::$isRouteMiddlewareCalled);
        $this->assertFalse(self::$isRequestFactoryCalled);
    }

    /**
     * Test error handling.
     * @throws ContainerExceptionInterface
     */
    public function testErrorHandling(): void
    {
        $execute = function (string $uri): ResponseInterface {
            $container = new Container();
            /** @var Application $app */
            [$app, $sapi] = $this->createApp('GET', $uri, $this->getRoutesDataForErrorsTesting(), [], $container);
            $app->run();
            return $sapi->{self::FIELD_RESPONSE};
        };

        /** @var ThrowableResponseInterface $response */
        $response = $execute('/throwable-exception-without-handler');
        $this->assertInstanceOf(ThrowableResponseInterface::class, $response);
        $this->assertInstanceOf(Throwable::class, $response->getThrowable());
        $this->assertEquals(500, $response->getStatusCode());
        $this->assertStringStartsWith(
            'Exception: The handler emulates an error in a Controller.',
            $this->getText($response->getBody())
        );

        $response = $execute('/throwable-error-without-handler');
        $this->assertInstanceOf(ThrowableResponseInterface::class, $response);
        $this->assertEquals(500, $response->getStatusCode());
        $this->assertStringStartsWith(
            'ParseError: syntax error',
            $this->getText($response->getBody())
        );

        $response = $execute('/throwable-exception-with-handler');
        $this->assertInstanceOf(ThrowableResponseInterface::class, $response);
        $this->assertEquals(500, $response->getStatusCode());
        $this->assertStringStartsWith(
            'Handled by error handler. Exception: The handler emulates an error in a Controller.',
            $this->getText($response->getBody())
        );

        $response = $execute('/throwable-error-with-handler');
        $this->assertInstanceOf(ThrowableResponseInterface::class, $response);
        $this->assertEquals(500, $response->getStatusCode());
        $this->assertStringStartsWith(
            'Handled by error handler. ParseError: syntax error',
            $this->getText($response->getBody())
        );
    }

    /**
     * Test page.
     * @throws ContainerExceptionInterface
     * @throws ReflectionException
     */
    public function testHomeIndexFaultyMiddleware(): void
    {
        /** @var Application $app */
        /** @var SapiInterface $sapi */
        $faultyMiddleware = [self::class, 'faultyMiddlewareItem'];
        $container = new Container();
        [$app, $sapi] = $this
            ->createApp('GET', '/', $this->getRoutesData(), [$faultyMiddleware], $container);
        $app->run();

        /** @var ResponseInterface $response */
        $response = $sapi->{self::FIELD_RESPONSE};
        $this->assertEquals(500, $response->getStatusCode());
        $this->assertStringStartsWith(
            'Exception: Oops! We got an error in middleware.',
            (string)$response->getBody()
        );

        $this->assertTrue(self::$isGlobalConfiguratorCalled);
        $this->assertFalse(self::$isHomeIndexCalled);
        $this->assertFalse(self::$isRouteConfiguratorCalled);
        $this->assertFalse(self::$isRouteMiddlewareCalled);
        $this->assertFalse(self::$isRequestFactoryCalled);
    }

    /**
     * Test page.
     * @throws ContainerExceptionInterface
     * @throws ReflectionException
     */
    public function testGetRouter(): void
    {
        /** @var Application $app */
        [$app] = $this->createApp('GET', '/', $this->getRoutesData());
        $app->run();

        $method = new ReflectionMethod(Application::class, 'getRouter');
        $method->setAccessible(true);
        $this->assertTrue($method->invoke($app) instanceof RouterInterface);
    }

    /**
     * @param string $method
     * @param string $uri
     * @param array $routesData
     * @param array|null $globalMiddleware
     * @param WhoaContainerInterface|null $container
     * @return array
     */
    private function createApp(
        string $method,
        string $uri,
        array $routesData,
        array $globalMiddleware = null,
        WhoaContainerInterface $container = null
    ): array {
        if ($container === null) {
            /** @var Mock $container */
            $container = Mockery::mock(WhoaContainerInterface::class);
        }

        $server['REQUEST_URI'] = $uri;
        $server['REQUEST_METHOD'] = $method;

        /** @var EmitterInterface $emitter */
        $emitter = Mockery::mock(EmitterInterface::class);

        $sapi = Mockery::mock(Sapi::class, [$emitter, $server])->makePartial();
        $sapi->shouldReceive('getMethod')->zeroOrMoreTimes()->withAnyArgs()->passthru();
        $sapi->shouldReceive('handleResponse')->once()->withAnyArgs()->andReturnUsing(function ($response) use ($sapi) {
            $sapi->{self::FIELD_RESPONSE} = $response;
        });

        $app = Mockery::mock(Application::class)->makePartial()->shouldAllowMockingProtectedMethods();

        $globalMiddleware = $globalMiddleware ??
            [[self::class, 'globalMiddlewareItem1'], [self::class, 'globalMiddlewareItem2']];
        $coreData = (new CoreData())
            ->setRouterParameters([
                CoreData::KEY_ROUTER_PARAMS__GENERATOR => GroupCountBasedGenerator::class,
                CoreData::KEY_ROUTER_PARAMS__DISPATCHER => GroupCountBasedDispatcher::class,
            ])->setRoutesData($routesData)
            ->setGlobalConfigurators([[self::class, 'createGlobalConfigurator']])
            ->setGlobalMiddleware($globalMiddleware);

        $app->shouldReceive('getCoreData')->once()->withAnyArgs()->andReturn($coreData->get());
        $app->shouldReceive('createContainerInstance')->zeroOrMoreTimes()->withNoArgs()->andReturn($container);

        /** @var Application $app */
        /** @var SapiInterface $sapi */
        $app->setSapi($sapi);

        return [$app, $sapi];
    }

    /**
     * @return array
     * @throws ReflectionException
     */
    private function getRoutesData(): array
    {
        $group = (new Group())
            ->get('/', [self::class, 'homeIndex'])
            ->group('posts', function (GroupInterface $group) {
                $group
                    ->post('', [self::class, 'postsCreate'], [
                        GroupInterface::PARAM_MIDDLEWARE_LIST => [self::class . '::createPostMiddleware'],
                        GroupInterface::PARAM_CONTAINER_CONFIGURATORS => [self::class . '::createPostConfigurator'],
                        GroupInterface::PARAM_REQUEST_FACTORY => self::class . '::createRequest',
                    ]);
            });

        $router = new Router(GroupCountBasedGenerator::class, GroupCountBasedDispatcher::class);
        return $router->getCachedRoutes($group);
    }

    /**
     * @return array
     * @throws ReflectionException
     */
    private function getRoutesDataForNoRequest(): array
    {
        $group = (new Group([
            GroupInterface::PARAM_CONTAINER_CONFIGURATORS => [self::class . '::createPostConfigurator'],
            GroupInterface::PARAM_REQUEST_FACTORY => null,
        ]))->get('/', [self::class, 'homeIndexNoRequest']);

        $router = new Router(GroupCountBasedGenerator::class, GroupCountBasedDispatcher::class);
        return $router->getCachedRoutes($group);
    }

    /**
     * @return array
     * @throws ReflectionException
     */
    private function getRoutesDataForErrorsTesting(): array
    {
        $group = (new Group())
            ->group('', function (GroupInterface $group): void {
                $group->get('throwable-exception-with-handler', self::class . '::handlerThatThrowsException');
                $group->get('throwable-error-with-handler', self::class . '::handlerThatProducesError');
            }, [
                GroupInterface::PARAM_CONTAINER_CONFIGURATORS => [self::class . '::configureErrorHandler'],
            ])
            ->group('', function (GroupInterface $group): void {
                $group->get('throwable-exception-without-handler', self::class . '::handlerThatThrowsException');
                $group->get('throwable-error-without-handler', self::class . '::handlerThatProducesError');
            });

        $router = new Router(GroupCountBasedGenerator::class, GroupCountBasedDispatcher::class);
        return $router->getCachedRoutes($group);
    }

    /**
     * @param SapiInterface $sapi
     * @param PsrContainerInterface $container
     * @return ServerRequestInterface
     */
    public static function createRequest(SapiInterface $sapi, PsrContainerInterface $container): ServerRequestInterface
    {
        assert($sapi || $container);

        // dummy for tests
        self::$isRequestFactoryCalled = true;

        return ServerRequestFactory::fromGlobals();
    }

    /**
     * @param ServerRequestInterface $request
     * @param Closure $next
     * @param PsrContainerInterface $container
     * @return ResponseInterface
     * @throws Exception
     */
    public static function globalMiddlewareItem1(
        ServerRequestInterface $request,
        Closure $next,
        PsrContainerInterface $container
    ): ResponseInterface {
        self::assertFalse(self::$isGlobalMiddleware1Called);
        self::assertFalse(self::$isGlobalMiddleware2Called);

        $container ?: null;

        // dummy for tests
        self::$isGlobalMiddleware1Called = true;

        return $next($request);
    }

    /**
     * @param ServerRequestInterface $request
     * @param Closure $next
     * @return ResponseInterface
     * @throws Exception
     */
    public static function globalMiddlewareItem2(ServerRequestInterface $request, Closure $next): ResponseInterface
    {
        self::assertTrue(self::$isGlobalMiddleware1Called);
        self::assertFalse(self::$isGlobalMiddleware2Called);

        self::$isGlobalMiddleware2Called = true;

        return $next($request);
    }

    /**
     * @param ServerRequestInterface $request
     * @param Closure $next
     * @return ResponseInterface
     * @throws Exception
     */
    public static function faultyMiddlewareItem(ServerRequestInterface $request, Closure $next): ResponseInterface
    {
        assert($request || $next);

        throw new Exception('Oops! We got an error in middleware.');
    }

    /**
     * @param ServerRequestInterface $request
     * @param Closure $next
     * @param PsrContainerInterface $container
     * @return ResponseInterface
     */
    public static function createPostMiddleware(
        ServerRequestInterface $request,
        Closure $next,
        PsrContainerInterface $container
    ): ResponseInterface {
        $container ?: null;

        // dummy for tests
        self::$isRouteMiddlewareCalled = true;

        return $next($request);
    }

    /**
     * Container configurator.
     * @param WhoaContainerInterface $container
     * @return void
     */
    public static function createGlobalConfigurator(WhoaContainerInterface $container): void
    {
        // dummy for tests
        $container ?: null;

        self::$isGlobalConfiguratorCalled = true;
    }

    /**
     * Container configurator.
     * @param WhoaContainerInterface $container
     * @return void
     */
    public static function createPostConfigurator(WhoaContainerInterface $container): void
    {
        // dummy for tests
        $container ?: null;

        self::$isRouteConfiguratorCalled = true;
    }

    /**
     * Container configurator.
     * @param WhoaContainerInterface $container
     * @return void
     */
    public static function configureErrorHandler(WhoaContainerInterface $container): void
    {
        $container[ThrowableHandlerInterface::class] = new class implements ThrowableHandlerInterface {
            /**
             * @inheritdoc
             */
            public function createResponse(
                Throwable $throwable,
                PsrContainerInterface $container
            ): ThrowableResponseInterface {
                return new class ($throwable) extends TextResponse implements ThrowableResponseInterface {
                    use ThrowableResponseTrait;

                    /**
                     * @param Throwable $throwable
                     * @param int $status
                     */
                    public function __construct(Throwable $throwable, int $status = 500)
                    {
                        $text = 'Handled by error handler. ' . (string)$throwable;
                        parent::__construct($text, $status);
                        $this->setThrowable($throwable);
                    }
                };
            }
        };
    }

    /**
     * @param array $params
     * @param PsrContainerInterface $container
     * @param ServerRequestInterface|null $request
     * @return ResponseInterface
     */
    public static function homeIndex(
        array $params,
        PsrContainerInterface $container,
        ServerRequestInterface $request = null
    ): ResponseInterface {
        // dummy for tests

        $params && $request && $container ?: null;

        self::$isHomeIndexCalled = true;

        return new TextResponse('home index');
    }

    /**
     * @param array $params
     * @param PsrContainerInterface $container
     * @param ServerRequestInterface|null $request
     * @return ResponseInterface
     * @throws Exception
     */
    public static function handlerThatThrowsException(
        array $params,
        PsrContainerInterface $container,
        ServerRequestInterface $request = null
    ): ResponseInterface {
        $params && $request && $container ?: null;

        throw new Exception('The handler emulates an error in a Controller.');
    }

    /**
     * @param array $params
     * @param PsrContainerInterface $container
     * @param ServerRequestInterface|null $request
     * @return ResponseInterface
     * @throws Exception
     */
    public static function handlerThatProducesError(
        array $params,
        PsrContainerInterface $container,
        ServerRequestInterface $request = null
    ): ResponseInterface {
        $params && $request && $container ?: null;

        // it will produce PHP syntax error and that's exactly what
        // we need to test how handler deals with such kind of problems.
        $invalidPhp = <<<EOT
<?php

syntax error
EOT;

        eval($invalidPhp);

        assert(false, 'Unreachable line of code');

        return new TextResponse('Unreachable line of code');
    }

    /**
     * @return ResponseInterface
     */
    public static function postsCreate(): ResponseInterface
    {
        // dummy for tests
        return new TextResponse('post create');
    }

    /**
     * @param array $params
     * @param PsrContainerInterface $container
     * @param ServerRequestInterface|null $request
     * @return ResponseInterface
     * @throws Exception
     */
    public static function homeIndexNoRequest(
        array $params,
        PsrContainerInterface $container,
        ServerRequestInterface $request = null
    ): ResponseInterface {
        // we didn't specify any middleware and request factory was set to null thus request should be null
        self::assertNull($request);

        // dummy for tests

        $params && $request && $container ?: null;

        return new TextResponse('home index');
    }

    /**
     * @param StreamInterface $stream
     * @return string
     */
    private function getText(StreamInterface $stream): string
    {
        return $stream->read($stream->getSize());
    }
}
