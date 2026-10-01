<?php

namespace MartinMulder\LaravelModelMetadata\Scopes;

use Illuminate\Database\Eloquent\Model;

/**
 * All scope definitions, keyed by owner model. Filled by packages (MetadataScopes::register() in
 * their service provider) and by the host through config('laravel-model-metadata.scopes').
 */
class MetadataScopeRegistry
{
    /** @var array<class-string<Model>, MetadataScope> */
    private array $scopes = [];

    public function register(MetadataScope $scope): void
    {
        $this->scopes[$scope->owner] = $scope;
    }

    /** @param  class-string<Model>|string  $owner */
    public function for(string $owner): ?MetadataScope
    {
        return $this->scopes[$owner] ?? null;
    }

    /** @return array<class-string<Model>, MetadataScope> */
    public function all(): array
    {
        return $this->scopes;
    }

    /**
     * The definitions whose scope values are records of this source model.
     *
     * @param  class-string<Model>  $source
     * @return array<int, MetadataScope>
     */
    public function forSource(string $source): array
    {
        return array_values(array_filter(
            $this->scopes,
            fn (MetadataScope $scope): bool => $scope->getSource() === $source,
        ));
    }
}
