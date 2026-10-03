<?php

namespace MartinMulder\LaravelModelMetadata\Options;

/**
 * All option sources, by name. Filled by packages and the host (MetadataOptionSources::register()
 * in a service provider).
 */
class MetadataOptionSourceRegistry
{
    /** @var array<string, MetadataOptionSource> */
    private array $sources = [];

    public function register(MetadataOptionSource $source): void
    {
        $this->sources[$source->name] = $source;
    }

    public function get(string $name): ?MetadataOptionSource
    {
        return $this->sources[$name] ?? null;
    }

    /** @return array<string, MetadataOptionSource> */
    public function all(): array
    {
        return $this->sources;
    }
}
