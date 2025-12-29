<?php

namespace Zerotoprod\HttpRouter;

use Closure;
use Exception;
use InvalidArgumentException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use RuntimeException;

/**
 * Static facade for route collection and dispatch.
 *
 * Provides convenient static methods for creating route collections
 * and dispatching requests.
 *
 * Performance optimizations:
 * - Method-based indexing for O(1) method filtering
 * - Build indices during registration, not during dispatch
 * - Reduced iterations through route matching
 *
 * Usage example:
 * <code>
 * $router = Router::for('GET', '/users/123')
 *     ->get('/users/{id}', [UserController::class, 'show'])
 *     ->where('id', '\d+')
 *     ->name('user.show')
 *     ->middleware(AuthMiddleware::class);
 *
 * $router->post('/users', [UserController::class, 'store'])
 *     ->middleware([ValidationMiddleware::class, AuthMiddleware::class]);
 *
 * $router->fallback(function() {
 *     http_response_code(404);
 *     echo 'Not Found';
 * });
 *
 * $router->dispatch();
 * </code>
 *
 * @link https://github.com/zero-to-prod/http-router
 */
class HttpRouter
{
    /** @var array */
    private $routes = [];

    /** @var array Indexed by method:path for O(1) static route lookup */
    private $static_index = [];

    /** @var array Indexed by method for quick method filtering */
    private $method_index = [];

    /** @var array Indexed by method:prefix for prefix-based lookup */
    private $prefix_index = [];

    /** @var array */
    private $pattern_index = [];

    /** @var array Indexed by route name for URL generation */
    private $named_routes = [];

    /** @var callable|null */
    private $not_found_handler = null;

    /** @var array Global middleware applied to all routes */
    private $global_middleware = [];

    /** @var Route|null Track last route for fluent chaining */
    private $last_route = null;

    /** @var int|null Index of last route in routes array */
    private $last_route_index = null;

    /** @var string HTTP method for dispatch */
    private $method;

    /** @var string Request URI for dispatch */
    private $uri;

    /** @var array Additional arguments passed to actions/middleware */
    private $args = [];

    /** @var bool Whether indices have been built */
    private $indices_built = false;

    /** @var array Stack of group attributes */
    private $group_stack = [];

    /** @var string|null Pending prefix for next group */
    private $pending_group_prefix = null;

    /** @var array|null Pending middleware for next group */
    private $pending_group_middleware = null;

    /** @var array|null Track middleware counts before last middleware() call */
    private $last_route_middleware_backup = null;

    /** @var string|null Path to cache file */
    private $cache_path = null;

    /** @var string Environment variable to check for caching */
    private $cache_env_var = 'APP_ENV';

    /** @var array Environments where caching is enabled */
    private $cache_envs = ['production'];

    /** @var bool Whether auto-cache is enabled */
    private $auto_cache_enabled = false;

    /** @var bool Whether routes were loaded from cache */
    private $cache_loaded = false;

    /** @var string Error message for methods that require a route to be defined first */
    private const NO_ROUTE_DEFINED = 'No route to configure. Call get/post/etc first.';

    /**
     * RESTful resource action definitions.
     * Format: [action_name => [http_method, uri_suffix, controller_method]]
     */
    private const RESTFUL_ACTIONS = [
        'index' => ['get', '', 'index'],
        'create' => ['get', '/create', 'create'],
        'store' => ['post', '', 'store'],
        'show' => ['get', '/{id}', 'show'],
        'edit' => ['get', '/{id}/edit', 'edit'],
        'update' => ['put', '/{id}', 'update'],
        'destroy' => ['delete', '/{id}', 'destroy'],
    ];

    /**
     * @link https://github.com/zero-to-prod/http-router
     */
    public function __construct()
    {
    }

    /**
     * Create a new router instance.
     *
     * @return self  New router instance
     *
     * @link https://github.com/zero-to-prod/http-router
     */
    public static function create(): self
    {
        return new self();
    }

    /**
     * Create a new router for the given request.
     *
     * Alias for create()->forRequest() for backward compatibility.
     *
     * @param  string  $method  HTTP method
     * @param  string  $uri     Request URI
     * @param  mixed   ...$args Additional arguments passed to actions/middleware
     *
     * @return self  New router instance
     *
     * @link https://github.com/zero-to-prod/http-router
     */
    public static function for(string $method = '', string $uri = '', ...$args): self
    {
        return self::create()->forRequest($method, $uri, ...$args);
    }

    /**
     * Configure the request to dispatch.
     *
     * @param  string  $method  HTTP method
     * @param  string  $uri     Request URI
     * @param  mixed   ...$args Additional arguments passed to actions/middleware
     *
     * @return self  Returns $this for method chaining
     *
     * @link https://github.com/zero-to-prod/http-router
     */
    public function forRequest(string $method, string $uri, ...$args): self
    {
        $this->method = $method;
        $this->uri = $uri;
        $this->args = $args;
        return $this;
    }

    /**
     * Define a GET route.
     *
     * @param  string  $uri     URI pattern
     * @param  mixed   $action  Action to execute
     *
     * @return self  Returns $this for method chaining
     * @link https://github.com/zero-to-prod/http-router
     */
    public function get(string $uri, $action = null): self
    {
        $this->addRoute('GET', $uri, $action);
        return $this;
    }

    /**
     * Define a POST route.
     *
     * @param  string  $uri     URI pattern
     * @param  mixed   $action  Action to execute
     *
     * @return self  Returns $this for method chaining
     * @link https://github.com/zero-to-prod/http-router
     */
    public function post(string $uri, $action = null): self
    {
        $this->addRoute('POST', $uri, $action);
        return $this;
    }

