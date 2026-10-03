<?php

namespace MartinMulder\LaravelModelMetadata\Tests\Filament\Fixtures\Resources\Items;

use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use MartinMulder\LaravelModelMetadata\Filament\Forms\MetadataFields;
use MartinMulder\LaravelModelMetadata\Filament\RelationManagers\MetadataRelationManager;
use MartinMulder\LaravelModelMetadata\Tests\Filament\Fixtures\Item;
use MartinMulder\LaravelModelMetadata\Tests\Filament\Fixtures\Resources\Items\Pages\EditItem;
use MartinMulder\LaravelModelMetadata\Tests\Filament\Fixtures\Resources\Items\Pages\ListItems;

class ItemResource extends Resource
{
    protected static ?string $model = Item::class;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name'),
            MetadataFields::make(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('name')]);
    }

    public static function getRelations(): array
    {
        return [MetadataRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListItems::route('/'),
            'edit' => EditItem::route('/{record}/edit'),
        ];
    }
}
