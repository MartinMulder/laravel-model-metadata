<?php

namespace MartinMulder\LaravelModelMetadata\Options;

/**
 * Short static access to the registry, for service providers:
 *
 *     MetadataOptionSources::register(MetadataOptionSource::make('…')->…);
 */
final class MetadataOptionSources
{
    public static function register(MetadataOptionSource ...$sources): void
    {
        foreach ($sources as $source) {
            app(MetadataOptionSourceRegistry::class)->register($source);
        }
    }

    public static function get(string $name): ?MetadataOptionSource
    {
        return app(MetadataOptionSourceRegistry::class)->get($name);
    }
}
