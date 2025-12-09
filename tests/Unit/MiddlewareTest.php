<?php

namespace Tests\Unit;

use Tests\TestCase;
use Zerotoprod\HttpRouter\Route;
use Zerotoprod\HttpRouter\HttpRouter;

class MiddlewareTest extends TestCase
{
    /** @test */
    public function global_middleware_executes_before_route_action(): void
    {
        $execution_order = [];

        $routes = HttpRouter::for('GET', '/test')
            ->globalMiddleware(function ($next) use (&$execution_order) {
                $execution_order[] = 'global_before';
                $next();
                $execution_order[] = 'global_after';
            })
            ->get('/test', function () use (&$execution_order) {
                $execution_order[] = 'action';
            });

        $routes->dispatch();

        $this->assertEquals(['global_before', 'action', 'global_after'], $execution_order);
    }

    /** @test */
    public function route_middleware_executes_after_global_middleware(): void
    {
        $execution_order = [];

        $routes = HttpRouter::for('GET', '/test')
            ->globalMiddleware(function ($next) use (&$execution_order) {
                $execution_order[] = 'global';
                $next();
            })
            ->get('/test', function () use (&$execution_order) {
                $execution_order[] = 'action';
            })
            ->middleware(function ($next) use (&$execution_order) {
                $execution_order[] = 'route';
                $next();
            });

        $routes->dispatch();

        $this->assertEquals(['global', 'route', 'action'], $execution_order);
    }

    /** @test */
    public function middleware_receives_dispatch_args(): void
    {
        $received_args = null;

        $server = ['TEST_KEY' => 'test_value'];
        $db = new \stdClass();
        $db->name = 'database';

        $routes = HttpRouter::for('GET', '/test', $server, $db)
            ->globalMiddleware(function ($next, ...$context) use (&$received_args) {
                $received_args = $context;
                $next();
            })
            ->get('/test', function () {});

        $routes->dispatch();

        $this->assertIsArray($received_args);
        $this->assertCount(2, $received_args);
        $this->assertEquals('test_value', $received_args[0]['TEST_KEY']);
        $this->assertEquals('database', $received_args[1]->name);
    }

    /** @test */
    public function middleware_can_halt_execution(): void
    {
        $action_executed = false;

        $routes = HttpRouter::for('GET', '/test')
            ->globalMiddleware(function ($next) {
                echo 'Halted';
            })
            ->get('/test', function () use (&$action_executed) {
                $action_executed = true;
            });

        $routes->dispatch();

        $this->assertFalse($action_executed);
    }

    /** @test */
    public function multiple_middleware_execute_in_order(): void
    {
        $execution_order = [];

        $routes = HttpRouter::for('GET', '/test')
            ->globalMiddleware([
                function ($next) use (&$execution_order) {
                    $execution_order[] = 'middleware1';
                    $next();
                },
                function ($next) use (&$execution_order) {
                    $execution_order[] = 'middleware2';
                    $next();
                },
                function ($next) use (&$execution_order) {
                    $execution_order[] = 'middleware3';
                    $next();
                },
            ])
            ->get('/test', function () use (&$execution_order) {
                $execution_order[] = 'action';
            });

        $routes->dispatch();

        $this->assertEquals(['middleware1', 'middleware2', 'middleware3', 'action'], $execution_order);
    }

    /** @test */
    public function invokable_middleware_class_works(): void
    {
        TestInvokableMiddleware::$executed = false;

        $routes = HttpRouter::for('GET', '/test')
            ->get('/test', function () {})
            ->middleware(TestInvokableMiddleware::class);

        $routes->dispatch();

        $this->assertTrue(TestInvokableMiddleware::$executed);
    }

    /** @test */
    public function routes_with_closure_middleware_are_not_cacheable(): void
    {
        $routes = HttpRouter::for()
            ->globalMiddleware(function ($next) {
                $next();
            })
            ->get('/test', [MiddlewareTestController::class, 'method']);

        $this->assertFalse($routes->isCacheable());
    }

    /** @test */
    public function routes_with_class_middleware_are_cacheable(): void
    {
        $routes = HttpRouter::for()
            ->globalMiddleware(TestInvokableMiddleware::class)
            ->get('/test', [MiddlewareTestController::class, 'method']);

        $this->assertTrue($routes->isCacheable());
    }

    /** @test */
    public function can_compile_and_load_routes_with_middleware(): void
    {
        $routes1 = HttpRouter::for()
            ->globalMiddleware(TestInvokableMiddleware::class)
            ->get('/test', [MiddlewareTestController::class, 'method'])
                ->middleware(AnotherMiddleware::class);

        $compiled = $routes1->compile();

        $routes2 = HttpRouter::for()->loadCompiled($compiled);

        $matched_route = $routes2->matchRoute('GET', '/test');
        $this->assertNotNull($matched_route);
        $this->assertCount(1, $matched_route->middleware);
        $this->assertEquals(AnotherMiddleware::class, $matched_route->middleware[0]);
    }

    /** @test */
    public function middleware_not_executed_for_non_matching_routes(): void
    {
        $middleware_executed = false;

        $routes = HttpRouter::for('GET', '/other')
            ->get('/test', function () {})
            ->middleware(function ($next) use (&$middleware_executed) {
                $middleware_executed = true;
                $next();
            });

        $routes->dispatch();

        $this->assertFalse($middleware_executed);
    }

    /** @test */
    public function fallback_handler_executes_with_global_middleware(): void
    {
        $middleware_executed = false;
        $fallback_executed = false;

        $routes = HttpRouter::for('GET', '/non-existent')
            ->globalMiddleware(function ($next) use (&$middleware_executed) {
                $middleware_executed = true;
                $next();
            })
            ->get('/test', function () {})
            ->fallback(function () use (&$fallback_executed) {
                $fallback_executed = true;
            });

        $routes->dispatch();

        $this->assertTrue($middleware_executed);
        $this->assertTrue($fallback_executed);
    }

    /** @test */
    public function middleware_chaining_works(): void
    {
        $execution_order = [];

        $routes = HttpRouter::for('GET', '/test')
            ->globalMiddleware(function ($next) use (&$execution_order) {
                $execution_order[] = 'global1';
                $next();
            })
            ->globalMiddleware(function ($next) use (&$execution_order) {
                $execution_order[] = 'global2';
                $next();
            })
            ->get('/test', function () use (&$execution_order) {
                $execution_order[] = 'action';
            })
            ->middleware(function ($next) use (&$execution_order) {
                $execution_order[] = 'route1';
                $next();
            })
            ->middleware(function ($next) use (&$execution_order) {
                $execution_order[] = 'route2';
                $next();
            });

        $routes->dispatch();

        $this->assertEquals(['global1', 'global2', 'route1', 'route2', 'action'], $execution_order);
    }

    /** @test */
    public function route_without_middleware_executes_directly(): void
    {
        $action_executed = false;

        $routes = HttpRouter::for('GET', '/test')
            ->get('/test', function () use (&$action_executed) {
                $action_executed = true;
            });

        $routes->dispatch();

        $this->assertTrue($action_executed);
    }
}

class TestInvokableMiddleware
{
    public static $executed = false;

    public function __invoke($next)
    {
        self::$executed = true;
        $next();
    }
}

class AnotherMiddleware
{
    public function __invoke($next)
    {
        $next();
    }
}

class MiddlewareTestController
{
    public function method($params)
    {
    }
}
