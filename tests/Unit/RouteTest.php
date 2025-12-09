<?php

namespace Tests\Unit;

use InvalidArgumentException;
use Tests\TestCase;
use Zerotoprod\HttpRouter\Route;
use Zerotoprod\HttpRouter\HttpRouter;

class RouteTest extends TestCase
{

    /** @test */
    public function can_register_static_route(): void
    {
        $executed = false;

        $routes = HttpRouter::for('GET', '/users')
            ->get('/users', function (array $params) use (&$executed) {
                $executed = true;
            });

        $routes->dispatch();

        $this->assertTrue($executed);
    }

    /** @test */
    public function static_route_receives_empty_params_array(): void
    {
        $received_params = null;

        $routes = HttpRouter::for('GET', '/users')
            ->get('/users', function (array $params) use (&$received_params) {
                $received_params = $params;
            });

        $routes->dispatch();

        $this->assertEquals([], $received_params);
    }

    /** @test */
    public function can_register_dynamic_route_with_required_param(): void
    {
        $received_params = null;

        $routes = HttpRouter::for('GET', '/users/123')
            ->get('/users/{id}', function (array $params) use (&$received_params) {
                $received_params = $params;
            });

        $routes->dispatch();

        $this->assertEquals(['id' => '123'], $received_params);
    }

    /** @test */
    public function can_register_route_with_inline_constraint(): void
    {
        $received_params = null;

        $routes = HttpRouter::for('GET', '/users/123')
            ->get('/users/{id:\d+}', function (array $params) use (&$received_params) {
                $received_params = $params;
            });

        $routes->dispatch();

        $this->assertEquals(['id' => '123'], $received_params);
    }

    /** @test */
    public function inline_constraint_rejects_non_matching_values(): void
    {
        $executed = false;

        $routes = HttpRouter::for('GET', '/users/abc')
            ->get('/users/{id:\d+}', function () use (&$executed) {
                $executed = true;
            });

        $routes->dispatch();

        $this->assertFalse($executed);
    }

    /** @test */
    public function can_add_constraint_with_where_method(): void
    {
        $received_params = null;

        $routes = HttpRouter::for('GET', '/users/456')
            ->get('/users/{id}', function (array $params) use (&$received_params) {
                $received_params = $params;
            })
            ->where('id', '\d+');

        $routes->dispatch();

        $this->assertEquals(['id' => '456'], $received_params);
    }

    /** @test */
    public function where_constraint_rejects_non_matching_values(): void
    {
        $executed = false;

        $routes = HttpRouter::for('GET', '/users/abc')
            ->get('/users/{id}', function () use (&$executed) {
                $executed = true;
            })
            ->where('id', '\d+');

        $routes->dispatch();

        $this->assertFalse($executed);
    }

    /** @test */
    public function optional_parameter_matches_without_value(): void
    {
        $received_params = null;

        $routes = HttpRouter::for('GET', '/users')
            ->get('/users/{id?}', function (array $params) use (&$received_params) {
                $received_params = $params;
            });

        $routes->dispatch();

        $this->assertEquals([], $received_params);
    }

    /** @test */
    public function optional_parameter_matches_with_value(): void
    {
        $received_params = null;

        $routes = HttpRouter::for('GET', '/users/789')
            ->get('/users/{id?}', function (array $params) use (&$received_params) {
                $received_params = $params;
            });

        $routes->dispatch();

        $this->assertEquals(['id' => '789'], $received_params);
    }

    /** @test */
    public function match_route_returns_route_object(): void
    {
        $routes = HttpRouter::for()
            ->get('/users/{id}', function () {
            });

        $route = $routes->matchRoute('GET', '/users/123');

        $this->assertInstanceOf(Route::class, $route);
        $this->assertEquals('GET', $route->method);
        $this->assertEquals('/users/{id}', $route->pattern);
    }

    /** @test */
    public function match_route_returns_null_when_no_match(): void
    {
        $routes = HttpRouter::for()
            ->get('/users/{id}', function () {
            });

        $route = $routes->matchRoute('GET', '/posts/123');

        $this->assertNull($route);
    }

    /** @test */
    public function get_routes_returns_all_registered_routes(): void
    {
        $routes = HttpRouter::for()
            ->get('/users', function () {
            })
            ->get('/posts/{id}', function () {
            })
            ->post('/users', function () {
            });

        $allRoutes = $routes->getRoutes();

        $this->assertCount(3, $allRoutes);
    }

    /** @test */
    public function has_route_returns_true_for_existing_route(): void
    {
        $routes = HttpRouter::for()
            ->get('/users/{id}', function () {
            });

        $this->assertTrue($routes->hasRoute('GET', '/users/{id}'));
    }

