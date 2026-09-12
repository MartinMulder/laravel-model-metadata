<?php

namespace MartinMulder\LaravelModelMetadata;

use Illuminate\Support\ServiceProvider;

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
        $this->app->singleton(TypeRegistry::class, function ($app) {
            return new TypeRegistry($app['config']->get('laravel-model-metadata.types', []));
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
