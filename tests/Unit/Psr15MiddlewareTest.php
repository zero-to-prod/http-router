<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zerotoprod\HttpRouter\HttpRouter;

class Psr15Controller
{
    public function test()
    {
        echo 'test';
    }
}

class Psr15MiddlewareTest extends TestCase
{
    /** @test */
    public function psr15_middleware_is_detected()
    {
        $router = HttpRouter::for('GET', '/test')
            ->get('/test', function () {
                echo 'success';
            })
            ->middleware(TestPsr15Middleware::class);

        ob_start();
        $router->dispatch();
        $output = ob_get_clean();

        $this->assertStringContainsString('PSR15', $output);
    }

    /** @test */
    public function variadic_middleware_still_works()
    {
        $router = HttpRouter::for('GET', '/test')
            ->get('/test', function () {
                echo 'success';
            })
            ->middleware(TestVariadicMiddleware::class);

        ob_start();
        $router->dispatch();
        $output = ob_get_clean();

        $this->assertStringContainsString('VARIADIC', $output);
    }

    /** @test */
    public function psr15_and_variadic_middleware_can_be_mixed()
    {
        $router = HttpRouter::for('GET', '/test')
            ->get('/test', function () {
                echo 'success';
            })
            ->middleware([TestPsr15Middleware::class, TestVariadicMiddleware::class]);

        ob_start();
        $router->dispatch();
        $output = ob_get_clean();

        $this->assertStringContainsString('PSR15', $output);
        $this->assertStringContainsString('VARIADIC', $output);
    }

    /** @test */
    public function psr15_middleware_receives_server_request()
    {
        $router = HttpRouter::for('GET', '/test')
            ->get('/test', function () {
                echo 'success';
            })
            ->middleware(TestPsr15RequestChecker::class);

        ob_start();
        $router->dispatch();
        $output = ob_get_clean();

        $this->assertStringContainsString('REQUEST_RECEIVED', $output);
    }

    /** @test */
    public function psr15_middleware_can_short_circuit()
    {
        $router = HttpRouter::for('GET', '/test')
            ->get('/test', function () {
                echo 'THIS SHOULD NOT APPEAR';
            })
            ->middleware(TestPsr15ShortCircuit::class);

        ob_start();
        $router->dispatch();
        $output = ob_get_clean();

        $this->assertStringContainsString('SHORT_CIRCUIT', $output);
        $this->assertStringNotContainsString('THIS SHOULD NOT APPEAR', $output);
    }

    /** @test */
    public function psr15_middleware_can_modify_response()
    {
        $router = HttpRouter::for('GET', '/test')
            ->get('/test', function () {
                echo 'original';
            })
            ->middleware(TestPsr15ResponseModifier::class);

        ob_start();
        $router->dispatch();
        $output = ob_get_clean();

        $this->assertStringContainsString('MODIFIED', $output);
    }

    /** @test */
    public function psr15_middleware_works_with_global_middleware()
    {
        $router = HttpRouter::for('GET', '/test')
            ->globalMiddleware(TestPsr15Middleware::class)
            ->get('/test', function () {
                echo 'success';
            });

        ob_start();
        $router->dispatch();
        $output = ob_get_clean();

        $this->assertStringContainsString('PSR15', $output);
    }

    /** @test */
    public function psr15_middleware_works_in_route_groups()
    {
        $router = HttpRouter::for('GET', '/admin/test')
            ->prefix('admin')
            ->middleware(TestPsr15Middleware::class)
            ->group(function ($r) {
                $r->get('/test', function () {
                    echo 'success';
                });
            });

        ob_start();
        $router->dispatch();
        $output = ob_get_clean();

        $this->assertStringContainsString('PSR15', $output);
    }

    /** @test */
    public function psr15_middleware_is_cacheable()
    {
        $router = HttpRouter::create()
            ->get('/test', [Psr15Controller::class, 'test'])
            ->middleware(TestPsr15Middleware::class);

        $this->assertTrue($router->isCacheable());
    }

    /** @test */
    public function psr15_middleware_survives_caching()
    {
        $router1 = HttpRouter::create()
            ->get('/test', [Psr15Controller::class, 'test'])
            ->middleware(TestPsr15Middleware::class);

        $compiled = $router1->compile();
        $router2 = HttpRouter::for('GET', '/test')->loadCompiled($compiled);

        ob_start();
        $router2->dispatch();
        $output = ob_get_clean();

        $this->assertStringContainsString('PSR15', $output);
    }
}

// Test middleware implementations

class TestPsr15Middleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);
        $body = (string) $response->getBody();
        $newBody = 'PSR15-' . $body;

        return $response->withBody(
            (new \Nyholm\Psr7\Factory\Psr17Factory())->createStream($newBody)
        );
    }
}

class TestVariadicMiddleware
{
    public function __invoke($next)
    {
        echo 'VARIADIC-';
        $next();
    }
}

class TestPsr15RequestChecker implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);
        $body = 'REQUEST_RECEIVED-' . (string) $response->getBody();

        return $response->withBody(
            (new \Nyholm\Psr7\Factory\Psr17Factory())->createStream($body)
        );
    }
}

class TestPsr15ShortCircuit implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $factory = new \Nyholm\Psr7\Factory\Psr17Factory();
        return $factory->createResponse(200)->withBody(
            $factory->createStream('SHORT_CIRCUIT')
        );
    }
}

class TestPsr15ResponseModifier implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);
        $body = (string) $response->getBody();

        return $response->withBody(
            (new \Nyholm\Psr7\Factory\Psr17Factory())->createStream($body . '-MODIFIED')
        );
    }
}
