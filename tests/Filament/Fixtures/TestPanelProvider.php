<?php

namespace MartinMulder\LaravelModelMetadata\Tests\Filament\Fixtures;

use Filament\Panel;
use Filament\PanelProvider;
use MartinMulder\LaravelModelMetadata\Filament\Resources\MetadataSchemas\MetadataSchemaResource;
use MartinMulder\LaravelModelMetadata\Tests\Filament\Fixtures\Resources\Categories\CategoryResource;
use MartinMulder\LaravelModelMetadata\Tests\Filament\Fixtures\Resources\Items\ItemResource;

class TestPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('test')
            ->path('test')
            ->default()
            ->resources([
                MetadataSchemaResource::class,
                CategoryResource::class,
                ItemResource::class,
            ]);
    }
}
