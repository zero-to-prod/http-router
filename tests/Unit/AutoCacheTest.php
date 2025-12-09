<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Zerotoprod\HttpRouter\HttpRouter;

class Controller
{
    public function test()
    {
        echo 'test';
    }

    public function show()
    {
        echo 'show';
    }

    public function notFound()
    {
        echo '404';
    }
}

class GlobalMiddleware
{
    public function __invoke($next)
    {
        $next();
    }
}

class AutoCacheTest extends TestCase
{
    private $cache_file;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cache_file = sys_get_temp_dir() . '/test_routes_' . uniqid() . '.php';
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        if (file_exists($this->cache_file)) {
            unlink($this->cache_file);
        }
        // Clean up cache directory if empty
        $cache_dir = dirname($this->cache_file);
        if (is_dir($cache_dir) && count(scandir($cache_dir)) === 2) {
            @rmdir($cache_dir);
        }
        // Reset environment
        putenv('APP_ENV');
        if (isset($_ENV['APP_ENV'])) {
            unset($_ENV['APP_ENV']);
        }
    }

    /** @test */
    public function auto_cache_writes_cache_in_production_environment()
    {
        putenv('APP_ENV=production');

        $router = HttpRouter::for('GET', '/test')
            ->get('/test', ['Tests\\Unit\\Controller', 'test'])
            ->autoCache($this->cache_file);

        ob_start();
        $router->dispatch();
        ob_end_clean();

        $this->assertFileExists($this->cache_file);
    }

    /** @test */
    public function auto_cache_does_not_write_cache_in_local_environment()
    {
        putenv('APP_ENV=local');

        $router = HttpRouter::for('GET', '/test')
            ->get('/test', ['Tests\\Unit\\Controller', 'test'])
            ->autoCache($this->cache_file);

        ob_start();
        $router->dispatch();
        ob_end_clean();

        $this->assertFalse(file_exists($this->cache_file));
    }

    /** @test */
    public function auto_cache_loads_from_existing_cache_in_production()
    {
        putenv('APP_ENV=production');

        // First request writes cache
        $router1 = HttpRouter::for('GET', '/test')
            ->get('/test', ['Tests\\Unit\\Controller', 'test'])
            ->autoCache($this->cache_file);

        ob_start();
        $router1->dispatch();
        ob_end_clean();

        $this->assertFileExists($this->cache_file);

        // Second request loads from cache
        $router2 = HttpRouter::for('GET', '/test')
            ->autoCache($this->cache_file);

        ob_start();
        $router2->dispatch();
        ob_end_clean();

        $this->assertTrue($router2->hasRoute('GET', '/test'));
    }

    /** @test */
    public function auto_cache_does_not_overwrite_existing_cache()
    {
        putenv('APP_ENV=production');

        // First request writes cache
        $router1 = HttpRouter::for('GET', '/test')
            ->get('/test', ['Tests\\Unit\\Controller', 'test'])
            ->autoCache($this->cache_file);

        ob_start();
        $router1->dispatch();
        ob_end_clean();

        $original_time = filemtime($this->cache_file);
        sleep(1);

        // Second request loads from cache, doesn't write
        $router2 = HttpRouter::for('GET', '/test')
            ->autoCache($this->cache_file);

        ob_start();
        $router2->dispatch();
        ob_end_clean();

        $this->assertEquals($original_time, filemtime($this->cache_file));
    }

    /** @test */
    public function auto_cache_respects_custom_environment_variable()
    {
        putenv('CUSTOM_ENV=prod');

        $router = HttpRouter::for('GET', '/test')
            ->get('/test', ['Tests\\Unit\\Controller', 'test'])
            ->autoCache($this->cache_file, 'CUSTOM_ENV', ['prod']);

        ob_start();
        $router->dispatch();
        ob_end_clean();

        $this->assertFileExists($this->cache_file);

        putenv('CUSTOM_ENV');
    }

    /** @test */
    public function auto_cache_respects_custom_cache_environments()
    {
        putenv('APP_ENV=staging');

        $router = HttpRouter::for('GET', '/test')
            ->get('/test', ['Tests\\Unit\\Controller', 'test'])
            ->autoCache($this->cache_file, null, ['staging', 'production']);

        ob_start();
        $router->dispatch();
        ob_end_clean();

        $this->assertFileExists($this->cache_file);
    }

    /** @test */
    public function auto_cache_creates_cache_directory_if_not_exists()
    {
        putenv('APP_ENV=production');

        $nested_cache = sys_get_temp_dir() . '/test_cache_' . uniqid() . '/nested/routes.php';

        $router = HttpRouter::for('GET', '/test')
            ->get('/test', ['Tests\\Unit\\Controller', 'test'])
            ->autoCache($nested_cache);

        ob_start();
        $router->dispatch();
        ob_end_clean();

        $this->assertFileExists($nested_cache);

        // Cleanup
        unlink($nested_cache);
        rmdir(dirname($nested_cache));
        rmdir(dirname(dirname($nested_cache)));
    }

    /** @test */
    public function auto_cache_does_not_cache_routes_with_closures()
    {
        putenv('APP_ENV=production');

        $router = HttpRouter::for('GET', '/test')
            ->get('/test', function () {
                echo 'test';
            })
            ->autoCache($this->cache_file);

        ob_start();
        $router->dispatch();
        ob_end_clean();

        $this->assertFalse(file_exists($this->cache_file));
    }

    /** @test */
    public function auto_cache_includes_named_routes()
    {
        putenv('APP_ENV=production');

        $router1 = HttpRouter::for('GET', '/users/123')
            ->get('/users/{id}', ['Tests\\Unit\\Controller', 'show'])
            ->name('users.show')
            ->autoCache($this->cache_file);

        ob_start();
        $router1->dispatch();
        ob_end_clean();

        // Load from cache
        $router2 = HttpRouter::for('GET', '/test')
            ->autoCache($this->cache_file);

        ob_start();
        try {
            $router2->dispatch();
        } catch (\Exception $e) {
            // Ignore dispatch errors
        }
        ob_end_clean();

        $url = $router2->route('users.show', ['id' => 456]);
        $this->assertEquals('/users/456', $url);
    }

    /** @test */
    public function auto_cache_includes_global_middleware()
    {
        putenv('APP_ENV=production');

        $router1 = HttpRouter::for('GET', '/test')
            ->globalMiddleware('Tests\\Unit\\GlobalMiddleware')
            ->get('/test', ['Tests\\Unit\\Controller', 'test'])
            ->autoCache($this->cache_file);

        ob_start();
        $router1->dispatch();
        ob_end_clean();

        // Load cache and check
        $compiled = file_get_contents($this->cache_file);
        $data = unserialize($compiled);

        $this->assertArrayHasKey('global_middleware', $data);
        $this->assertContains('Tests\\Unit\\GlobalMiddleware', $data['global_middleware']);
    }

    /** @test */
    public function auto_cache_works_with_fallback_routes()
    {
        putenv('APP_ENV=production');

        $router = HttpRouter::for('GET', '/nonexistent')
            ->get('/test', ['Tests\\Unit\\Controller', 'test'])
            ->fallback(['Tests\\Unit\\Controller', 'notFound'])
            ->autoCache($this->cache_file);

        ob_start();
        $router->dispatch();
        ob_end_clean();

        $this->assertFileExists($this->cache_file);
    }
}
