<?php

namespace MartinMulder\LaravelModelMetadata\Filament\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use MartinMulder\LaravelModelMetadata\Filament\Resources\MetadataSchemas\Schemas\MetadataSchemaForm;
use MartinMulder\LaravelModelMetadata\Models\MetadataSchema;
use MartinMulder\LaravelModelMetadata\Options\MetadataOptionSources;

/**
 * Manages the metadata fields of one scope, on the resource of the scope's source model — e.g.
 * the fields of all documents of one document type, on the DocumentType edit page. The source
 * model uses the ProvidesMetadataScope trait; owner and scope are filled in automatically.
 *
 * Extend it to change the texts, e.g. `protected static ?string $title = 'Metadata-velden';`.
 */
class MetadataSchemasRelationManager extends RelationManager
{
    protected static string $relationship = 'metadataSchemas';

    protected static ?string $title = 'Metadata fields';

    protected static ?string $modelLabel = 'metadata field';

    protected static ?string $pluralModelLabel = 'metadata fields';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return method_exists($ownerRecord, 'metadataSchemas');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components(MetadataSchemaForm::fieldComponents());
    }

    public function table(Table $table): Table
    {
        $definition = $this->getOwnerRecord()::metadataScopeDefinition();

        return $table
            ->recordTitleAttribute('key')
            ->description('Applies to every ' . $definition->getLabel() . ' with ' . $definition->getScopeLabel() . ' "' . $definition->getOptionLabel((string) $this->getOwnerRecord()->getAttribute($definition->getSourceKey())) . '", in addition to the global fields.')
            ->columns([
                TextColumn::make('key')
                    ->weight('semibold'),
                TextColumn::make('type')
                    ->badge()
                    ->color('info'),
                TextColumn::make('default')
                    ->placeholder('—')
                    ->limit(40),
                TextColumn::make('options')
                    ->state(fn (MetadataSchema $record): string => filled($record->options_source)
                        ? 'from ' . (MetadataOptionSources::get($record->options_source)?->getLabel() ?? $record->options_source)
                        : (is_array($record->options) ? count($record->options) : 0) . ' option(s)'),
                IconColumn::make('required')
                    ->boolean(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
