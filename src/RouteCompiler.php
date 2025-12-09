<?php

namespace Zerotoprod\HttpRouter;

use InvalidArgumentException;

/**
 * Compiles URI patterns into regex for route matching.
 *
 * Centralizes all pattern parsing and regex compilation logic to avoid duplication
 * between RouteCollection and Route classes.
 *
 * @link https://github.com/zero-to-prod/http-router
 */
class RouteCompiler
{
    /**
     * Parse URI pattern and compile to regex with constraints.
     *
     * Supports:
     * - Named parameters: {id}, {name}
     * - Inline constraints: {id:\d+}
     * - Optional parameters: {name?}
     * - Where constraints: applied via $where_constraints array
     *
     * @param  string  $pattern           URI pattern to compile
     * @param  array   $where_constraints Optional constraints from where() calls
     *
     * @return array  Compiled route data with keys:
     *                - 'regex': Compiled regex pattern
     *                - 'params': Array of parameter names
     *                - 'optional_params': Array of optional parameter names
     *                - 'constraints': Array of inline constraints
     *
     * @throws InvalidArgumentException  If conflicting constraints detected
     * @link https://github.com/zero-to-prod/http-router
     */
    public static function compile(string $pattern, array $where_constraints = []): array
    {
        preg_match_all('/\{([a-zA-Z_]+\w*)(?::(.+?))?(\?)?\}/', $pattern, $matches);

        $params = [];
        $optional_params = [];
        $constraints = [];
        $search = [];
        $replace = [];

        foreach ($matches[0] as $i => $placeholder) {
            $name = $matches[1][$i];
            $inline = !empty($matches[2][$i]) ? $matches[2][$i] : null;
            $is_optional = !empty($matches[3][$i]);

            $params[] = $name;

            if ($is_optional) {
                $optional_params[] = $name;
            }

            $constraint = $where_constraints[$name] ?? $inline ?? '[^/]+';

            if ($inline && !isset($where_constraints[$name])) {
                $constraints[$name] = $inline;
            }

            if ($is_optional) {
                $search[] = '/'.$placeholder;
                $replace[] = '(?:/('.$constraint.'))?';
            } else {
                $search[] = $placeholder;
                $replace[] = '('.$constraint.')';
            }
        }

        return [
            'params' => $params,
            'optional_params' => $optional_params,
            'constraints' => $constraints,
            'regex' => '#^'.str_replace($search, $replace, $pattern).'$#'
        ];
    }
}