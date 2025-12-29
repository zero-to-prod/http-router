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

    /** @test */
    public function middleware_not_applied_to_routes_between_groups()
    {
        // This test verifies the fix for the bug where middleware intended for a second group
        // was incorrectly applied to the last route defined before the middleware()->group() call
        $router = HttpRouter::create();

        // First group with middleware
        $router->middleware(['ApplicationAuthMiddleware'])
            ->group(function ($r) {
                $r->get('/', ['IndexController', 'index']);
            });

        // Routes outside of group
        $router->get('/oauth2callback', ['ApplicationOauth2CallbackController', 'callback']);
        $router->get('/logout', ['ApplicationLogoutController', 'logout']);

        // Second group with middleware - this should NOT affect /logout
        $router->middleware(['CentralizedAuthMiddleware'])
            ->group(function ($r) {
                $r->get('/checkout', ['CheckoutController', 'checkout']);
            });

        $routes = $router->getRoutes();

        // Verify route 0: / should have ApplicationAuthMiddleware
        $this->assertEquals('/', $routes[0]->pattern);
        $this->assertContains('ApplicationAuthMiddleware', $routes[0]->middleware);
        $this->assertNotContains('CentralizedAuthMiddleware', $routes[0]->middleware);

        // Verify route 1: /oauth2callback should have NO middleware
        $this->assertEquals('/oauth2callback', $routes[1]->pattern);
        $this->assertEmpty($routes[1]->middleware);

        // Verify route 2: /logout should have NO middleware (this was the bug)
        $this->assertEquals('/logout', $routes[2]->pattern);
        $this->assertEmpty($routes[2]->middleware);

        // Verify route 3: /checkout should have CentralizedAuthMiddleware
        $this->assertEquals('/checkout', $routes[3]->pattern);
        $this->assertContains('CentralizedAuthMiddleware', $routes[3]->middleware);
        $this->assertNotContains('ApplicationAuthMiddleware', $routes[3]->middleware);
    }

    /** @test */
    public function middleware_applied_to_single_route_when_not_followed_by_group()
    {
        // Verify that middleware() still works for single routes when NOT followed by group()
        $router = HttpRouter::create();

        $router->get('/public', ['PublicController', 'index']);
        $router->get('/protected', ['ProtectedController', 'index'])->middleware('AuthMiddleware');
        $router->get('/admin', ['AdminController', 'index'])->middleware('AdminMiddleware');

        $routes = $router->getRoutes();

        // /public should have no middleware
        $this->assertEquals('/public', $routes[0]->pattern);
        $this->assertEmpty($routes[0]->middleware);

        // /protected should have AuthMiddleware
        $this->assertEquals('/protected', $routes[1]->pattern);
        $this->assertContains('AuthMiddleware', $routes[1]->middleware);

        // /admin should have AdminMiddleware
        $this->assertEquals('/admin', $routes[2]->pattern);
        $this->assertContains('AdminMiddleware', $routes[2]->middleware);
    }

    /** @test */
    public function multiple_middleware_group_chains_work_correctly()
    {
        // Test multiple middleware()->group() chains in sequence
        $router = HttpRouter::create();

        $router->middleware('Middleware1')
            ->group(function ($r) {
                $r->get('/group1', ['Controller1', 'index']);
            });

        $router->get('/between1', ['BetweenController1', 'index']);

        $router->middleware('Middleware2')
            ->group(function ($r) {
                $r->get('/group2', ['Controller2', 'index']);
            });

        $router->get('/between2', ['BetweenController2', 'index']);

        $router->middleware('Middleware3')
            ->group(function ($r) {
                $r->get('/group3', ['Controller3', 'index']);
            });

        $routes = $router->getRoutes();

        // /group1 should have Middleware1
        $this->assertEquals('/group1', $routes[0]->pattern);
        $this->assertContains('Middleware1', $routes[0]->middleware);
        $this->assertNotContains('Middleware2', $routes[0]->middleware);
        $this->assertNotContains('Middleware3', $routes[0]->middleware);

        // /between1 should have NO middleware
        $this->assertEquals('/between1', $routes[1]->pattern);
        $this->assertEmpty($routes[1]->middleware);

        // /group2 should have Middleware2
        $this->assertEquals('/group2', $routes[2]->pattern);
        $this->assertContains('Middleware2', $routes[2]->middleware);
        $this->assertNotContains('Middleware1', $routes[2]->middleware);
        $this->assertNotContains('Middleware3', $routes[2]->middleware);

        // /between2 should have NO middleware
        $this->assertEquals('/between2', $routes[3]->pattern);
        $this->assertEmpty($routes[3]->middleware);

        // /group3 should have Middleware3
        $this->assertEquals('/group3', $routes[4]->pattern);
        $this->assertContains('Middleware3', $routes[4]->middleware);
        $this->assertNotContains('Middleware1', $routes[4]->middleware);
        $this->assertNotContains('Middleware2', $routes[4]->middleware);
    }
}
