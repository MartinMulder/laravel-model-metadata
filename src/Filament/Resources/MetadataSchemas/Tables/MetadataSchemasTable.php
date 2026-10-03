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
use MartinMulder\LaravelModelMetadata\Filament\Resources\MetadataSchemas\Schemas\MetadataSchemaForm;
use MartinMulder\LaravelModelMetadata\Models\MetadataSchema;
use MartinMulder\LaravelModelMetadata\Options\MetadataOptionSources;
use MartinMulder\LaravelModelMetadata\Scopes\MetadataScopeRegistry;

class MetadataSchemasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('owner_type')
                    ->label('Owner Model')
                    ->formatStateUsing(fn (string $state): string => app(MetadataScopeRegistry::class)->for($state)?->getLabel() ?? $state)
                    ->tooltip(fn (MetadataSchema $record): string => $record->owner_type)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('scope')
                    ->formatStateUsing(function (MetadataSchema $record, string $state): string {
                        $definition = app(MetadataScopeRegistry::class)->for($record->owner_type);

                        return $definition?->hasOptions()
                            ? $definition->getScopeLabel() . ': ' . $definition->getOptionLabel($state)
                            : $state;
                    })
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
                    ->state(fn ($record): string => filled($record->options_source)
                        ? 'from ' . (MetadataOptionSources::get($record->options_source)?->getLabel() ?? $record->options_source)
                        : (is_array($record->options) ? count($record->options) : 0) . ' option(s)'),

                TextColumn::make('sort_order')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('owner_type')
                    ->label('Owner Model')
                    ->options(fn (): array => MetadataSchemaForm::ownerOptions()),
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
