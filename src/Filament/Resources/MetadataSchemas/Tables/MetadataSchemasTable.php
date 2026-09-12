<?php

namespace MartinMulder\LaravelModelMetadata\Filament\Resources\MetadataSchemas\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use MartinMulder\LaravelModelMetadata\Models\MetadataSchema;

class MetadataSchemasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('owner_type')
                    ->label('Owner Model')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('scope')
                    ->placeholder('— global —')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('key')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold'),

                TextColumn::make('type')
                    ->badge()
                    ->color('info')
                    ->sortable(),

                IconColumn::make('required')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('options')
                    ->label('Options')
                    ->state(fn ($record) => is_array($record->options) ? count($record->options) : 0)
                    ->suffix(' option(s)'),

                TextColumn::make('sort_order')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('owner_type')
                    ->label('Owner Model')
                    ->options(fn () => MetadataSchema::query()
                        ->distinct()
                        ->pluck('owner_type', 'owner_type')
                        ->toArray()),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
