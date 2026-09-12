<?php

namespace MartinMulder\LaravelModelMetadata\Filament\Resources\MetadataSchemas;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use MartinMulder\LaravelModelMetadata\Filament\Resources\MetadataSchemas\Pages\CreateMetadataSchema;
use MartinMulder\LaravelModelMetadata\Filament\Resources\MetadataSchemas\Pages\EditMetadataSchema;
use MartinMulder\LaravelModelMetadata\Filament\Resources\MetadataSchemas\Pages\ListMetadataSchemas;
use MartinMulder\LaravelModelMetadata\Filament\Resources\MetadataSchemas\Schemas\MetadataSchemaForm;
use MartinMulder\LaravelModelMetadata\Filament\Resources\MetadataSchemas\Tables\MetadataSchemasTable;
use MartinMulder\LaravelModelMetadata\Models\MetadataSchema;

class MetadataSchemaResource extends Resource
{
    protected static ?string $model = MetadataSchema::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static ?string $navigationLabel = 'Metadata Schemas';

    protected static ?string $modelLabel = 'metadata schema';

    protected static ?string $pluralModelLabel = 'metadata schemas';

    public static function form(Schema $schema): Schema
    {
        return MetadataSchemaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MetadataSchemasTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMetadataSchemas::route('/'),
            'create' => CreateMetadataSchema::route('/create'),
            'edit' => EditMetadataSchema::route('/{record}/edit'),
        ];
    }
}