    /**
     * Define a PUT route.
     *
     * @param  string  $uri     URI pattern
     * @param  mixed   $action  Action to execute
     *
     * @return self  Returns $this for method chaining
     * @link https://github.com/zero-to-prod/http-router
     */
    public function put(string $uri, $action = null): self
    {
        $this->addRoute('PUT', $uri, $action);
        return $this;
    }

    /**
     * Define a PATCH route.
     *
     * @param  string  $uri     URI pattern
     * @param  mixed   $action  Action to execute
     *
     * @return self  Returns $this for method chaining
     * @link https://github.com/zero-to-prod/http-router
     */
    public function patch(string $uri, $action = null): self
    {
        $this->addRoute('PATCH', $uri, $action);
        return $this;
    }

    /**
     * Define a DELETE route.
     *
     * @param  string  $uri     URI pattern
     * @param  mixed   $action  Action to execute
     *
     * @return self  Returns $this for method chaining
     * @link https://github.com/zero-to-prod/http-router
     */
    public function delete(string $uri, $action = null): self
    {
        $this->addRoute('DELETE', $uri, $action);
        return $this;
    }

    /**
     * Define an OPTIONS route.
     *
     * @param  string  $uri     URI pattern
     * @param  mixed   $action  Action to execute
     *
     * @return self  Returns $this for method chaining
     * @link https://github.com/zero-to-prod/http-router
     */
    public function options(string $uri, $action = null): self
    {
        $this->addRoute('OPTIONS', $uri, $action);
        return $this;
    }

    /**
     * Define a HEAD route.
     *
     * @param  string  $uri     URI pattern
     * @param  mixed   $action  Action to execute
     *
     * @return self  Returns $this for method chaining
     * @link https://github.com/zero-to-prod/http-router
     */
    public function head(string $uri, $action = null): self
    {
        $this->addRoute('HEAD', $uri, $action);
        return $this;
    }

    /**
     * Define a route that responds to any HTTP method.
     *
     * @param  string      $uri      URI pattern
     * @param  mixed       $action   Action to execute
     * @param  array|null  $methods  Optional array of HTTP methods (defaults to all standard methods)
     *
     * @return self  Returns $this for method chaining
     * @link https://github.com/zero-to-prod/http-router
     */
    public function any(string $uri, $action = null, ?array $methods = null): self
    {
        $allowed_methods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD'];
        $target_methods = $methods ?? $allowed_methods;

        foreach ($target_methods as $method) {
            $method = strtoupper($method);
            if (in_array($method, $allowed_methods, true)) {
                $this->addRoute($method, $uri, $action);
            }
        }

        return $this;
    }

    /**
     * Register RESTful resource routes.
     *
     * Generates all 7 standard RESTful routes:
     * - GET    /{name}           -> index()
     * - GET    /{name}/create    -> create()
     * - POST   /{name}           -> store()
     * - GET    /{name}/{id}      -> show()
     * - GET    /{name}/{id}/edit -> edit()
     * - PUT    /{name}/{id}      -> update()
     * - DELETE /{name}/{id}      -> destroy()
     *
     * @param  string  $name        Resource name (e.g., 'users')
     * @param  string  $controller  Controller class name
     * @param  array   $options     Optional filters ('only' or 'except')
     *
     * @return self  Returns $this for method chaining
     * @link https://github.com/zero-to-prod/http-router
     */
    public function resource(string $name, string $controller, array $options = []): self
    {
        $name = trim($name, '/');
        $actions = $this->filterRestfulActions($options);

        foreach ($actions as $key => $action_data) {
            $this->registerRestfulAction($name, $controller, $action_data, $key);
        }

        return $this;
    }

    /**
     * Filter RESTful actions based on options.
     *
     * @param  array  $options  ['only' => [...]] or ['except' => [...]]
     *
     * @return array  Filtered actions
     * @internal
     */
    private function filterRestfulActions(array $options): array
    {
        $actions = self::RESTFUL_ACTIONS;

        if (isset($options['only'])) {
            return array_intersect_key($actions, array_flip((array) $options['only']));
        }

        if (isset($options['except'])) {
            return array_diff_key($actions, array_flip((array) $options['except']));
        }

        return $actions;
    }

    /**
     * Register a single RESTful action.
     *
     * @param  string  $resource_name
     * @param  string  $controller
     * @param  array   $action_data
     * @param  string  $action_key
     *
     * @return void
     * @internal
     */
    private function registerRestfulAction(
        string $resource_name,
        string $controller,
        array $action_data,
        string $action_key
    ): void {
        list($method, $suffix, $action) = $action_data;
        $uri = "/{$resource_name}{$suffix}";

        $this->$method($uri, [$controller, $action])
            ->name("{$resource_name}.{$action_key}");
    }

    /**
     * Set prefix for next group.
     *
     * @param  string  $prefix
     *
     * @return self  Returns $this for method chaining
     * @link https://github.com/zero-to-prod/http-router
     */
    public function prefix(string $prefix): self
    {
        $this->pending_group_prefix = trim($prefix, '/');
        return $this;
    }

