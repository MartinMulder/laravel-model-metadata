<?php

namespace MartinMulder\LaravelModelMetadata\Scopes;

/**
 * Short static access to the registry, for service providers:
 *
 *     MetadataScopes::register(MetadataScope::for(Document::class)->…);
 */
final class MetadataScopes
{
    public static function register(MetadataScope ...$scopes): void
    {
        foreach ($scopes as $scope) {
            app(MetadataScopeRegistry::class)->register($scope);
        }
    }

    public static function for(string $owner): ?MetadataScope
    {
        return app(MetadataScopeRegistry::class)->for($owner);
    }
}
