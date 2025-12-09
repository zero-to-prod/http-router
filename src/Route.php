<?php

namespace Zerotoprod\HttpRouter;

use Closure;

/**
 * Represents a single HTTP route.
 *
 * Value object containing all route metadata including method, pattern,
 * compiled regex, parameters, constraints, and action.
 *
 * @link https://github.com/zero-to-prod/http-router
 */
class Route
{
    /**
     * @var string
     * @link https://github.com/zero-to-prod/http-router
     */
    public $method;

    /**
     * @var string
     * @link https://github.com/zero-to-prod/http-router
     */
    public $pattern;

    /**
     * @var string
     * @link https://github.com/zero-to-prod/http-router
     */
    public $regex;

    /**
     * @var array
     * @link https://github.com/zero-to-prod/http-router
     */
    public $params;

    /**
     * @var array
     * @link https://github.com/zero-to-prod/http-router
     */
    public $optional_params;

    /**
     * @var array
     * @link https://github.com/zero-to-prod/http-router
     */
    public $constraints;

    /**
     * @var mixed
     * @link https://github.com/zero-to-prod/http-router
     */
    public $action;

    /**
     * @var string|null
     * @link https://github.com/zero-to-prod/http-router
     */
    public $name;

    /**
     * @var array
     * @link https://github.com/zero-to-prod/http-router
     */
    public $middleware = [];

    /**
     * @var callable|null Cached compiled middleware pipeline
     * @link https://github.com/zero-to-prod/http-router
     */
    public $middleware_pipeline = null;

    /**
     * @link https://github.com/zero-to-prod/http-router
     */
    public function __construct(
        string $method,
        string $pattern,
        string $regex,
        array $params,
        array $optional_params,
        array $constraints,
        $action,
        $name = null,
        array $middleware = []
    ) {
        $this->method = $method;
        $this->pattern = $pattern;
        $this->regex = $regex;
        $this->params = $params;
        $this->optional_params = $optional_params;
        $this->constraints = $constraints;
        $this->action = $action;
        $this->name = $name;
        $this->middleware = $middleware;
    }

    /**
     * Check if this route matches the given URI.
     *
     * @param  string  $uri  The URI to match against
     *
     * @return bool  True if route matches
     * @link https://github.com/zero-to-prod/http-router
     */
    public function matches(string $uri): bool
    {
        return preg_match($this->regex, $uri) === 1;
    }

    /**
     * Extract parameters from the given URI.
     *
     * @param  string  $uri  The URI to extract parameters from
     *
     * @return array  Associative array of parameter names to values
     * @link https://github.com/zero-to-prod/http-router
     */
    public function extractParams(string $uri): array
    {
        if (!preg_match($this->regex, $uri, $matches)) {
            return [];
        }

        $captured_values = array_slice($matches, 1);
        $params = [];

        foreach ($this->params as $index => $param_name) {
            $value = $captured_values[$index] ?? '';

            if ($value !== '' || !in_array($param_name, $this->optional_params, true)) {
                $params[$param_name] = $value;
            }
        }

        return $params;
    }

    /**
     * Match and extract parameters in one regex call.
     *
     * Performance optimization: Combines matches() and extractParams()
     * to avoid calling preg_match twice.
     *
     * @param  string  $uri  The URI to match against
     * @param  array   $params  Output parameter for extracted values
     *
     * @return bool  True if route matches
     * @link https://github.com/zero-to-prod/http-router
     */
    public function matchAndExtract(string $uri, &$params): bool
    {
        if (!preg_match($this->regex, $uri, $matches)) {
            return false;
        }

        $captured_values = array_slice($matches, 1);
        $params = [];

        foreach ($this->params as $index => $param_name) {
            $value = $captured_values[$index] ?? '';

            if ($value !== '' || !in_array($param_name, $this->optional_params, true)) {
                $params[$param_name] = $value;
            }
        }

        return true;
    }