    /** @test */
    public function has_route_returns_false_for_non_existing_route(): void
    {
        $routes = HttpRouter::for()
            ->get('/users/{id}', function () {
            });

        $this->assertFalse($routes->hasRoute('POST', '/users/{id}'));
    }

    /** @test */
    public function throws_exception_when_action_is_null(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Action cannot be null');

        HttpRouter::for()->get('/users', null);
    }

    /** @test */
    public function post_route_works(): void
    {
        $received_params = null;

        $routes = HttpRouter::for('POST', '/users/456')
            ->post('/users/{id}', function (array $params) use (&$received_params) {
                $received_params = $params;
            });

        $routes->dispatch();

        $this->assertEquals(['id' => '456'], $received_params);
    }

    /** @test */
    public function put_route_works(): void
    {
        $executed = false;

        $routes = HttpRouter::for('PUT', '/users/789')
            ->put('/users/{id}', function () use (&$executed) {
                $executed = true;
            });

        $routes->dispatch();

        $this->assertTrue($executed);
    }

    /** @test */
    public function patch_route_works(): void
    {
        $executed = false;

        $routes = HttpRouter::for('PATCH', '/users/111')
            ->patch('/users/{id}', function () use (&$executed) {
                $executed = true;
            });

        $routes->dispatch();

        $this->assertTrue($executed);
    }

    /** @test */
    public function delete_route_works(): void
    {
        $executed = false;

        $routes = HttpRouter::for('DELETE', '/users/222')
            ->delete('/users/{id}', function () use (&$executed) {
                $executed = true;
            });

        $routes->dispatch();

        $this->assertTrue($executed);
    }

    /** @test */
    public function options_route_works(): void
    {
        $executed = false;

        $routes = HttpRouter::for('OPTIONS', '/users/333')
            ->options('/users/{id}', function () use (&$executed) {
                $executed = true;
            });

        $routes->dispatch();

        $this->assertTrue($executed);
    }

    /** @test */
    public function head_route_works(): void
    {
        $executed = false;

        $routes = HttpRouter::for('HEAD', '/users/444')
            ->head('/users/{id}', function () use (&$executed) {
                $executed = true;
            });

        $routes->dispatch();

        $this->assertTrue($executed);
    }

    /** @test */
    public function multiple_parameters_in_route(): void
    {
        $received_params = null;

        $routes = HttpRouter::for('GET', '/users/42/posts/99')
            ->get('/users/{userId}/posts/{postId}', function (array $params) use (&$received_params) {
                $received_params = $params;
            });

        $routes->dispatch();

        $this->assertEquals(['userId' => '42', 'postId' => '99'], $received_params);
    }

    /** @test */
    public function where_method_with_array_of_constraints(): void
    {
        $received_params = null;

        $routes = HttpRouter::for('GET', '/users/123/posts/my-post')
            ->get('/users/{id}/posts/{slug}', function (array $params) use (&$received_params) {
                $received_params = $params;
            })
            ->where(['id' => '\d+', 'slug' => '[a-z0-9-]+']);

        $routes->dispatch();

        $this->assertEquals(['id' => '123', 'slug' => 'my-post'], $received_params);
    }

    /** @test */
    public function inline_constraint_with_optional_parameter(): void
    {
        $received_params = null;

        $routes = HttpRouter::for('GET', '/users')
            ->get('/users/{id:\d+?}', function (array $params) use (&$received_params) {
                $received_params = $params;
            });

        $routes->dispatch();

        $this->assertEquals([], $received_params);
    }

    /** @test */
    public function inline_constraint_with_optional_parameter_present(): void
    {
        $received_params = null;

        $routes = HttpRouter::for('GET', '/users/555')
            ->get('/users/{id:\d+?}', function (array $params) use (&$received_params) {
                $received_params = $params;
            });

        $routes->dispatch();

        $this->assertEquals(['id' => '555'], $received_params);
    }

    /** @test */
    public function multiple_optional_parameters(): void
    {
        $received_params = null;

        $routes = HttpRouter::for('GET', '/search/test')
            ->get('/search/{query?}/{category?}', function (array $params) use (&$received_params) {
                $received_params = $params;
            });

        $routes->dispatch();

        $this->assertEquals(['query' => 'test'], $received_params);
    }

    /** @test */
    public function mixed_required_and_optional_parameters(): void
    {
        $received_params = null;

        $routes = HttpRouter::for('GET', '/users/123/posts')
            ->get('/users/{id}/posts/{slug?}', function (array $params) use (&$received_params) {
                $received_params = $params;
            });

        $routes->dispatch();

        $this->assertEquals(['id' => '123'], $received_params);
    }

    /** @test */
    public function fallback_handler_executes_when_no_match(): void
    {
        $fallback_executed = false;

        $routes = HttpRouter::for('GET', '/nonexistent')
            ->get('/users', function () {
            })
            ->fallback(function () use (&$fallback_executed) {
                $fallback_executed = true;
            });

        $routes->dispatch();

        $this->assertTrue($fallback_executed);
    }