    /**
     * Create a route group with shared attributes.
     *
     * @param  callable  $callback
     *
     * @return self  Returns $this for method chaining
     * @link https://github.com/zero-to-prod/http-router
     */
    public function group(callable $callback): self
    {
        // Build group attributes from pending state
        $attributes = [];

        if ($this->pending_group_prefix !== null) {
            $attributes['prefix'] = $this->pending_group_prefix;
            $this->pending_group_prefix = null;
        }

        if ($this->pending_group_middleware !== null) {
            $attributes['middleware'] = $this->pending_group_middleware;

            // Remove the middleware from last route if it was just added by middleware()->group() chain
            if ($this->last_route !== null && $this->last_route_middleware_backup !== null) {
                // Restore the route to its state before middleware() was called
                $this->last_route = new Route(
                    $this->last_route->method,
                    $this->last_route->pattern,
                    $this->last_route->regex,
                    $this->last_route->params,
                    $this->last_route->optional_params,
                    $this->last_route->constraints,
                    $this->last_route->action,
                    $this->last_route->name,
                    $this->last_route_middleware_backup
                );
                $this->replaceLastRoute($this->last_route);
            }

            $this->pending_group_middleware = null;
            $this->last_route_middleware_backup = null;
        }

        // Push to stack
        $this->group_stack[] = $attributes;

        // Execute callback with router instance
        $callback($this);

        // Pop from stack
        array_pop($this->group_stack);

        // Clear last route to prevent middleware() from applying to routes outside this group
        $this->last_route = null;
        $this->last_route_index = null;

        return $this;
    }

    /**
     * Enable automatic route caching based on environment.
     *
     * @param  string       $cache_path  Path to cache file
     * @param  string|null  $env_var     Environment variable to check (default: 'APP_ENV')
     * @param  array        $cache_envs  Environments where caching is enabled (default: ['production'])
     *
     * @return self  Returns $this for method chaining
     * @link https://github.com/zero-to-prod/http-router
     */
    public function autoCache(string $cache_path, ?string $env_var = null, ?array $cache_envs = null): self
    {
        $this->cache_path = $cache_path;
        if ($env_var !== null) {
            $this->cache_env_var = $env_var;
        }
        if ($cache_envs !== null) {
            $this->cache_envs = $cache_envs;
        }
        $this->auto_cache_enabled = true;

        return $this;
    }

    /**
     * Define fallback handler for 404 responses.
     *
     * @param  mixed  $action  Fallback action
     *
     * @return self  Returns $this for method chaining
     *
     * @throws InvalidArgumentException  If action is invalid
     * @link https://github.com/zero-to-prod/http-router
     */
    public function fallback($action): self
    {
        if ($action === null) {
            throw new InvalidArgumentException('Fallback action cannot be null');
        }

        $this->not_found_handler = $action;

        return $this;
    }

    /**
     * Normalize middleware to array format.
     *
     * @param  mixed  $middleware  Single middleware or array
     *
     * @return array  Normalized middleware array
     * @internal
     */
    private function normalizeMiddleware($middleware): array
    {
        return is_array($middleware) ? $middleware : [$middleware];
    }

    /**
     * Register global middleware for all routes.
     *
     * @param  mixed  $middleware  Single middleware (callable/class name) or array
     *
     * @return self  Returns $this for method chaining
     * @link https://github.com/zero-to-prod/http-router
     */
    public function globalMiddleware($middleware): self
    {
        $this->global_middleware = array_merge(
            $this->global_middleware,
            $this->normalizeMiddleware($middleware)
        );

        // Mark indices (and pipelines) as needing rebuild
        $this->indices_built = false;

        return $this;
    }

    /**
     * Add middleware to the last defined route or set pending group middleware.
     *
     * @param  mixed  $middleware  Single middleware (callable/class name) or array
     *
     * @return self  Returns $this for method chaining
     *
     * @throws RuntimeException  If no route has been defined yet
     * @link https://github.com/zero-to-prod/http-router
     */
    public function middleware($middleware): self
    {
        // Always store as pending for potential group() call
        $this->pending_group_middleware = array_merge(
            $this->pending_group_middleware ?? [],
            $this->normalizeMiddleware($middleware)
        );

        // Also apply to last route if one exists (will be undone if group() is called next)
        if ($this->last_route !== null) {
            // Backup the current middleware state before adding new middleware
            $this->last_route_middleware_backup = $this->last_route->middleware;
            $this->last_route = $this->last_route->withMiddleware($middleware);
            $this->replaceLastRoute($this->last_route);
        }

        return $this;
    }

    /**
     * Dispatch a request with triple-level optimization.
     *
     * Executes the matched route action via side effects (echo, output buffering, etc.).
     * Actions do not return values; they produce output directly. The return value
     * indicates whether a route was matched and executed.
     *
     * Performance improvements:
     * - O(1) static route lookup (hash map for exact matches)
     * - O(1) prefix-based filtering (check only routes with matching prefix)
     * - Single regex call per route (matchAndExtract eliminates duplicate preg_match)
     * - O(1) method filtering as fallback
     *
     * @return bool  True if route or fallback executed, false if no match
     * @link https://github.com/zero-to-prod/http-router
     */
    public function dispatch(): bool
    {
        // If auto-cache is enabled, try loading from cache first
        if ($this->auto_cache_enabled && $this->shouldUseCache()) {
            $this->loadCacheIfExists();
        }

        // Build indices lazily on first dispatch
        if (!$this->indices_built) {
            $this->buildIndices();
        }

        $params = [];
        $route = $this->findRoute($this->method, $this->uri, $params);

        if ($route !== null) {
            $this->executeWithMiddleware($route, $params);
            $this->finalizeCacheIfNeeded();
            return true;
        }

        if ($this->not_found_handler !== null) {
            $this->executeWithMiddleware(null, []);
            $this->finalizeCacheIfNeeded();
            return true;
        }

        $this->finalizeCacheIfNeeded();
        return false;
    }

