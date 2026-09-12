<?php

namespace MartinMulder\LaravelModelMetadata;

use InvalidArgumentException;
use MartinMulder\LaravelModelMetadata\Contracts\MetadataType;

class TypeRegistry
{
    /**
     * The registered types.
     *
     * @var array<string, string|MetadataType>
     */
    protected array $types = [];

    /**
     * The instantiated type classes.
     *
     * @var array<string, MetadataType>
     */
    protected array $instances = [];

    /**
     * Create a new TypeRegistry instance.
     *
     * @param  array<string, string|MetadataType>  $types
     */
    public function __construct(array $types = [])
    {
        foreach ($types as $name => $class) {
            $this->register($name, $class);
        }
    }

    /**
     * Register a new metadata type.
     */
    public function register(string $name, string|MetadataType $classOrInstance): void
    {
        $this->types[$name] = $classOrInstance;
        // If an instance is passed directly, cache it
        if ($classOrInstance instanceof MetadataType) {
            $this->instances[$name] = $classOrInstance;
        } else {
            unset($this->instances[$name]);
        }
    }

    /**
     * Check if a type is registered.
     */
    public function has(string $name): bool
    {
        return isset($this->types[$name]);
    }

    /**
     * Get the concrete MetadataType instance for a given type name.
     */
    public function get(string $name): MetadataType
    {
        if (!$this->has($name)) {
            throw new InvalidArgumentException("Metadata type '{$name}' is not registered.");
        }

        if (isset($this->instances[$name])) {
            return $this->instances[$name];
        }

        $class = $this->types[$name];

        if (is_string($class)) {
            if (!class_exists($class)) {
                throw new InvalidArgumentException("Metadata type class '{$class}' for type '{$name}' does not exist.");
            }
            
            $instance = new $class();

            if (!$instance instanceof MetadataType) {
                throw new InvalidArgumentException("Metadata type class '{$class}' must implement " . MetadataType::class);
            }

            $this->instances[$name] = $instance;
        }

        return $this->instances[$name];
    }

    /**
     * Get all registered types.
     *
     * @return array<string, string|MetadataType>
     */
    public function all(): array
    {
        return $this->types;
    }
}