    /** @test */
    public function fallback_receives_empty_params_array(): void
    {
        $received_params = null;

        $routes = HttpRouter::for('GET', '/anything')
            ->fallback(function (array $params) use (&$received_params) {
                $received_params = $params;
            });

        $routes->dispatch();

        $this->assertEquals([], $received_params);
    }

    /** @test */
    public function route_name_can_be_set(): void
    {
        $routes = HttpRouter::for()
            ->get('/users/{id}', function () {
            })
            ->name('user.show');

        $route = $routes->matchRoute('GET', '/users/123');

        $this->assertEquals('user.show', $route->name);
    }

    /** @test */
    public function controller_array_syntax_works(): void
    {
        $routes = HttpRouter::for('GET', '/test')
            ->get('/test', [TestController::class, 'handle']);

        TestController::$executed = false;
        $routes->dispatch();

        $this->assertTrue(TestController::$executed);
    }

    /** @test */
    public function controller_receives_params_array(): void
    {
        $routes = HttpRouter::for('GET', '/users/999')
            ->get('/users/{id}', [TestController::class, 'showWithParams']);

        TestController::$received_params = null;
        $routes->dispatch();

        $this->assertEquals(['id' => '999'], TestController::$received_params);
    }

    /** @test */
    public function invokeable_controller_works(): void
    {
        $routes = HttpRouter::for('GET', '/invoke')
            ->get('/invoke', InvokeableTestController::class);

        InvokeableTestController::$invoked = false;
        $routes->dispatch();

        $this->assertTrue(InvokeableTestController::$invoked);
    }

    /** @test */
    public function string_action_echoes_output(): void
    {
        $routes = HttpRouter::for('GET', '/hello')
            ->get('/hello', function () {
                echo 'Hello World';
            });

        ob_start();
        $routes->dispatch();
        $output = ob_get_clean();

        $this->assertEquals('Hello World', $output);
    }

    /** @test */
    public function additional_arguments_passed_to_action(): void
    {
        $received_args = [];

        $routes = HttpRouter::for('GET', '/test', 'arg1', 'arg2')
            ->get('/test', function (array $params, ...$args) use (&$received_args) {
                $received_args = $args;
            });

        $routes->dispatch();

        $this->assertEquals(['arg1', 'arg2'], $received_args);
    }

    /** @test */
    public function query_string_is_stripped(): void
    {
        $executed = false;

        $routes = HttpRouter::for('GET', '/search?q=test&page=1')
            ->get('/search', function () use (&$executed) {
                $executed = true;
            });

        $routes->dispatch();

        $this->assertTrue($executed);
    }

    /** @test */
    public function static_route_takes_priority_over_patterned(): void
    {
        $static_executed = false;
        $dynamic_executed = false;

        $routes = HttpRouter::for('GET', '/users/create')
            ->get('/users/create', function () use (&$static_executed) {
                $static_executed = true;
            })
            ->get('/users/{action}', function () use (&$dynamic_executed) {
                $dynamic_executed = true;
            });

        $routes->dispatch();

        $this->assertTrue($static_executed);
        $this->assertFalse($dynamic_executed);
    }

    /** @test */
    public function compile_and_load_cacheable_routes(): void
    {
        $routes1 = HttpRouter::for()
            ->get('/users/{id}', [TestController::class, 'handle'])
            ->where('id', '\d+');

        $compiled = $routes1->compile();

        $routes2 = HttpRouter::for('GET', '/users/123')->loadCompiled($compiled);

        TestController::$executed = false;
        $routes2->dispatch();

        $this->assertTrue(TestController::$executed);
    }

    /** @test */
    public function compile_throws_exception_with_closures(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('closures');

        $routes = HttpRouter::for()
            ->get('/test', function () {
            });

        $routes->compile();
    }

    /** @test */
    public function is_cacheable_returns_false_for_closures(): void
    {
        $routes = HttpRouter::for()
            ->get('/test', function () {
            });

        $this->assertFalse($routes->isCacheable());
    }

    /** @test */
    public function is_cacheable_returns_true_for_controller_arrays(): void
    {
        $routes = HttpRouter::for()
            ->get('/test', [TestController::class, 'handle']);

        $this->assertTrue($routes->isCacheable());
    }

    /** @test */
    public function route_does_not_match_wrong_method(): void
    {
        $executed = false;

        $routes = HttpRouter::for('POST', '/users')
            ->get('/users', function () use (&$executed) {
                $executed = true;
            });

        $routes->dispatch();

        $this->assertFalse($executed);
    }

    /** @test */
    public function route_does_not_match_wrong_uri(): void
    {
        $executed = false;

        $routes = HttpRouter::for('GET', '/posts')
            ->get('/users', function () use (&$executed) {
                $executed = true;
            });

        $routes->dispatch();

        $this->assertFalse($executed);
    }

