<?php

namespace MartinMulder\LaravelModelMetadata\Tests\Filament\Fixtures\Resources\Categories;

use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use MartinMulder\LaravelModelMetadata\Filament\RelationManagers\MetadataSchemasRelationManager;
use MartinMulder\LaravelModelMetadata\Tests\Filament\Fixtures\Category;
use MartinMulder\LaravelModelMetadata\Tests\Filament\Fixtures\Resources\Categories\Pages\EditCategory;
use MartinMulder\LaravelModelMetadata\Tests\Filament\Fixtures\Resources\Categories\Pages\ListCategories;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([TextInput::make('name')]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('name')]);
    }

    public static function getRelations(): array
    {
        return [MetadataSchemasRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCategories::route('/'),
            'edit' => EditCategory::route('/{record}/edit'),
        ];
    }
}
