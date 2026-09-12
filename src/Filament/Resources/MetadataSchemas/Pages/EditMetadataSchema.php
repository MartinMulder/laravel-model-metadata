<?php

namespace MartinMulder\LaravelModelMetadata\Filament\Resources\MetadataSchemas\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use MartinMulder\LaravelModelMetadata\Filament\Resources\MetadataSchemas\MetadataSchemaResource;

class EditMetadataSchema extends EditRecord
{
    protected static string $resource = MetadataSchemaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