    /**
     * Get all registered routes.
     *
     * @return array  Array of Route objects
     * @return array  Array of Route objects
     * @internal This method is primarily for testing and debugging.
     *           Production code should use dispatch() instead.
     *
     * @link     https://github.com/zero-to-prod/web-framework
     * @link https://github.com/zero-to-prod/http-router
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }

    /**
     * Build index key from method and path.
     *
     * @param  string  $method  HTTP method
     * @param  string  $path    Route path
     *
     * @return string  Index key (method:path)
     * @internal
     */
    private function buildIndexKey(string $method, string $path): string
    {
        return $method.':'.$path;
    }

    /**
     * Find matching route using triple-level optimization.
     *
     * @param  string  $method  HTTP method
     * @param  string  $uri     Request URI
     * @param  array   $params  Output parameter for extracted route params
     *
     * @return Route|null  Matched route or null
     * @internal
     * @link https://github.com/zero-to-prod/http-router
     */
    private function findRoute(string $method, string $uri, array &$params = []): ?Route
    {
        if (!$this->indices_built) {
            $this->buildIndices();
        }

        $path = $this->stripQueryString($uri);
        $key = $this->buildIndexKey($method, $path);

        // Level 1: O(1) static lookup
        if (isset($this->static_index[$key])) {
            $params = [];
            return $this->static_index[$key];
        }

        // Level 2: O(1) prefix-based lookup
        $prefix = $this->extractPrefix($path);
        $prefix_key = $this->buildIndexKey($method, $prefix);

        if (isset($this->prefix_index[$prefix_key])) {
            foreach ($this->prefix_index[$prefix_key] as $route) {
                if ($route->matchAndExtract($path, $params)) {
                    return $route;
                }
            }
        }

        // Level 3: Method-based fallback
        if (isset($this->method_index[$method])) {
            foreach ($this->method_index[$method] as $route) {
                if ($prefix !== '/' && strpos($route->pattern, $prefix) === 0) {
                    continue;
                }
                if ($route->matchAndExtract($path, $params)) {
                    return $route;
                }
            }
        }

        return null;
    }

    /**
     * Find a matching route for method and URI.
     *
     * Uses same triple-level optimization as dispatch().
     *
     * @param  string  $method  HTTP method
     * @param  string  $uri     Request URI
     *
     * @return Route|null  Matched route or null
     * @internal This method is primarily for testing and debugging.
     *           Production code should use dispatch() instead.
     *
     * @link     https://github.com/zero-to-prod/web-framework
     * @link https://github.com/zero-to-prod/http-router
     */
    public function matchRoute(string $method, string $uri): ?Route
    {
        $params = [];
        return $this->findRoute($method, $uri, $params);
    }

    /**
     * Check if a route exists.
     *
     * @param  string  $method   HTTP method
     * @param  string  $pattern  Route pattern
     *
     * @return bool  True if route exists
     * @internal This method is primarily for testing and duplicate detection.
     *           Not typically needed in production code.
     *
     * @link     https://github.com/zero-to-prod/web-framework
     * @link https://github.com/zero-to-prod/http-router
     */
    public function hasRoute(string $method, string $pattern): bool
    {
        return isset($this->pattern_index[$this->buildIndexKey($method, $pattern)]);
    }

    /**
     * Check if all routes are cacheable.
     *
     * @return bool  True if all routes can be cached
     * @link https://github.com/zero-to-prod/http-router
     */
    public function isCacheable(): bool
    {
        return !array_filter($this->global_middleware, static function ($mw) {
                return $mw instanceof Closure;
            })
            && !array_filter($this->routes, static function ($route) {
                return !$route->isCacheable();
            });
    }

    /**
     * Compile routes for caching.
     *
     * @return string  Serialized route data
     *
     * @throws RuntimeException  If routes contain closures
     * @link https://github.com/zero-to-prod/http-router
     */
    public function compile(): string
    {
        if (!$this->isCacheable()) {
            throw new RuntimeException(
                'Cannot compile routes with closures for caching. '.
                'Closures cannot be serialized in PHP. '.
                'Use controller arrays [Controller::class, \'method\'], '.
                'invokeable classes Controller::class, or '.
                'middleware class names instead of closures.'
            );
        }

        return serialize([
            'routes' => array_map(static function ($route) {
                return $route->toArray();
            }, $this->routes),
            'global_middleware' => $this->global_middleware,
            'named_routes' => array_map(static function ($route) {
                return $route->toArray();
            }, $this->named_routes)
        ]);
    }

    /**
     * Load compiled routes from cache.
     *
     * @param  string  $data  Serialized route data
     *
     * @return self  Returns $this for method chaining
     * @link https://github.com/zero-to-prod/http-router
     */
    public function loadCompiled(string $data): self
    {
        $compiled = unserialize($data, ['allowed_classes' => true]);

        $routes = isset($compiled[0]) ? $compiled : ($compiled['routes'] ?? []);
        $this->global_middleware = $compiled['global_middleware'] ?? [];

        foreach ($routes as $routeData) {
            $this->storeRoute(Route::fromArray($routeData));
        }

        // Load named routes
        if (isset($compiled['named_routes'])) {
            $this->named_routes = array_map(static function ($routeData) {
                return Route::fromArray($routeData);
            }, $compiled['named_routes']);
        }

        return $this;
    }

