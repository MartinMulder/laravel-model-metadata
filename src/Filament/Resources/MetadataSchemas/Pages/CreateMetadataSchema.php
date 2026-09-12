<?php

namespace MartinMulder\LaravelModelMetadata\Filament\Resources\MetadataSchemas\Pages;

use Filament\Resources\Pages\CreateRecord;
use MartinMulder\LaravelModelMetadata\Filament\Resources\MetadataSchemas\MetadataSchemaResource;

class CreateMetadataSchema extends CreateRecord
{
    protected static string $resource = MetadataSchemaResource::class;
}
