<?php

namespace JTD\LaravelMCP\Integration;

use Illuminate\Container\Container;
use Illuminate\Contracts\Container\BindingResolutionException;

class LaravelServiceBridge
{
    private Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function resolveForMcp(string $abstract, array $parameters = [])
    {
        try {
            return $this->container->make($abstract, $parameters);
        } catch (BindingResolutionException $e) {
            throw new \RuntimeException("Failed to resolve service for MCP: {$abstract}", 0, $e);
        }
    }

    public function injectDependencies(object $mcpComponent): void
    {
        $reflection = new \ReflectionClass($mcpComponent);

        // Inject through constructor if needed
        $this->injectConstructorDependencies($mcpComponent, $reflection);

        // Inject through properties with attributes
        $this->injectPropertyDependencies($mcpComponent, $reflection);

        // Inject through setter methods
        $this->injectSetterDependencies($mcpComponent, $reflection);
    }

    private function injectConstructorDependencies(object $component, \ReflectionClass $reflection): void
    {
        $constructor = $reflection->getConstructor();

        if (! $constructor) {
            return;
        }

        $parameters = [];
        foreach ($constructor->getParameters() as $parameter) {
            $type = $parameter->getType();

            if ($type && ! $type->isBuiltin()) {
                $parameters[] = $this->container->make($type->getName());
            }
        }

        if (! empty($parameters)) {
            $constructor->invokeArgs($component, $parameters);
        }
    }

    private function injectPropertyDependencies(object $component, \ReflectionClass $reflection): void
    {
        foreach ($reflection->getProperties() as $property) {
            $attributes = $property->getAttributes(Inject::class);

            if (! empty($attributes)) {
                $injectAttribute = $attributes[0]->newInstance();
                $service = $this->container->make($injectAttribute->service ?? $property->getType()->getName());

                $property->setAccessible(true);
                $property->setValue($component, $service);
            }
        }
    }

    private function injectSetterDependencies(object $component, \ReflectionClass $reflection): void
    {
        foreach ($reflection->getMethods() as $method) {
            if (str_starts_with($method->getName(), 'set') && $method->getNumberOfParameters() === 1) {
                $parameter = $method->getParameters()[0];
                $type = $parameter->getType();

                if ($type && ! $type->isBuiltin() && $this->container->bound($type->getName())) {
                    $service = $this->container->make($type->getName());
                    $method->invoke($component, $service);
                }
            }
        }
    }
}