    /**
     * Add constraint(s) to the last defined route.
     *
     * @param  string|array  $param    Parameter name or array of constraints
     * @param  string|null   $pattern  Regex pattern (if $param is string)
     *
     * @return self  Returns $this for method chaining
     *
     * @throws RuntimeException  If no route has been defined yet
     * @throws InvalidArgumentException  If constraint is invalid
     * @link https://github.com/zero-to-prod/http-router
     */
    public function where($param, ?string $pattern = null): self
    {
        if ($this->last_route === null) {
            throw new RuntimeException(self::NO_ROUTE_DEFINED);
        }

        // Validate constraints
        if (is_array($param)) {
            foreach ($param as $key => $value) {
                $this->validateConstraint($key, $value);
            }
        } else {
            $this->validateConstraint($param, $pattern);
        }

        $this->last_route = $this->last_route->where($param, $pattern);
        $this->replaceLastRoute($this->last_route);

        return $this;
    }

    /**
     * Build constraint regex pattern.
     *
     * @param  string  $constraint
     *
     * @return string  Compiled regex pattern
     * @internal
     */
    private function buildConstraintRegex(string $constraint): string
    {
        return '#^'.$constraint.'$#';
    }

    /**
     * Validate constraint pattern.
     *
     * @param  string  $param    Parameter name
     * @param  string  $pattern  Regex pattern
     *
     * @throws InvalidArgumentException  If invalid
     * @internal
     */
    private function validateConstraint(string $param, string $pattern): void
    {
        if (@preg_match($this->buildConstraintRegex($pattern), '') === false) {
            throw new InvalidArgumentException("Invalid regex pattern for constraint '$param': $pattern");
        }
    }

    /**
     * Name the last defined route.
     *
     * @param  string  $name  Route name
     *
     * @return self  Returns $this for method chaining
     *
     * @throws RuntimeException  If no route has been defined yet
     * @link https://github.com/zero-to-prod/http-router
     */
    public function name(string $name): self
    {
        if ($this->last_route === null) {
            throw new RuntimeException(self::NO_ROUTE_DEFINED);
        }

        $this->last_route = $this->last_route->withName($name);
        $this->replaceLastRoute($this->last_route);

        // Register in named routes index
        $this->named_routes[$name] = $this->last_route;

        return $this;
    }

    /**
     * Generate URL from named route.
     *
     * @param  string  $name    Route name
     * @param  array   $params  Route parameters
     *
     * @return string
     *
     * @throws RuntimeException  If route not found or parameters invalid
     * @link https://github.com/zero-to-prod/http-router
     */
    public function route(string $name, array $params = []): string
    {
        if (!isset($this->named_routes[$name])) {
            throw new RuntimeException("Route not found: {$name}");
        }

        return $this->generateUrl($this->named_routes[$name], $params);
    }

    /**
     * Validate parameter against constraint.
     *
     * @param  Route   $route
     * @param  string  $param
     * @param  mixed   $value
     *
     * @return void
     *
     * @throws RuntimeException  If validation fails
     * @internal
     */
    private function validateUrlParameter(Route $route, string $param, $value): void
    {
        if (!isset($route->constraints[$param])) {
            return;
        }

        $pattern = $this->buildConstraintRegex($route->constraints[$param]);

        if (!preg_match($pattern, (string) $value)) {
            throw new RuntimeException(
                "Parameter '{$param}' value '{$value}' does not match constraint"
            );
        }
    }

    /**
     * Build parameter placeholder variations.
     *
     * @param  string  $param  Parameter name
     *
     * @return array  Placeholder variations
     * @internal
     */
    private function buildParamPlaceholders(string $param): array
    {
        return [
            'required' => '{'.$param.'}',
            'optional' => '{'.$param.'?}',
            'optional_path' => '/{'.$param.'?}'
        ];
    }

    /**
     * Build parameter replacements for URL generation.
     *
     * @param  Route  $route
     * @param  array  $params
     *
     * @return array  Map of placeholder => value
     *
     * @throws RuntimeException  If required parameter missing
     * @internal
     */
    private function buildUrlReplacements(Route $route, array $params): array
    {
        $replacements = [];

        foreach ($route->params as $param) {
            $is_optional = in_array($param, $route->optional_params, true);
            $placeholders = $this->buildParamPlaceholders($param);

            // Check if parameter provided
            if (!isset($params[$param])) {
                if (!$is_optional) {
                    throw new RuntimeException("Missing required parameter: {$param}");
                }
                // Mark optional for removal
                $replacements[$placeholders['optional_path']] = '';
                continue;
            }

            $value = $params[$param];
            $this->validateUrlParameter($route, $param, $value);

            // Add both syntaxes
            $replacements[$placeholders['required']] = (string) $value;
            $replacements[$placeholders['optional']] = (string) $value;
        }

        return $replacements;
    }

    /**
     * Generate URL from route pattern and parameters.
     *
     * @param  Route  $route
     * @param  array  $params
     *
     * @return string
     *
     * @throws RuntimeException  If required parameter missing or constraint validation fails
     * @internal
     */
    private function generateUrl(Route $route, array $params): string
    {
        $replacements = $this->buildUrlReplacements($route, $params);
        return str_replace(array_keys($replacements), array_values($replacements), $route->pattern);
    }

    /**
     * Create and store a route.
     *
     * @param  string  $method  HTTP method
     * @param  string  $uri     URI pattern
     * @param  mixed   $action  Action to execute
     *
     * @return void
     *
     * @throws InvalidArgumentException  If action is invalid
     * @internal
     */
    private function addRoute(string $method, string $uri, $action): void
    {
        if ($action === null) {
            throw new InvalidArgumentException("Action cannot be null for route: $method $uri");
        }

        // Apply group prefix
        $uri = $this->applyGroupPrefix($uri);

        $compiled = RouteCompiler::compile($uri);

        $route = new Route(
            $method,
            $uri,
            $compiled['regex'],
            $compiled['params'],
            $compiled['optional_params'],
            $compiled['constraints'],
            $action
        );

        // Apply group middleware
        $route = $this->applyGroupMiddleware($route);

        $this->storeRoute($route);
        $this->last_route = $route;
    }

