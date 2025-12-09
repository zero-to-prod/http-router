# HTTP Router

[![Repo](https://img.shields.io/badge/github-gray?logo=github)](https://github.com/zero-to-prod/http-router)
[![GitHub Actions Workflow Status](https://img.shields.io/github/actions/workflow/status/zero-to-prod/http-router/test.yml?label=test)](https://github.com/zero-to-prod/http-router/actions)
[![Packagist Downloads](https://img.shields.io/packagist/dt/zero-to-prod/http-router)](https://packagist.org/packages/zero-to-prod/http-router)
[![php](https://img.shields.io/packagist/php-v/zero-to-prod/http-router.svg?color=purple)](https://packagist.org/packages/zero-to-prod/http-router/stats)
[![Packagist Version](https://img.shields.io/packagist/v/zero-to-prod/http-router)](https://packagist.org/packages/zero-to-prod/http-router)
[![License](https://img.shields.io/github/license/zero-to-prod/http-router)](https://github.com/zero-to-prod/http-router)

High-performance HTTP router for PHP 7.2+ with three-level indexing, PSR-15 middleware support, and route caching.

## Features

- **High Performance**: Three-level route indexing for O(1) static route lookup and optimized dynamic route matching
- **PSR-15 Middleware**: Full support for PSR-15 middleware alongside legacy variadic middleware
- **Route Caching**: Production-optimized serialization with automatic cache management
- **RESTful Resources**: Automatic generation of resourceful routes
- **Route Groups**: Organize routes with shared prefixes and middleware
- **Named Routes**: Generate URLs from route names with parameter substitution
- **Parameter Constraints**: Inline (`{id:\d+}`) and fluent (`where()`) constraint syntax
- **PHP 7.1+ Compatible**: Broad multi-version support

## Installation

```bash
composer require zero-to-prod/http-router
```

## Quick Start

```php
use Zerotoprod\HttpRouter\HttpRouter;

$router = HttpRouter::for('GET', '/users/123')
    ->get('/users/{id}', [UserController::class, 'show'])
    ->where('id', '\d+')
    ->name('user.show')
    ->middleware(AuthMiddleware::class);

$router->dispatch();
```

## Basic Usage

### Defining Routes

```php
use Zerotoprod\HttpRouter\HttpRouter;

$router = HttpRouter::create();

// Static routes
$router->get('/', function() {
    echo 'Home';
});

// Dynamic routes with parameters
$router->get('/users/{id}', function(array $params) {
    echo "User ID: " . $params['id'];
});

// Routes with inline constraints
$router->get('/posts/{id:\d+}', [PostController::class, 'show']);

// Routes with fluent constraints
$router->get('/users/{id}', [UserController::class, 'show'])
    ->where('id', '\d+');
```

### HTTP Methods

```php
$router->get('/users', [UserController::class, 'index']);
$router->post('/users', [UserController::class, 'store']);
$router->put('/users/{id}', [UserController::class, 'update']);
$router->patch('/users/{id}', [UserController::class, 'patch']);
$router->delete('/users/{id}', [UserController::class, 'destroy']);
$router->options('/users', [UserController::class, 'options']);
$router->head('/users', [UserController::class, 'head']);

// Multiple methods
$router->any('/users/{id}', [UserController::class, 'handler'], ['GET', 'POST']);
```

### RESTful Resources

```php
// Generates all 7 RESTful routes
$router->resource('users', UserController::class);

// With filters
$router->resource('posts', PostController::class, ['only' => ['index', 'show']]);
$router->resource('comments', CommentController::class, ['except' => ['destroy']]);
```

Generated routes:
- `GET /users` → `index()`
- `GET /users/create` → `create()`
- `POST /users` → `store()`
- `GET /users/{id}` → `show()`
- `GET /users/{id}/edit` → `edit()`
- `PUT /users/{id}` → `update()`
- `DELETE /users/{id}` → `destroy()`

### Route Groups

```php
// Prefix groups
$router->prefix('api/v1')->group(function($router) {
    $router->get('/users', [UserController::class, 'index']); // /api/v1/users
});

// Middleware groups
$router->middleware([AuthMiddleware::class])->group(function($router) {
    $router->get('/dashboard', [DashboardController::class, 'index']);
});

// Combined
$router->prefix('admin')
    ->middleware([AuthMiddleware::class, AdminMiddleware::class])
    ->group(function($router) {
        $router->get('/users', [AdminController::class, 'users']);
    });
```

### Named Routes

```php
// Define named route
$router->get('/users/{id}', [UserController::class, 'show'])
    ->name('user.show');

// Generate URL
$url = $router->route('user.show', ['id' => 123]); // /users/123
```

### Middleware

#### PSR-15 Middleware

```php
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class AuthMiddleware implements MiddlewareInterface
{
    public function process(
        ServerRequestInterface $request, 
        RequestHandlerInterface $handler
    ): ResponseInterface {
        // Check authentication
        return $handler->handle($request);
    }
}

$router->get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(AuthMiddleware::class);
```

#### Variadic Middleware

```php
class LogMiddleware
{
    public function __invoke(callable $next, ...$context)
    {
        // Before request
        error_log('Request started');
        
        $next();
        
        // After request
        error_log('Request completed');
    }
}

$router->get('/users', [UserController::class, 'index'])
    ->middleware(LogMiddleware::class);
```

#### Global Middleware

```php
$router->globalMiddleware([
    CorsMiddleware::class,
    SecurityHeadersMiddleware::class
]);
```

### Route Caching

```php
// Enable automatic caching in production
$router->autoCache(
    cache_path: __DIR__ . '/cache/routes.php',
    env_var: 'APP_ENV',
    cache_envs: ['production']
);

// Manual caching
if ($router->isCacheable()) {
    $compiled = $router->compile();
    file_put_contents('routes.php', $compiled);
}

// Load from cache
$data = file_get_contents('routes.php');
$router->loadCompiled($data);
```

### Fallback Handler

```php
$router->fallback(function() {
    http_response_code(404);
    echo 'Not Found';
});
```

## Advanced Usage

### Dispatching with Context

```php
// Pass additional arguments to actions and middleware
$router = Router::for('GET', '/users', $container, $request);
$router->get('/users', function(array $params, $container, $request) {
    // Access container and request
});
```

### Optional Parameters

```php
$router->get('/users/{id?}', function(array $params) {
    $id = $params['id'] ?? 'all';
    echo "User: $id";
});
```

### Multiple Constraints

```php
$router->get('/posts/{year}/{month}/{slug}', [PostController::class, 'show'])
    ->where([
        'year' => '\d{4}',
        'month' => '\d{2}',
        'slug' => '[a-z0-9-]+'
    ]);
```

## Action Types

The router supports three types of actions:

### Controller Array
```php
$router->get('/users', [UserController::class, 'index']);
```

### Invokable Class
```php
$router->get('/users', UserAction::class);
```

### Closure
```php
$router->get('/users', function(array $params) {
    echo 'Users';
});
```

**Note**: Closures cannot be cached due to PHP serialization limitations.

## Performance

The router uses a three-level indexing strategy for optimal performance:

1. **Static Index (O(1))**: Hash map `method:path` → Route for exact matches
2. **Prefix Index (O(1) + O(n))**: Hash map `method:prefix` → [Routes] for common prefixes
3. **Method Index (O(n))**: Hash map `method` → [Routes] as fallback

This approach minimizes regex matching for most common routing patterns.

## Testing

```bash
composer test
```

## License

MIT License. See [LICENSE](LICENSE) for details.

## Contributing

Contributions are welcome! Please submit pull requests to the [GitHub repository](https://github.com/zero-to-prod/http-router).

## Support

- **Issues**: [GitHub Issues](https://github.com/zero-to-prod/http-router/issues)
- **Email**: dave0016@gmail.com
- **Documentation**: [https://zero-to-prod.github.io/http-router/](https://zero-to-prod.github.io/http-router/)

## Credits

Created and maintained by [David Smith](https://davidsmith.blog/).