    /** @test */
    public function constraint_rejects_invalid_pattern(): void
    {
        $executed = false;

        $routes = HttpRouter::for('GET', '/users/123')
            ->get('/users/{id}', function () use (&$executed) {
                $executed = true;
            })
            ->where('id', '[a-z]+');

        $routes->dispatch();

        $this->assertFalse($executed);
    }

    /** @test */
    public function wildcard_constraint_matches_path_segments(): void
    {
        $received_params = null;

        $routes = HttpRouter::for('GET', '/search/foo/bar/baz')
            ->get('/search/{query}', function (array $params) use (&$received_params) {
                $received_params = $params;
            })
            ->where('query', '.*');

        $routes->dispatch();

        $this->assertEquals(['query' => 'foo/bar/baz'], $received_params);
    }

    /** @test */
    public function multiple_routes_dispatch_correct_one(): void
    {
        $first_executed = false;
        $second_executed = false;

        $routes = HttpRouter::for('GET', '/posts')
            ->get('/users', function () use (&$first_executed) {
                $first_executed = true;
            })
            ->get('/posts', function () use (&$second_executed) {
                $second_executed = true;
            });

        $routes->dispatch();

        $this->assertFalse($first_executed);
        $this->assertTrue($second_executed);
    }

    /** @test */
    public function method_chaining_works(): void
    {
        $routes = HttpRouter::for()
            ->get('/users', function () {
            })
            ->post('/users', function () {
            })
            ->put('/users/{id}', function () {
            })
            ->where('id', '\d+')
            ->delete('/users/{id}', function () {
            });

        $this->assertCount(4, $routes->getRoutes());
    }

    /** @test */
    public function optional_parameter_at_start_of_pattern(): void
    {
        $received_params = null;

        $routes = HttpRouter::for('GET', '/users')
            ->get('/{lang?}/users', function (array $params) use (&$received_params) {
                $received_params = $params;
            });

        $routes->dispatch();

        $this->assertEquals([], $received_params);
    }

    /** @test */
    public function optional_parameter_at_start_with_value(): void
    {
        $received_params = null;

        $routes = HttpRouter::for('GET', '/en/users')
            ->get('/{lang?}/users', function (array $params) use (&$received_params) {
                $received_params = $params;
            });

        $routes->dispatch();

        $this->assertEquals(['lang' => 'en'], $received_params);
    }

    /** @test */
    public function optional_in_middle_of_pattern(): void
    {
        $received_params = null;

        $routes = HttpRouter::for('GET', '/api/users/123')
            ->get('/api/{version?}/users/{id}', function (array $params) use (&$received_params) {
                $received_params = $params;
            });

        $routes->dispatch();

        $this->assertEquals(['id' => '123'], $received_params);
    }

    /** @test */
    public function optional_in_middle_with_value(): void
    {
        $received_params = null;

        $routes = HttpRouter::for('GET', '/api/v1/users/123')
            ->get('/api/{version?}/users/{id}', function (array $params) use (&$received_params) {
                $received_params = $params;
            });

        $routes->dispatch();

        $this->assertEquals(['version' => 'v1', 'id' => '123'], $received_params);
    }

    /** @test */
    public function all_optional_parameters_missing(): void
    {
        $received_params = null;

        $routes = HttpRouter::for('GET', '/search')
            ->get('/search/{query?}/{filter?}', function (array $params) use (&$received_params) {
                $received_params = $params;
            });

        $routes->dispatch();

        $this->assertEquals([], $received_params);
    }

    /** @test */
    public function combining_inline_and_where_constraints(): void
    {
        $received_params = null;

        $routes = HttpRouter::for('GET', '/users/123/posts/my-post-123')
            ->get('/users/{id:\d+}/posts/{slug}', function (array $params) use (&$received_params) {
                $received_params = $params;
            })
            ->where('slug', '[a-z0-9-]+');

        $routes->dispatch();

        $this->assertEquals(['id' => '123', 'slug' => 'my-post-123'], $received_params);
    }

    /** @test */
    public function where_constraint_overrides_inline_constraint(): void
    {
        $executed = false;

        $routes = HttpRouter::for('GET', '/users/abc')
            ->get('/users/{id:\d+}', function () use (&$executed) {
                $executed = true;
            })
            ->where('id', '[a-z]+');

        $routes->dispatch();

        $this->assertTrue($executed);
    }

    /** @test */
    public function throws_exception_for_invalid_where_constraint(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid regex pattern');

        HttpRouter::for()
            ->get('/users/{id}', function () {
            })
            ->where('id', '(?P<invalid>');
    }

