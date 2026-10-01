<?php

namespace MartinMulder\LaravelModelMetadata;

use Illuminate\Support\ServiceProvider;
use MartinMulder\LaravelModelMetadata\Scopes\MetadataScopeRegistry;

class LaravelModelMetadataServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Merge package configuration
        $this->mergeConfigFrom(
            __DIR__ . '/../config/laravel-model-metadata.php',
            'laravel-model-metadata'
        );

        // Register the TypeRegistry singleton
        // Built-in types are always available, also when the host published an older config
        // without them; a type in the config with the same name replaces the built-in one.
        $this->app->singleton(TypeRegistry::class, function ($app) {
            return new TypeRegistry(array_merge(
                TypeRegistry::BUILT_IN,
                $app['config']->get('laravel-model-metadata.types', []),
            ));
        });

        // Scope definitions: packages register theirs in their own service provider
        // (MetadataScopes::register()); the host can add some through the config.
        $this->app->singleton(MetadataScopeRegistry::class, function ($app) {
            $registry = new MetadataScopeRegistry;

            foreach ($app['config']->get('laravel-model-metadata.scopes', []) as $definition) {
                $registry->register(is_string($definition) ? $app->call([$app->make($definition), '__invoke']) : $definition);
            }

            return $registry;
        });
    }

    /**
     * Bootstrap any package services.
     */
    public function boot(): void
    {
        // Load database migrations
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // Publish the package configuration
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/laravel-model-metadata.php' => config_path('laravel-model-metadata.php'),
            ], 'laravel-model-metadata-config');
        }
    }
}
