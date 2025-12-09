<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Zerotoprod\HttpRouter\HttpRouter;

class RouteResourceTest extends TestCase
{
    /** @test */
    public function resource_generates_all_seven_restful_routes()
    {
        $router = HttpRouter::create()
            ->resource('users', 'UserController');

        $routes = $router->getRoutes();

        $this->assertCount(7, $routes);

        // Verify all 7 routes exist
        $this->assertTrue($router->hasRoute('GET', '/users'));
        $this->assertTrue($router->hasRoute('GET', '/users/create'));
        $this->assertTrue($router->hasRoute('POST', '/users'));
        $this->assertTrue($router->hasRoute('GET', '/users/{id}'));
        $this->assertTrue($router->hasRoute('GET', '/users/{id}/edit'));
        $this->assertTrue($router->hasRoute('PUT', '/users/{id}'));
        $this->assertTrue($router->hasRoute('DELETE', '/users/{id}'));
    }

    /** @test */
    public function resource_generates_routes_with_correct_names()
    {
        $router = HttpRouter::create()
            ->resource('users', 'UserController');

        $routes = $router->getRoutes();

        // Check route names
        $this->assertEquals('users.index', $routes[0]->name);
        $this->assertEquals('users.create', $routes[1]->name);
        $this->assertEquals('users.store', $routes[2]->name);
        $this->assertEquals('users.show', $routes[3]->name);
        $this->assertEquals('users.edit', $routes[4]->name);
        $this->assertEquals('users.update', $routes[5]->name);
        $this->assertEquals('users.destroy', $routes[6]->name);
    }

    /** @test */
    public function resource_generates_routes_with_correct_actions()
    {
        $router = HttpRouter::create()
            ->resource('users', 'UserController');

        $routes = $router->getRoutes();

        // Check actions
        $this->assertEquals(['UserController', 'index'], $routes[0]->action);
        $this->assertEquals(['UserController', 'create'], $routes[1]->action);
        $this->assertEquals(['UserController', 'store'], $routes[2]->action);
        $this->assertEquals(['UserController', 'show'], $routes[3]->action);
        $this->assertEquals(['UserController', 'edit'], $routes[4]->action);
        $this->assertEquals(['UserController', 'update'], $routes[5]->action);
        $this->assertEquals(['UserController', 'destroy'], $routes[6]->action);
    }

    /** @test */
    public function resource_only_generates_specified_routes_with_array()
    {
        $router = HttpRouter::create()
            ->resource('users', 'UserController', ['only' => ['index', 'show']]);

        $routes = $router->getRoutes();

        $this->assertCount(2, $routes);
        $this->assertTrue($router->hasRoute('GET', '/users'));
        $this->assertTrue($router->hasRoute('GET', '/users/{id}'));
        $this->assertFalse($router->hasRoute('POST', '/users'));
    }

    /** @test */
    public function resource_only_generates_specified_routes_with_string()
    {
        $router = HttpRouter::create()
            ->resource('users', 'UserController', ['only' => 'index']);

        $routes = $router->getRoutes();

        $this->assertCount(1, $routes);
        $this->assertTrue($router->hasRoute('GET', '/users'));
    }

    /** @test */
    public function resource_except_excludes_specified_routes_with_array()
    {
        $router = HttpRouter::create()
            ->resource('users', 'UserController', ['except' => ['destroy', 'edit']]);

        $routes = $router->getRoutes();

        $this->assertCount(5, $routes);
        $this->assertTrue($router->hasRoute('GET', '/users'));
        $this->assertTrue($router->hasRoute('POST', '/users'));
        $this->assertFalse($router->hasRoute('DELETE', '/users/{id}'));
        $this->assertFalse($router->hasRoute('GET', '/users/{id}/edit'));
    }

    /** @test */
    public function resource_except_excludes_specified_routes_with_string()
    {
        $router = HttpRouter::create()
            ->resource('users', 'UserController', ['except' => 'destroy']);

        $routes = $router->getRoutes();

        $this->assertCount(6, $routes);
        $this->assertFalse($router->hasRoute('DELETE', '/users/{id}'));
    }

    /** @test */
    public function resource_handles_nested_resource_names()
    {
        $router = HttpRouter::create()
            ->resource('admin/users', 'AdminUserController');

        $routes = $router->getRoutes();

        $this->assertTrue($router->hasRoute('GET', '/admin/users'));
        $this->assertTrue($router->hasRoute('GET', '/admin/users/{id}'));
    }

    /** @test */
    public function resource_strips_leading_and_trailing_slashes()
    {
        $router = HttpRouter::create()
            ->resource('/users/', 'UserController');

        $routes = $router->getRoutes();

        $this->assertTrue($router->hasRoute('GET', '/users'));
        $this->assertTrue($router->hasRoute('GET', '/users/{id}'));
    }

    /** @test */
    public function resource_routes_are_cacheable()
    {
        $router = HttpRouter::create()
            ->resource('users', 'UserController');

        $this->assertTrue($router->isCacheable());
    }

    /** @test */
    public function resource_routes_can_be_compiled_and_loaded()
    {
        $router1 = HttpRouter::create()
            ->resource('users', 'UserController');

        $compiled = $router1->compile();

        $router2 = HttpRouter::create()->loadCompiled($compiled);
        $routes = $router2->getRoutes();

        $this->assertCount(7, $routes);
        $this->assertTrue($router2->hasRoute('GET', '/users'));
        $this->assertTrue($router2->hasRoute('GET', '/users/{id}'));
    }
}