    /** @test */
    public function dispatch_returns_false_when_no_match_and_no_fallback(): void
    {
        $routes = HttpRouter::for('GET', '/posts')
            ->get('/users', function () {
            });

        $result = $routes->dispatch();

        $this->assertFalse($result);
    }

    /** @test */
    public function dispatch_returns_true_when_route_matches(): void
    {
        $routes = HttpRouter::for('GET', '/users')
            ->get('/users', function () {
            });

        $result = $routes->dispatch();

        $this->assertTrue($result);
    }

    /** @test */
    public function dispatch_returns_true_when_fallback_executes(): void
    {
        $routes = HttpRouter::for('GET', '/anything')
            ->fallback(function () {
            });

        $result = $routes->dispatch();

        $this->assertTrue($result);
    }

    /** @test */
    public function route_extract_params_method_works(): void
    {
        $routes = HttpRouter::for()
            ->get('/users/{id}', function () {
            });

        $route = $routes->matchRoute('GET', '/users/789');
        $params = $route->extractParams('/users/789');

        $this->assertEquals(['id' => '789'], $params);
    }

    /** @test */
    public function route_matches_method_works(): void
    {
        $routes = HttpRouter::for()
            ->get('/users/{id}', function () {
            });

        $route = $routes->matchRoute('GET', '/users/123');

        $this->assertTrue($route->matches('/users/123'));
        $this->assertFalse($route->matches('/posts/123'));
    }

    /** @test */
    public function get_routes_returns_empty_array_for_new_collection(): void
    {
        $routes = HttpRouter::for();

        $this->assertEquals([], $routes->getRoutes());
    }

    /** @test */
    public function has_route_checks_method_and_pattern(): void
    {
        $routes = HttpRouter::for()
            ->get('/users/{id}', function () {
            })
            ->post('/users/{id}', function () {
            });

        $this->assertTrue($routes->hasRoute('GET', '/users/{id}'));
        $this->assertTrue($routes->hasRoute('POST', '/users/{id}'));
        $this->assertFalse($routes->hasRoute('PUT', '/users/{id}'));
    }

    /** @test */
    public function constraint_with_complex_regex_pattern(): void
    {
        $received_params = null;

        $routes = HttpRouter::for('GET', '/users/550e8400-e29b-41d4-a716-446655440000')
            ->get('/users/{uuid}', function (array $params) use (&$received_params) {
                $received_params = $params;
            })
            ->where('uuid', '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}');

        $routes->dispatch();

        $this->assertEquals(['uuid' => '550e8400-e29b-41d4-a716-446655440000'], $received_params);
    }

    /** @test */
    public function inline_uuid_constraint_via_where(): void
    {
        $received_params = null;

        $routes = HttpRouter::for('GET', '/users/550e8400-e29b-41d4-a716-446655440000')
            ->get('/users/{uuid}', function (array $params) use (&$received_params) {
                $received_params = $params;
            })
            ->where('uuid', '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}');

        $routes->dispatch();

        $this->assertEquals(['uuid' => '550e8400-e29b-41d4-a716-446655440000'], $received_params);
    }

    /** @test */
    public function route_with_hyphenated_parameter_value(): void
    {
        $received_params = null;

        $routes = HttpRouter::for('GET', '/posts/my-blog-post-123')
            ->get('/posts/{slug}', function (array $params) use (&$received_params) {
                $received_params = $params;
            });

        $routes->dispatch();

        $this->assertEquals(['slug' => 'my-blog-post-123'], $received_params);
    }

    /** @test */
    public function three_parameters_in_route(): void
    {
        $received_params = null;

        $routes = HttpRouter::for('GET', '/api/v1/users/42/posts/99')
            ->get('/api/{version}/users/{id}/posts/{postId}', function (array $params) use (&$received_params) {
                $received_params = $params;
            });

        $routes->dispatch();

        $this->assertEquals(['version' => 'v1', 'id' => '42', 'postId' => '99'], $received_params);
    }

    /** @test */
    public function match_route_returns_static_route(): void
    {
        $routes = HttpRouter::for()
            ->get('/static', function () {
            });

        $route = $routes->matchRoute('GET', '/static');

        $this->assertInstanceOf(\Zerotoprod\HttpRouter\Route::class, $route);
        $this->assertEquals('/static', $route->pattern);
    }

    /** @test */
    public function different_methods_same_pattern_are_different_routes(): void
    {
        $routes = HttpRouter::for()
            ->get('/users', function () {
            })
            ->post('/users', function () {
            });

        $get_route = $routes->matchRoute('GET', '/users');
        $post_route = $routes->matchRoute('POST', '/users');

        $this->assertNotNull($get_route);
        $this->assertNotNull($post_route);
        $this->assertEquals('GET', $get_route->method);
        $this->assertEquals('POST', $post_route->method);
    }

