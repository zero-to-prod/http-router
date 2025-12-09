<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Zerotoprod\HttpRouter\HttpRouter;

class RouteGroupTest extends TestCase
{
    /** @test */
    public function group_applies_prefix_to_routes()
    {
        $router = HttpRouter::create()
            ->prefix('admin')
            ->group(function ($r) {
                $r->get('/users', ['Controller', 'users']);
                $r->get('/posts', ['Controller', 'posts']);
            });

        $this->assertTrue($router->hasRoute('GET', '/admin/users'));
        $this->assertTrue($router->hasRoute('GET', '/admin/posts'));
    }

    /** @test */
    public function group_applies_middleware_to_routes()
    {
        $router = HttpRouter::create()
            ->middleware('AuthMiddleware')
            ->group(function ($r) {
                $r->get('/users', ['Controller', 'users']);
            });

        $routes = $router->getRoutes();
        $this->assertContains('AuthMiddleware', $routes[0]->middleware);
    }

    /** @test */
    public function group_applies_prefix_and_middleware_together()
    {
        $router = HttpRouter::create()
            ->prefix('admin')
            ->middleware('AuthMiddleware')
            ->group(function ($r) {
                $r->get('/users', ['Controller', 'users']);
            });

        $routes = $router->getRoutes();
        $this->assertTrue($router->hasRoute('GET', '/admin/users'));
        $this->assertContains('AuthMiddleware', $routes[0]->middleware);
    }

    /** @test */
    public function nested_groups_stack_prefixes()
    {
        $router = HttpRouter::create()
            ->prefix('api')
            ->group(function ($r) {
                $r->prefix('v1')
                    ->group(function ($r) {
                        $r->get('/users', ['Controller', 'users']);
                    });
            });

        $this->assertTrue($router->hasRoute('GET', '/api/v1/users'));
    }

    /** @test */
    public function nested_groups_stack_middleware()
    {
        $router = HttpRouter::create()
            ->middleware('Middleware1')
            ->group(function ($r) {
                $r->middleware('Middleware2')
                    ->group(function ($r) {
                        $r->get('/users', ['Controller', 'users']);
                    });
            });

        $routes = $router->getRoutes();
        $this->assertContains('Middleware1', $routes[0]->middleware);
        $this->assertContains('Middleware2', $routes[0]->middleware);
    }

    /** @test */
    public function group_with_multiple_middleware()
    {
        $router = HttpRouter::create()
            ->middleware(['Middleware1', 'Middleware2'])
            ->group(function ($r) {
                $r->get('/users', ['Controller', 'users']);
            });

        $routes = $router->getRoutes();
        $this->assertContains('Middleware1', $routes[0]->middleware);
        $this->assertContains('Middleware2', $routes[0]->middleware);
    }

    /** @test */
    public function empty_group_works()
    {
        $router = HttpRouter::create()
            ->prefix('admin')
            ->group(function ($r) {
                // Empty group
            });

        $this->assertCount(0, $router->getRoutes());
    }

    /** @test */
    public function group_with_resource_routes()
    {
        $router = HttpRouter::create()
            ->prefix('api')
            ->group(function ($r) {
                $r->resource('users', 'UserController', ['only' => ['index', 'show']]);
            });

        $this->assertTrue($router->hasRoute('GET', '/api/users'));
        $this->assertTrue($router->hasRoute('GET', '/api/users/{id}'));
    }

    /** @test */
    public function group_preserves_route_names()
    {
        $router = HttpRouter::create()
            ->prefix('admin')
            ->group(function ($r) {
                $r->get('/users', ['Controller', 'users'])->name('admin.users');
            });

        $routes = $router->getRoutes();
        $this->assertEquals('admin.users', $routes[0]->name);
    }

    /** @test */
    public function routes_outside_group_not_affected()
    {
        $router = HttpRouter::create()
            ->get('/home', ['Controller', 'home'])
            ->prefix('admin')
            ->group(function ($r) {
                $r->get('/users', ['Controller', 'users']);
            })
            ->get('/about', ['Controller', 'about']);

        $this->assertTrue($router->hasRoute('GET', '/home'));
        $this->assertTrue($router->hasRoute('GET', '/admin/users'));
        $this->assertTrue($router->hasRoute('GET', '/about'));
    }

    /** @test */
    public function group_strips_leading_trailing_slashes_from_prefix()
    {
        $router = HttpRouter::create()
            ->prefix('/admin/')
            ->group(function ($r) {
                $r->get('/users', ['Controller', 'users']);
            });

        $this->assertTrue($router->hasRoute('GET', '/admin/users'));
    }

    /** @test */
    public function grouped_routes_are_cacheable()
    {
        $router = HttpRouter::create()
            ->prefix('admin')
            ->middleware('AuthMiddleware')
            ->group(function ($r) {
                $r->get('/users', ['Controller', 'users']);
            });

        $this->assertTrue($router->isCacheable());
    }

    /** @test */
    public function grouped_routes_can_be_compiled_and_loaded()
    {
        $router1 = HttpRouter::create()
            ->prefix('admin')
            ->middleware('AuthMiddleware')
            ->group(function ($r) {
                $r->get('/users', ['Controller', 'users']);
            });

        $compiled = $router1->compile();
        $router2 = HttpRouter::create()->loadCompiled($compiled);

        $this->assertTrue($router2->hasRoute('GET', '/admin/users'));
        $routes = $router2->getRoutes();
        $this->assertContains('AuthMiddleware', $routes[0]->middleware);
    }
}
