<?php

namespace JTD\LaravelMCP\Tests;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

/**
 * Base test case for true unit tests that don't require Laravel application bootstrapping.
 *
 * This class is designed for testing isolated units of code with mocked dependencies,
 * avoiding the overhead and potential permission issues of bootstrapping a full Laravel
 * application through Orchestra Testbench.
 *
 * Use this for:
 * - Testing individual classes with mocked dependencies
 * - Testing middleware with mocked request/response cycle
 * - Testing services with mocked repositories
 * - Any test that doesn't require actual Laravel functionality
 *
 * Use TestCase (Orchestra) for:
 * - Integration tests
 * - Tests that need actual Laravel services
 * - Tests that need database access
 * - Tests that need to test service provider registration
 */
abstract class UnitTestCase extends PHPUnitTestCase
{
    use MockeryPHPUnitIntegration;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up any global mocks or test doubles here if needed
        $this->setUpGlobalMocks();
    }

    protected function tearDown(): void
    {
        // Close Mockery to verify expectations
        Mockery::close();

        parent::tearDown();
    }

    /**
     * Set up global mocks for commonly used Laravel facades.
     * Override this method in child classes if you need different behavior.
     */
    protected function setUpGlobalMocks(): void
    {
        // Child classes can override this to set up their own global mocks
    }

    /**
     * Create a mock object with Mockery.
     */
    protected function mock(string $class): \Mockery\MockInterface
    {
        return Mockery::mock($class);
    }

    /**
     * Create a partial mock object with Mockery.
     */
    protected function partialMock(string $class, array $constructorArgs = []): \Mockery\MockInterface
    {
        return Mockery::mock($class, $constructorArgs)->makePartial();
    }

    /**
     * Create a spy object with Mockery.
     */
    protected function spy(string $class): \Mockery\MockInterface
    {
        return Mockery::spy($class);
    }

    /**
     * Assert that an exception is thrown with a specific message.
     */
    protected function assertExceptionWithMessage(string $exceptionClass, string $expectedMessage, callable $callback): void
    {
        try {
            $callback();
            $this->fail("Expected exception {$exceptionClass} was not thrown");
        } catch (\Exception $e) {
            $this->assertInstanceOf($exceptionClass, $e);
            $this->assertStringContainsString($expectedMessage, $e->getMessage());
        }
    }

    /**
     * Create a mock request object.
     */
    protected function createMockRequest(
        string $method = 'GET',
        string $uri = '/',
        array $parameters = [],
        array $cookies = [],
        array $files = [],
        array $server = [],
        ?string $content = null
    ): \Mockery\MockInterface {
        $request = $this->mock(\Illuminate\Http\Request::class);

        $request->shouldReceive('method')->andReturn($method)->byDefault();
        $request->shouldReceive('path')->andReturn(trim($uri, '/'))->byDefault();
        $request->shouldReceive('url')->andReturn('http://localhost'.$uri)->byDefault();
        $request->shouldReceive('fullUrl')->andReturn('http://localhost'.$uri)->byDefault();
        $request->shouldReceive('getContent')->andReturn($content)->byDefault();
        $request->shouldReceive('all')->andReturn($parameters)->byDefault();
        $request->shouldReceive('input')->andReturnUsing(function ($key, $default = null) use ($parameters) {
            return $parameters[$key] ?? $default;
        })->byDefault();

        // Set up headers mock
        $headers = $this->mock(\Symfony\Component\HttpFoundation\HeaderBag::class);
        $request->headers = $headers;

        return $request;
    }

    /**
     * Create a mock response object.
     */
    protected function createMockResponse(
        string $content = '',
        int $status = 200,
        array $headers = []
    ): \Mockery\MockInterface {
        $response = $this->mock(\Illuminate\Http\Response::class);

        $response->shouldReceive('getContent')->andReturn($content)->byDefault();
        $response->shouldReceive('status')->andReturn($status)->byDefault();
        $response->shouldReceive('getStatusCode')->andReturn($status)->byDefault();
        $response->shouldReceive('header')->andReturnUsing(function ($key, $value = null) use (&$headers) {
            if ($value !== null) {
                $headers[$key] = $value;
            }

            return $this;
        })->byDefault();

        return $response;
    }

    /**
     * Create a mock JSON response object.
     */
    protected function createMockJsonResponse(
        array $data = [],
        int $status = 200,
        array $headers = []
    ): \Mockery\MockInterface {
        $response = $this->mock(\Illuminate\Http\JsonResponse::class);

        $response->shouldReceive('getData')->andReturn((object) $data)->byDefault();
        $response->shouldReceive('getContent')->andReturn(json_encode($data))->byDefault();
        $response->shouldReceive('status')->andReturn($status)->byDefault();
        $response->shouldReceive('getStatusCode')->andReturn($status)->byDefault();
        $response->shouldReceive('header')->andReturnUsing(function ($key, $value = null) use (&$headers) {
            if ($value !== null) {
                $headers[$key] = $value;
            }

            return $this;
        })->byDefault();

        return $response;
    }
}
