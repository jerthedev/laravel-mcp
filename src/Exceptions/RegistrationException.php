<?php

namespace JTD\LaravelMCP\Exceptions;

/**
 * Exception for component registration errors.
 *
 * This exception is thrown when errors occur during MCP component
 * registration, such as duplicate registrations, invalid components,
 * discovery failures, or registry-related issues.
 */
class RegistrationException extends McpException
{
    /**
     * Component type that caused the error.
     */
    protected ?string $componentType = null;

    /**
     * Component name that caused the error.
     */
    protected ?string $componentName = null;

    /**
     * Create a new registration exception instance.
     *
     * @param  string  $message  Error message
     * @param  int  $code  Error code
     * @param  string|null  $componentType  Component type
     * @param  string|null  $componentName  Component name
     * @param  mixed  $data  Additional error data
     * @param  array  $context  Error context
     * @param  \Throwable|null  $previous  Previous exception
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?string $componentType = null,
        ?string $componentName = null,
        $data = null,
        array $context = [],
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $data, $context, $previous);
        $this->componentType = $componentType;
        $this->componentName = $componentName;
    }

    /**
     * Get the component type.
     */
    public function getComponentType(): ?string
    {
        return $this->componentType;
    }

    /**
     * Set the component type.
     *
     * @param  string  $componentType  Component type
     */
    public function setComponentType(string $componentType): self
    {
        $this->componentType = $componentType;

        return $this;
    }

    /**
     * Get the component name.
     */
    public function getComponentName(): ?string
    {
        return $this->componentName;
    }

    /**
     * Set the component name.
     *
     * @param  string  $componentName  Component name
     */
    public function setComponentName(string $componentName): self
    {
        $this->componentName = $componentName;

        return $this;
    }

    /**
     * Convert exception to array format with component info.
     */
    public function toArray(): array
    {
        $result = parent::toArray();

        if ($this->componentType) {
            $result['component_type'] = $this->componentType;
        }

        if ($this->componentName) {
            $result['component_name'] = $this->componentName;
        }

        return $result;
    }

    /**
     * Create a duplicate registration exception.
     *
     * @param  string  $type  Component type
     * @param  string  $name  Component name
     * @param  mixed  $data  Additional data
     * @return static
     */
    public static function duplicateRegistration(string $type, string $name, $data = null): self
    {
        return new static(
            "Component '{$name}' of type '{$type}' is already registered",
            -32030,
            $type,
            $name,
            $data
        );
    }

    /**
     * Create a component not found exception.
     *
     * @param  string  $type  Component type
     * @param  string  $name  Component name
     * @param  mixed  $data  Additional data
     * @return static
     */
    public static function componentNotFound(string $type, string $name, $data = null): self
    {
        return new static(
            "Component '{$name}' of type '{$type}' not found",
            -32031,
            $type,
            $name,
            $data
        );
    }

    /**
     * Create an invalid component exception.
     *
     * @param  string  $type  Component type
     * @param  string  $name  Component name
     * @param  string  $reason  Reason why component is invalid
     * @param  mixed  $data  Additional data
     * @return static
     */
    public static function invalidComponent(string $type, string $name, string $reason, $data = null): self
    {
        return new static(
            "Invalid {$type} component '{$name}': {$reason}",
            -32032,
            $type,
            $name,
            array_merge($data ?? [], ['reason' => $reason])
        );
    }

    /**
     * Create a component class not found exception.
     *
     * @param  string  $className  Class name
     * @param  string  $type  Component type
     * @param  mixed  $data  Additional data
     * @return static
     */
    public static function classNotFound(string $className, string $type, $data = null): self
    {
        return new static(
            "Component class '{$className}' not found for type '{$type}'",
            -32033,
            $type,
            $className,
            array_merge($data ?? [], ['class_name' => $className])
        );
    }