    /** @test */
    public function first_matching_patterned_route_wins(): void
    {
        $first_executed = false;
        $second_executed = false;

        $routes = HttpRouter::for('GET', '/items/test')
            ->get('/items/{id}', function () use (&$first_executed) {
                $first_executed = true;
            })
            ->get('/items/{slug}', function () use (&$second_executed) {
                $second_executed = true;
            });

        $routes->dispatch();

        $this->assertTrue($first_executed);
        $this->assertFalse($second_executed);
    }

    /** @test */
    public function optional_parameter_with_constraint_where(): void
    {
        $received_params = null;

        $routes = HttpRouter::for('GET', '/users/777')
            ->get('/users/{id?}', function (array $params) use (&$received_params) {
                $received_params = $params;
            })
            ->where('id', '\d+');

        $routes->dispatch();

        $this->assertEquals(['id' => '777'], $received_params);
    }

    /** @test */
    public function optional_parameter_with_constraint_rejects_invalid(): void
    {
        $executed = false;

        $routes = HttpRouter::for('GET', '/users/abc')
            ->get('/users/{id?}', function () use (&$executed) {
                $executed = true;
            })
            ->where('id', '\d+');

        $routes->dispatch();

        $this->assertFalse($executed);
    }

    /** @test */
    public function empty_pattern_creates_root_route(): void
    {
        $executed = false;

        $routes = HttpRouter::for('GET', '/')
            ->get('/', function () use (&$executed) {
                $executed = true;
            });

        $routes->dispatch();

        $this->assertTrue($executed);
    }

    /** @test */
    public function cached_routes_dispatch_correctly(): void
    {
        $routes1 = HttpRouter::for()
            ->get('/users/{id:\d+}/posts/{slug?}', [TestController::class, 'showWithParams']);

        $compiled = $routes1->compile();

        $routes2 = HttpRouter::for('GET', '/users/42/posts')->loadCompiled($compiled);

        TestController::$received_params = null;
        $routes2->dispatch();

        $this->assertEquals(['id' => '42'], TestController::$received_params);
    }

    /** @test */
    public function fallback_throws_exception_when_action_is_null(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Fallback action cannot be null');

        HttpRouter::for()->fallback(null);
    }

    /** @test */
    public function fallback_returns_self_for_chaining(): void
    {
        $routes = HttpRouter::for();

        $result = $routes->fallback(function () {
        });

        $this->assertSame($routes, $result);
    }

    // Removed test: finalizeRoute() was an internal implementation detail
    // that has been removed as part of the API redesign

    /**
     * @test
     * Note: execute() is now private. Invalid action types are caught at route registration time,
     * not at dispatch time. See tests for controller array validation below.
     */
    public function execute_method_is_private_and_not_part_of_public_api(): void
    {
        $routes = HttpRouter::for();

        // Verify execute() is not accessible from outside
        $this->assertFalse(
            (new \ReflectionMethod(HttpRouter::class, 'execute'))->isPublic(),
            'execute() should be private'
        );
    }

    /** @test */
    public function controller_array_with_zero_elements_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Controller array must have exactly 2 elements');

        $routes = HttpRouter::for('GET', '/test')
            ->get('/test', []);

