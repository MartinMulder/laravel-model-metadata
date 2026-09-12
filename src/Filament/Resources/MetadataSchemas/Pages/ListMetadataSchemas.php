<?php

namespace MartinMulder\LaravelModelMetadata\Filament\Resources\MetadataSchemas\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use MartinMulder\LaravelModelMetadata\Filament\Resources\MetadataSchemas\MetadataSchemaResource;

class ListMetadataSchemas extends ListRecords
{
    protected static string $resource = MetadataSchemaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