    /**
     * Create a new route with an additional constraint.
     *
     * Alias for withConstraint() for fluent API consistency.
     *
     * @param  string|array  $param    Parameter name or array of constraints
     * @param  string|null   $pattern  Regex pattern (if $param is string)
     *
     * @return Route  New route instance with updated constraint
     * @link https://github.com/zero-to-prod/http-router
     */
    public function where($param, $pattern = null): Route
    {
        return $this->withConstraint($param, $pattern);
    }

    /**
     * Create a new route with an additional constraint.
     *
     * @param  string|array  $param    Parameter name or array of constraints
     * @param  string|null   $pattern  Regex pattern (if $param is string)
     *
     * @return Route  New route instance with updated constraint
     * @link https://github.com/zero-to-prod/http-router
     */
    public function withConstraint($param, $pattern = null): Route
    {
        if (is_array($param)) {
            return $this->withConstraints($param);
        }

        $new_constraints = array_merge($this->constraints, [$param => $pattern]);
        $compiled = RouteCompiler::compile($this->pattern, $new_constraints);

        return new self(
            $this->method,
            $this->pattern,
            $compiled['regex'],
            $this->params,
            $this->optional_params,
            $new_constraints,
            $this->action,
            $this->name,
            $this->middleware
        );
    }

    /**
     * Create a new route with multiple constraints.
     *
     * @param  array  $constraints  Array of param => pattern
     *
     * @return Route  New route instance with updated constraints
     * @link https://github.com/zero-to-prod/http-router
     */
    public function withConstraints(array $constraints): Route
    {
        $new_constraints = array_merge($this->constraints, $constraints);
        $compiled = RouteCompiler::compile($this->pattern, $new_constraints);

        return new self(
            $this->method,
            $this->pattern,
            $compiled['regex'],
            $this->params,
            $this->optional_params,
            $new_constraints,
            $this->action,
            $this->name,
            $this->middleware
        );
    }

    /**
     * Create a new route with a name.
     *
     * @param  string  $name  Route name
     *
     * @return Route  New route instance with name
     * @link https://github.com/zero-to-prod/http-router
     */
    public function withName(string $name): Route
    {
        return new self(
            $this->method,
            $this->pattern,
            $this->regex,
            $this->params,
            $this->optional_params,
            $this->constraints,
            $this->action,
            $name,
            $this->middleware
        );
    }

    /**
     * Create a new route with additional middleware.
     *
     * @param  mixed  $middleware  Single middleware (callable/class name) or array
     *
     * @return Route  New route instance with middleware
     * @link https://github.com/zero-to-prod/http-router
     */
    public function withMiddleware($middleware): Route
    {
        return new self(
            $this->method,
            $this->pattern,
            $this->regex,
            $this->params,
            $this->optional_params,
            $this->constraints,
            $this->action,
            $this->name,
            array_merge($this->middleware, (array) $middleware)
        );
    }

    /**
     * Check if this route is cacheable (no closures).
     *
     * @return bool  True if route can be cached
     * @link https://github.com/zero-to-prod/http-router
     */
    public function isCacheable(): bool
    {
        return !$this->action instanceof Closure
            && !array_filter($this->middleware, function ($mw) {
                return $mw instanceof Closure;
            });
    }

    /**
     * Convert route to array for serialization.
     *
     * @return array  Route data as associative array
     * @link https://github.com/zero-to-prod/http-router
     */
    public function toArray(): array
    {
        return [
            'method' => $this->method,
            'pattern' => $this->pattern,
            'regex' => $this->regex,
            'params' => $this->params,
            'optional_params' => $this->optional_params,
            'constraints' => $this->constraints,
            'action' => $this->action,
            'name' => $this->name,
            'middleware' => $this->middleware
        ];
    }

    /**
     * Create route from array data.
     *
     * @param  array  $data  Route data from toArray()
     *
     * @return Route  New route instance
     * @link https://github.com/zero-to-prod/http-router
     */
    public static function fromArray(array $data): Route
    {
        return new self(
            $data['method'],
            $data['pattern'],
            $data['regex'],
            $data['params'],
            $data['optional_params'],
            $data['constraints'],
            $data['action'],
            $data['name'] ?? null,
            $data['middleware'] ?? []
        );
    }

}