    /**
     * Extract current group state from stack.
     *
     * @return array  ['prefixes' => string[], 'middleware' => array]
     * @internal
     */
    private function extractGroupState(): array
    {
        if (empty($this->group_stack)) {
            return ['prefixes' => [], 'middleware' => []];
        }

        $prefixes = [];
        $middleware = [];

        foreach ($this->group_stack as $group) {
            if (isset($group['prefix'])) {
                $prefixes[] = $group['prefix'];
            }
            if (isset($group['middleware'])) {
                $middleware = array_merge($middleware, (array) $group['middleware']);
            }
        }

        return ['prefixes' => $prefixes, 'middleware' => $middleware];
    }

    /**
     * Apply group prefix to pattern.
     *
     * @param  string  $pattern
     *
     * @return string
     * @internal
     */
    private function applyGroupPrefix(string $pattern): string
    {
        $state = $this->extractGroupState();

        if (empty($state['prefixes'])) {
            return $pattern;
        }

        $prefix = '/'.implode('/', $state['prefixes']);
        $pattern = '/'.trim($pattern, '/');

        return $prefix.$pattern;
    }

    /**
     * Apply group middleware to route.
     *
     * @param  Route  $route
     *
     * @return Route
     * @internal
     */
    private function applyGroupMiddleware(Route $route): Route
    {
        $state = $this->extractGroupState();

        if (empty($state['middleware'])) {
            return $route;
        }

        return $route->withMiddleware($state['middleware']);
    }

    /**
     * Store a route and track its index.
     *
     * Indices are built lazily before first dispatch for better performance
     * when defining many routes.
     *
     * @param  Route  $route  Route to store
     * @internal
     */
    private function storeRoute(Route $route): void
    {
        $this->last_route_index = count($this->routes);
        $this->routes[] = $route;
        $this->pattern_index[$this->buildIndexKey($route->method, $route->pattern)] = $route;
    }

    /**
     * Append value to indexed array, initializing if needed.
     *
     * @param  array   $index
     * @param  string  $key
     * @param  mixed   $value
     *
     * @return void
     * @internal
     */
    private function indexAppend(array &$index, string $key, $value): void
    {
        if (!isset($index[$key])) {
            $index[$key] = [];
        }
        $index[$key][] = $value;
    }

    /**
     * Build all indices from stored routes.
     *
     * Performance optimization: Build indices once before dispatch
     * rather than updating them after each route modification.
     *
     * @return void
     * @internal
     */
    private function buildIndices(): void
    {
        // Clear existing indices
        $this->static_index = [];
        $this->method_index = [];
        $this->prefix_index = [];

        // Build indices from routes array
        foreach ($this->routes as $route) {
            // Build middleware pipeline (once)
            $route->middleware_pipeline = $this->buildMiddlewarePipeline($route);

            // Build static index for routes without parameters (O(1) lookup)
            if (empty($route->params)) {
                $this->static_index[$this->buildIndexKey($route->method, $route->pattern)] = $route;
            } else {
                // Build method index for dynamic routes (group by HTTP method)
                $this->indexAppend($this->method_index, $route->method, $route);

                // Build prefix index for faster dynamic route matching
                $prefix = $this->extractPrefix($route->pattern);
                if ($prefix !== '/') {
                    $prefix_key = $this->buildIndexKey($route->method, $prefix);
                    $this->indexAppend($this->prefix_index, $prefix_key, $route);
                }
            }
        }

        $this->indices_built = true;
    }

    /**
     * Replace the last stored route with an updated version.
     *
     * Performance optimization: This method only updates the routes array and pattern
     * index immediately. The static_index, method_index, and prefix_index are marked
     * as stale (indices_built = false) and will be rebuilt lazily on the next dispatch()
     * call. This approach avoids expensive index rebuilding during route definition when
     * chaining multiple configuration methods like where(), name(), and middleware().
     *
     * @param  Route  $route  Updated route to replace
     * @internal
     */
    private function replaceLastRoute(Route $route): void
    {
        // Replace in routes array using tracked index (O(1) instead of O(n))
        if ($this->last_route_index !== null) {
            $this->routes[$this->last_route_index] = $route;
        }

        // Update pattern index
        $this->pattern_index[$this->buildIndexKey($route->method, $route->pattern)] = $route;

        // Mark indices as needing rebuild
        $this->indices_built = false;
    }

    /**
     * Strip query string from URI.
     *
     * @param  string  $uri  Request URI
     *
     * @return string  Path without query string
     * @internal
     */
    private function stripQueryString(string $uri): string
    {
        $path = strtok($uri, '?');

        return $path !== false ? $path : '';
    }

    /**
     * Extract static prefix from a route pattern or path.
     *
     * Returns everything before the first parameter placeholder (normalized without trailing slash).
     * Examples:
     * - /users/{id}/posts -> /users
     * - /api/v1/{resource} -> /api/v1
     * - /users -> /users
     * - /{id} -> / (root has no prefix)
     *
     * @param  string  $pattern  Route pattern or path
     *
     * @return string  Static prefix (no trailing slash except for root)
     * @internal
     */
    private function extractPrefix(string $pattern): string
    {
        $pos = strpos($pattern, '{');

        // No parameters - extract prefix from last segment
        if ($pos === false) {
            return $this->extractPrefixFromStatic($pattern);
        }

        // Parameter at root - no meaningful prefix
        if ($pos === 0 || $pos === 1) {
            return '/';
        }

        // Extract prefix before first parameter
        return $this->extractPrefixBeforeParam($pattern, $pos);
    }

