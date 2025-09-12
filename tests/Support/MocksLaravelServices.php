<?php

namespace JTD\LaravelMCP\Tests\Support;

use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Facade;
use Mockery;

/**
 * Trait for mocking Laravel services in unit tests.
 *
 * This trait provides helper methods to mock common Laravel services
 * like Config, Cache, Log, etc. in unit tests that don't bootstrap
 * the full Laravel application.
 */
trait MocksLaravelServices
{
    /**
     * Mock Laravel config helper and facade.
     */
    protected function mockConfig(array $config = []): void
    {
        // Create a container instance
        $container = Container::getInstance();
        if (! $container) {
            $container = new Container;
            Container::setInstance($container);
        }

        // Create config repository with provided data
        $configRepo = new ConfigRepository($config);

        // Bind to container
        $container->instance('config', $configRepo);

        // Set up facades
        Facade::setFacadeApplication($container);

        // Mock the config helper function if needed
        if (! function_exists('config')) {
            function config($key = null, $default = null)
            {
                $config = Container::getInstance()->make('config');
                if (is_null($key)) {
                    return $config;
                }
                if (is_array($key)) {
                    $config->set($key);

                    return null;
                }

                return $config->get($key, $default);
            }
        }
    }

    /**
     * Mock Laravel auth service.
     */
    protected function mockAuth($user = null): void
    {
        $container = Container::getInstance();
        if (! $container) {
            $container = new Container;
            Container::setInstance($container);
        }

        $authMock = Mockery::mock('auth');
        $authMock->shouldReceive('user')->andReturn($user);
        $authMock->shouldReceive('check')->andReturn($user !== null);
        $authMock->shouldReceive('id')->andReturn($user ? ($user->id ?? 1) : null);
        $authMock->shouldReceive('guard')->andReturnSelf();

        $container->instance('auth', $authMock);
    }

    /**
     * Mock Laravel cache service.
     */
    protected function mockCache(): \Mockery\MockInterface
    {
        $container = Container::getInstance();
        if (! $container) {
            $container = new Container;
            Container::setInstance($container);
        }

        $cacheMock = Mockery::mock('cache');
        $cacheMock->shouldReceive('get')->andReturn(null)->byDefault();
        $cacheMock->shouldReceive('put')->andReturn(true)->byDefault();
        $cacheMock->shouldReceive('remember')->andReturnUsing(function ($key, $ttl, $callback) {
            return $callback();
        })->byDefault();
        $cacheMock->shouldReceive('forget')->andReturn(true)->byDefault();
        $cacheMock->shouldReceive('flush')->andReturn(true)->byDefault();

        $container->instance('cache', $cacheMock);

        return $cacheMock;
    }

    /**
     * Mock Laravel log service.
     */
    protected function mockLog(): \Mockery\MockInterface
    {
        $container = Container::getInstance();
        if (! $container) {
            $container = new Container;
            Container::setInstance($container);
        }

        $logMock = Mockery::mock('log');
        $logMock->shouldReceive('info')->andReturnSelf()->byDefault();
        $logMock->shouldReceive('debug')->andReturnSelf()->byDefault();
        $logMock->shouldReceive('warning')->andReturnSelf()->byDefault();
        $logMock->shouldReceive('error')->andReturnSelf()->byDefault();
        $logMock->shouldReceive('critical')->andReturnSelf()->byDefault();

        $container->instance('log', $logMock);

        return $logMock;
    }

    /**
     * Clean up mocked services.
     */
    protected function cleanupMockedServices(): void
    {
        Container::setInstance(null);
        Facade::clearResolvedInstances();
        Mockery::close();
    }
}