    /**
     * Create an invalid component class exception.
     *
     * @param  string  $className  Class name
     * @param  string  $type  Component type
     * @param  string  $expectedBase  Expected base class
     * @param  mixed  $data  Additional data
     * @return static
     */
    public static function invalidComponentClass(string $className, string $type, string $expectedBase, $data = null): self
    {
        return new static(
            "Class '{$className}' for type '{$type}' must extend '{$expectedBase}'",
            -32034,
            $type,
            $className,
            array_merge($data ?? [], ['class_name' => $className, 'expected_base' => $expectedBase])
        );
    }

    /**
     * Create a discovery failure exception.
     *
     * @param  string  $path  Discovery path
     * @param  string  $reason  Failure reason
     * @param  mixed  $data  Additional data
     * @return static
     */
    public static function discoveryFailure(string $path, string $reason, $data = null): self
    {
        return new static(
            "Component discovery failed for path '{$path}': {$reason}",
            -32035,
            null,
            null,
            array_merge($data ?? [], ['path' => $path, 'reason' => $reason])
        );
    }

    /**
     * Create a registry not available exception.
     *
     * @param  string  $type  Registry type
     * @param  mixed  $data  Additional data
     * @return static
     */
    public static function registryNotAvailable(string $type, $data = null): self
    {
        return new static(
            "Registry for type '{$type}' is not available",
            -32036,
            $type,
            null,
            $data
        );
    }

    /**
     * Create an unsupported component type exception.
     *
     * @param  string  $type  Unsupported type
     * @param  array  $supportedTypes  Supported types
     * @param  mixed  $data  Additional data
     * @return static
     */
    public static function unsupportedComponentType(string $type, array $supportedTypes = [], $data = null): self
    {
        $message = "Unsupported component type: {$type}";
        if (! empty($supportedTypes)) {
            $message .= '. Supported types: '.implode(', ', $supportedTypes);
        }

        return new static(
            $message,
            -32037,
            $type,
            null,
            array_merge($data ?? [], ['supported_types' => $supportedTypes])
        );
    }

    /**
     * Create a component instantiation failure exception.
     *
     * @param  string  $className  Class name
     * @param  string  $type  Component type
     * @param  string  $reason  Failure reason
     * @param  mixed  $data  Additional data
     * @return static
     */
    public static function instantiationFailure(string $className, string $type, string $reason, $data = null): self
    {
        return new static(
            "Failed to instantiate {$type} component '{$className}': {$reason}",
            -32038,
            $type,
            $className,
            array_merge($data ?? [], ['class_name' => $className, 'reason' => $reason])
        );
    }

    /**
     * Create a component validation failure exception.
     *
     * @param  string  $type  Component type
     * @param  string  $name  Component name
     * @param  array  $validationErrors  Validation errors
     * @param  mixed  $data  Additional data
     * @return static
     */
    public static function validationFailure(string $type, string $name, array $validationErrors, $data = null): self
    {
        return new static(
            "Component '{$name}' of type '{$type}' failed validation",
            -32039,
            $type,
            $name,
            array_merge($data ?? [], ['validation_errors' => $validationErrors])
        );
    }

    /**
     * Create a configuration error exception.
     *
     * @param  string  $type  Component type
     * @param  string  $name  Component name
     * @param  string  $configError  Configuration error
     * @param  mixed  $data  Additional data
     * @return static
     */
    public static function configurationError(string $type, string $name, string $configError, $data = null): self
    {
        return new static(
            "Configuration error for {$type} component '{$name}': {$configError}",
            -32040,
            $type,
            $name,
            array_merge($data ?? [], ['config_error' => $configError])
        );
    }

    /**
     * Create exception from registration error.
     *
     * @param  \Throwable  $throwable  Source throwable
     * @param  string  $type  Component type
     * @param  string  $name  Component name
     * @return static
     */
    public static function fromRegistrationError(\Throwable $throwable, string $type, string $name): self
    {
        return new static(
            $throwable->getMessage(),
            -32041,
            $type,
            $name,
            [
                'type' => get_class($throwable),
                'file' => $throwable->getFile(),
                'line' => $throwable->getLine(),
            ],
            [],
            $throwable
        );
    }
}