    /**
     * Extract text up to last slash, returning '/' if none found.
     *
     * @param  string  $text
     *
     * @return string
     * @internal
     */
    private function extractUntilLastSlash(string $text): string
    {
        $last_slash = strrpos($text, '/');

        if ($last_slash === false || $last_slash === 0) {
            return '/';
        }

        $result = substr($text, 0, $last_slash);
        return $result === '' ? '/' : $result;
    }

    /**
     * Extract prefix from static route (no parameters).
     *
     * @param  string  $pattern
     *
     * @return string
     * @internal
     */
    private function extractPrefixFromStatic(string $pattern): string
    {
        return $this->extractUntilLastSlash($pattern);
    }

    /**
     * Extract prefix before parameter placeholder.
     *
     * @param  string  $pattern
     * @param  int     $param_pos  Position of first '{'
     *
     * @return string
     * @internal
     */
    private function extractPrefixBeforeParam(string $pattern, int $param_pos): string
    {
        $prefix = substr($pattern, 0, $param_pos);
        return $this->extractUntilLastSlash($prefix);
    }

    /**
     * Execute array-based controller action [Class, 'method'].
     *
     * @param  array  $action
     * @param  array  $params
     *
     * @return void
     *
     * @throws InvalidArgumentException
     * @internal
     */
    private function executeArrayAction(array $action, array $params): void
    {
        if (!isset($action[0], $action[1]) || isset($action[2])) {
            throw new InvalidArgumentException(
                'Controller array must have exactly 2 elements: [Class, \'method\']'
            );
        }

        (new $action[0]())->{$action[1]}($params, ...$this->args);
    }

    /**
     * Execute invokable class action.
     *
     * @param  string  $action
     * @param  array   $params
     *
     * @return void
     * @internal
     */
    private function executeInvokableAction(string $action, array $params): void
    {
        (new $action())($params, ...$this->args);
    }

    /**
     * Execute callable action.
     *
     * @param  callable  $action
     * @param  array     $params
     *
     * @return void
     * @internal
     */
    private function executeCallableAction(callable $action, array $params): void
    {
        $action($params, ...$this->args);
    }

    /**
     * Execute an action with parameters.
     *
     * Supports three action types:
     * 1. Controller array: [ClassName::class, 'methodName']
     * 2. Invokable class: ClassName::class (must have __invoke method)
     * 3. Closure: function($params) { ... }
     *
     * @param  mixed  $action  Action to execute
     * @param  array  $params  Route parameters extracted from URI
     *
     * @throws InvalidArgumentException  If action type is invalid
     * @internal
     * @link https://github.com/zero-to-prod/http-router
     */
    private function execute($action, array $params): void
    {
        if (is_array($action)) {
            $this->executeArrayAction($action, $params);
            return;
        }

        if (is_string($action) && method_exists($action, '__invoke')) {
            $this->executeInvokableAction($action, $params);
            return;
        }

        if (!is_callable($action)) {
            throw new InvalidArgumentException(
                'Action must be callable (closure), controller array [Class::class, \'method\'], or invokable class'
            );
        }

        $this->executeCallableAction($action, $params);
    }

    /**
     * Execute route action with middleware pipeline.
     *
     * @param  Route|null  $route   Matched route (null for fallback)
     * @param  array       $params  Route parameters
     *
     * @return void
     * @internal
     * @link https://github.com/zero-to-prod/http-router
     */
    private function executeWithMiddleware(?Route $route, array $params): void
    {
        // Use pre-built pipeline if available
        if ($route && $route->middleware_pipeline !== null) {
            ($route->middleware_pipeline)($params, $this->args);
            return;
        }

        // Handle fallback with global middleware (not a route, so no pre-built pipeline)
        if ($route === null && !empty($this->global_middleware)) {
            $pipeline = function () use ($params) {
                $this->execute($this->not_found_handler, $params);
            };

            foreach (array_reverse($this->global_middleware) as $mw) {
                $pipeline = $this->isPsr15Middleware($mw)
                    ? $this->wrapPsr15($mw, $pipeline)
                    : $this->wrapVariadic($mw, $pipeline);
            }

            $pipeline();
            return;
        }

        // No middleware - direct execution
        $this->execute($route->action ?? $this->not_found_handler, $params);
    }

    /**
     * Check if middleware is PSR-15 (otherwise treat as variadic legacy).
     *
     * @param  mixed  $middleware
     *
     * @return bool
     * @internal
     */
    private function isPsr15Middleware($middleware): bool
    {
        if (is_string($middleware) && class_exists($middleware)) {
            return in_array('Psr\\Http\\Server\\MiddlewareInterface', class_implements($middleware) ?: []);
        }

        if (is_object($middleware)) {
            return $middleware instanceof MiddlewareInterface;
        }

        return false;
    }

    /**
     * Create PSR-7 server request from globals.
     *
     * @return ServerRequestInterface
     * @internal
     */
    private function createPsrRequest()
    {
        $psr17Factory = new Psr17Factory();
        $creator = new ServerRequestCreator(
            $psr17Factory,
            $psr17Factory,
            $psr17Factory,
            $psr17Factory
        );
        return $creator->fromGlobals();
    }