        $routes->dispatch();
    }

    /** @test */
    public function controller_array_with_one_element_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Controller array must have exactly 2 elements');

        $routes = HttpRouter::for('GET', '/test')
            ->get('/test', [TestController::class]);

        $routes->dispatch();
    }

    /** @test */
    public function controller_array_with_three_elements_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Controller array must have exactly 2 elements');

        $routes = HttpRouter::for('GET', '/test')
            ->get('/test', [TestController::class, 'handle', 'extra']);

        $routes->dispatch();
    }

    /** @test */
    public function load_compiled_returns_self_for_chaining(): void
    {
        $routes1 = HttpRouter::for()
            ->get('/users', [TestController::class, 'handle']);

        $compiled = $routes1->compile();

        $routes2 = HttpRouter::for();
        $result = $routes2->loadCompiled($compiled);

        $this->assertSame($routes2, $result);
    }

    /** @test */
    public function execute_with_controller_array_passes_additional_args(): void
    {
        $routes = HttpRouter::for('GET', '/test', 'arg1', 'arg2', 'arg3')
            ->get('/test', [TestControllerWithArgs::class, 'handleWithArgs']);

        TestControllerWithArgs::$received_args = [];
        $routes->dispatch();

        $this->assertEquals(['arg1', 'arg2', 'arg3'], TestControllerWithArgs::$received_args);
    }

    /** @test */
    public function execute_with_invokable_controller_passes_additional_args(): void
    {
        $routes = HttpRouter::for('GET', '/test', 'foo', 'bar')
            ->get('/test', InvokeableControllerWithArgs::class);

        InvokeableControllerWithArgs::$received_args = [];
        $routes->dispatch();

        $this->assertEquals(['foo', 'bar'], InvokeableControllerWithArgs::$received_args);
    }

    /** @test */
    public function any_route_responds_to_get_request(): void
    {
        $executed = false;

        $routes = HttpRouter::for('GET', '/test')
            ->any('/test', function () use (&$executed) {
                $executed = true;
            });

        $routes->dispatch();

        $this->assertTrue($executed);
    }

    /** @test */
    public function any_route_responds_to_post_request(): void
    {
        $executed = false;

        $routes = HttpRouter::for('POST', '/test')
            ->any('/test', function () use (&$executed) {
                $executed = true;
            });

        $routes->dispatch();

        $this->assertTrue($executed);
    }

    /** @test */
    public function any_route_responds_to_put_request(): void
    {
        $executed = false;

        $routes = HttpRouter::for('PUT', '/test')
            ->any('/test', function () use (&$executed) {
                $executed = true;
            });

        $routes->dispatch();

        $this->assertTrue($executed);
    }

    /** @test */
    public function any_route_responds_to_patch_request(): void
    {
        $executed = false;

        $routes = HttpRouter::for('PATCH', '/test')
            ->any('/test', function () use (&$executed) {
                $executed = true;
            });

        $routes->dispatch();

        $this->assertTrue($executed);
    }

    /** @test */
    public function any_route_responds_to_delete_request(): void
    {
        $executed = false;

        $routes = HttpRouter::for('DELETE', '/test')
            ->any('/test', function () use (&$executed) {
                $executed = true;
            });

        $routes->dispatch();

        $this->assertTrue($executed);
    }

    /** @test */
    public function any_route_responds_to_options_request(): void
    {
        $executed = false;

        $routes = HttpRouter::for('OPTIONS', '/test')
            ->any('/test', function () use (&$executed) {
                $executed = true;
            });

        $routes->dispatch();

        $this->assertTrue($executed);
    }

    /** @test */
    public function any_route_responds_to_head_request(): void
    {
        $executed = false;

        $routes = HttpRouter::for('HEAD', '/test')
            ->any('/test', function () use (&$executed) {
                $executed = true;
            });

        $routes->dispatch();

        $this->assertTrue($executed);
    }

    /** @test */
    public function any_route_with_specific_methods_array(): void
    {
        $routes = HttpRouter::for()
            ->any('/test', [TestController::class, 'handle'], ['GET', 'POST']);

        $this->assertTrue($routes->hasRoute('GET', '/test'));
        $this->assertTrue($routes->hasRoute('POST', '/test'));
        $this->assertFalse($routes->hasRoute('PUT', '/test'));
        $this->assertFalse($routes->hasRoute('DELETE', '/test'));
    }

    /** @test */
    public function any_route_ignores_invalid_methods(): void
    {
        $routes = HttpRouter::for()
            ->any('/test', [TestController::class, 'handle'], ['GET', 'INVALID', 'POST']);

        $this->assertTrue($routes->hasRoute('GET', '/test'));
        $this->assertTrue($routes->hasRoute('POST', '/test'));
        $this->assertFalse($routes->hasRoute('INVALID', '/test'));
    }

    /** @test */
    public function any_route_with_lowercase_methods(): void
    {
        $routes = HttpRouter::for()
            ->any('/test', [TestController::class, 'handle'], ['get', 'post']);

        $this->assertTrue($routes->hasRoute('GET', '/test'));
        $this->assertTrue($routes->hasRoute('POST', '/test'));
    }

    /** @test */
    public function any_route_with_dynamic_parameters(): void
    {
        $received_params = null;

        $routes = HttpRouter::for('POST', '/users/123')
            ->any('/users/{id}', function (array $params) use (&$received_params) {
                $received_params = $params;
            });

        $routes->dispatch();

        $this->assertEquals(['id' => '123'], $received_params);
    }

    /** @test */
    public function any_route_with_constraints(): void
    {
        $received_params = null;

        $routes = HttpRouter::for('HEAD', '/users/456')
            ->any('/users/{id}', function (array $params) use (&$received_params) {
                $received_params = $params;
            })
            ->where('id', '\d+');

        $routes->dispatch();

        $this->assertEquals(['id' => '456'], $received_params);
    }

    /** @test */
    public function any_route_constraint_rejects_invalid_values(): void
    {
        $executed = false;

        $routes = HttpRouter::for('HEAD', '/users/abc')
            ->any('/users/{id}', function () use (&$executed) {
                $executed = true;
            })
            ->where('id', '\d+');

        $routes->dispatch();

        $this->assertFalse($executed);
    }

    /** @test */
    public function any_route_with_controller_array(): void
    {
        $routes = HttpRouter::for('POST', '/test')
            ->any('/test', [TestController::class, 'handle']);

        TestController::$executed = false;
        $routes->dispatch();

        $this->assertTrue(TestController::$executed);
    }

    /** @test */
    public function any_route_with_invokable_controller(): void
    {
        $routes = HttpRouter::for('DELETE', '/invoke')
            ->any('/invoke', InvokeableTestController::class);

        InvokeableTestController::$invoked = false;
        $routes->dispatch();

        $this->assertTrue(InvokeableTestController::$invoked);
    }

    /** @test */
    public function any_route_with_named_route(): void
    {
        $routes = HttpRouter::for('HEAD', '/users/789')
            ->any('/users/{id}', function () {
            })
            ->name('users.any');

        $route = $routes->matchRoute('HEAD', '/users/789');

        $this->assertEquals('users.any', $route->name);
    }

    /** @test */
    public function any_route_registers_all_methods_by_default(): void
    {
        $routes = HttpRouter::for()
            ->any('/test', [TestController::class, 'handle']);

        $this->assertTrue($routes->hasRoute('GET', '/test'));
        $this->assertTrue($routes->hasRoute('POST', '/test'));
        $this->assertTrue($routes->hasRoute('PUT', '/test'));
        $this->assertTrue($routes->hasRoute('PATCH', '/test'));
        $this->assertTrue($routes->hasRoute('DELETE', '/test'));
        $this->assertTrue($routes->hasRoute('OPTIONS', '/test'));
        $this->assertTrue($routes->hasRoute('HEAD', '/test'));
    }

    /** @test */
    public function any_route_with_additional_args(): void
    {
        $received_args = [];

        $routes = HttpRouter::for('PATCH', '/test', 'arg1', 'arg2')
            ->any('/test', function (array $params, ...$args) use (&$received_args) {
                $received_args = $args;
            });

        $routes->dispatch();

        $this->assertEquals(['arg1', 'arg2'], $received_args);
    }

    /** @test */
    public function any_route_returns_self_for_chaining(): void
    {
        $routes = HttpRouter::for();

        $result = $routes->any('/test', function () {
        });

        $this->assertSame($routes, $result);
    }

    /** @test */
    public function any_route_throws_exception_when_action_is_null(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Action cannot be null');

        HttpRouter::for()->any('/test', null);
    }

    /** @test */
    public function any_route_with_optional_parameters(): void
    {
        $received_params = null;

        $routes = HttpRouter::for('GET', '/users')
            ->any('/users/{id?}', function (array $params) use (&$received_params) {
                $received_params = $params;
            });

        $routes->dispatch();

        $this->assertEquals([], $received_params);
    }

    /** @test */
    public function any_route_can_be_compiled_and_cached(): void
    {
        $routes1 = HttpRouter::for()
            ->any('/test', [TestController::class, 'handle']);

        $compiled = $routes1->compile();

        $routes2 = HttpRouter::for('POST', '/test')->loadCompiled($compiled);

        TestController::$executed = false;
        $routes2->dispatch();

        $this->assertTrue(TestController::$executed);
    }

    /** @test */
    public function any_route_with_inline_constraints(): void
    {
        $received_params = null;

        $routes = HttpRouter::for('PATCH', '/users/999')
            ->any('/users/{id:\d+}', function (array $params) use (&$received_params) {
                $received_params = $params;
            });

        $routes->dispatch();

        $this->assertEquals(['id' => '999'], $received_params);
    }

    /** @test */
    public function any_route_with_empty_methods_array_registers_no_routes(): void
    {
        $routes = HttpRouter::for()
            ->any('/test', [TestController::class, 'handle'], []);

        $this->assertFalse($routes->hasRoute('GET', '/test'));
        $this->assertFalse($routes->hasRoute('POST', '/test'));
        $this->assertFalse($routes->hasRoute('PUT', '/test'));
    }

    /** @test */
    public function any_route_with_single_method_in_array(): void
    {
        $routes = HttpRouter::for()
            ->any('/test', [TestController::class, 'handle'], ['DELETE']);

        $this->assertTrue($routes->hasRoute('DELETE', '/test'));
        $this->assertFalse($routes->hasRoute('GET', '/test'));
        $this->assertFalse($routes->hasRoute('POST', '/test'));
    }
}

class TestController
{
    public static $executed = false;
    public static $received_params = null;

    public function handle(array $params): void
    {
        self::$executed = true;
    }

    public function showWithParams(array $params): void
    {
        self::$received_params = $params;
    }
}

class InvokeableTestController
{
    public static $invoked = false;

    public function __invoke(array $params): void
    {
        self::$invoked = true;
    }
}

class TestControllerWithArgs
{
    public static $received_args = [];

    public function handleWithArgs(array $params, ...$args): void
    {
        self::$received_args = $args;
    }
}

class InvokeableControllerWithArgs
{
    public static $received_args = [];

    public function __invoke(array $params, ...$args): void
    {
        self::$received_args = $args;
    }
}