    /**
     * Send PSR-7 response to output.
     *
     * @param  ResponseInterface  $response
     *
     * @return void
     * @internal
     */
    private function sendPsrResponse($response): void
    {
        http_response_code($response->getStatusCode());
        foreach ($response->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                header("$name: $value", false);
            }
        }
        echo $response->getBody();
    }

    /**
     * Wrap PSR-15 middleware (handles both legacy and compiled).
     *
     * @param  mixed     $middleware
     * @param  callable  $next
     * @param  bool      $compiled  Whether this is for compiled pipeline
     *
     * @return callable
     * @internal
     */
    private function wrapPsr15($middleware, callable $next, bool $compiled = false): callable
    {
        return function (...$args) use ($middleware, $next, $compiled) {
            $request = $this->createPsrRequest();

            // Instantiate if class name
            $middleware = is_string($middleware) ? new $middleware() : $middleware;

            // Create handler
            $psr17Factory = new Psr17Factory();
            $handler = new RequestHandler(
                function ($request) use ($next, $psr17Factory, $args, $compiled) {
                    ob_start();

                    // Pass args differently for compiled vs legacy
                    $compiled ? $next(...$args) : $next();

                    $body = ob_get_clean();
                    return $psr17Factory->createResponse(200)->withBody(
                        $psr17Factory->createStream($body)
                    );
                }
            );

            $response = $middleware->process($request, $handler);
            $this->sendPsrResponse($response);
        };
    }

    /**
     * Wrap variadic middleware (handles both legacy and compiled).
     *
     * @param  mixed     $middleware
     * @param  callable  $next
     * @param  bool      $compiled  Whether this is for compiled pipeline
     *
     * @return callable
     * @internal
     */
    private function wrapVariadic($middleware, callable $next, bool $compiled = false): callable
    {
        return function (...$args) use ($middleware, $next, $compiled) {
            $middleware = is_string($middleware) ? new $middleware() : $middleware;

            if ($compiled) {
                // Compiled version: $args contains [$params, $routerArgs]
                $middleware(function () use ($next, $args) {
                    $next(...$args);
                }, ...$args[1]); // Pass router args to middleware
            } else {
                // Legacy version: called without params
                $middleware($next, ...$this->args);
            }
        };
    }

    /**
     * Build middleware pipeline once and cache it.
     *
     * @param  Route  $route
     *
     * @return callable|null Compiled middleware pipeline or null if no middleware
     * @internal
     */
    private function buildMiddlewarePipeline(Route $route): ?callable
    {
        $middleware = array_merge($this->global_middleware, $route->middleware ?? []);

        if (empty($middleware)) {
            return null;
        }

        // Pre-analyze middleware types (once)
        $compiled_middleware = [];
        foreach ($middleware as $mw) {
            $compiled_middleware[] = [
                'middleware' => $mw,
                'is_psr15' => $this->isPsr15Middleware($mw)
            ];
        }

        // Build the pipeline (closures capture compiled info)
        $pipeline = function ($params, $args) use ($route) {
            $this->execute($route->action, $params);
        };

        foreach (array_reverse($compiled_middleware) as $compiled) {
            $pipeline = $compiled['is_psr15']
                ? $this->wrapPsr15($compiled['middleware'], $pipeline, true)
                : $this->wrapVariadic($compiled['middleware'], $pipeline, true);
        }

        return $pipeline;
    }

    /**
     * Check if we should use cached routes.
     *
     * @return bool
     * @internal
     */
    private function shouldUseCache(): bool
    {
        if (empty($this->cache_path)) {
            return false;
        }

        $env = getenv($this->cache_env_var);
        if ($env === false) {
            $env = $_ENV[$this->cache_env_var] ?? 'local';
        }

        return in_array($env, $this->cache_envs, true);
    }

    /**
     * Load routes from cache if file exists.
     *
     * @return void
     * @internal
     */
    private function loadCacheIfExists(): void
    {
        if (!file_exists($this->cache_path)) {
            return;
        }

        try {
            $compiled = file_get_contents($this->cache_path);
            $this->loadCompiled($compiled);
            $this->cache_loaded = true;
        } catch (Exception $e) {
            // Silently fail - will register routes normally
            error_log('Failed to load route cache: '.$e->getMessage());
        }
    }

    /**
     * Check if we should write cache after dispatch.
     *
     * @return bool
     * @internal
     */
    private function shouldWriteCache(): bool
    {
        $should_write = !$this->cache_loaded
            && !empty($this->cache_path)
            && $this->shouldUseCache()
            && $this->isCacheable();

        if (!$should_write && !$this->isCacheable() && !empty($this->cache_path)) {
            error_log('Warning: Routes contain closures and cannot be cached');
        }

        return $should_write;
    }

    /**
     * Finalize cache writing after dispatch if needed.
     *
     * @return void
     * @internal
     */
    private function finalizeCacheIfNeeded(): void
    {
        if ($this->auto_cache_enabled && $this->shouldWriteCache()) {
            $this->writeCache();
        }
    }

    /**
     * Write routes to cache file.
     *
     * @return void
     * @internal
     */
    private function writeCache(): void
    {
        try {
            // Ensure cache directory exists
            $dir = dirname($this->cache_path);
            if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
                throw new RuntimeException(sprintf('Directory "%s" was not created', $dir));
            }

            // Compile and write
            $compiled = $this->compile();
            file_put_contents($this->cache_path, $compiled);
        } catch (Exception $e) {
            error_log('Failed to write route cache: '.$e->getMessage());
        }
    }

}
