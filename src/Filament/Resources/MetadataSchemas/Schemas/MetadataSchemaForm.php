<?php

namespace MartinMulder\LaravelModelMetadata\Filament\Resources\MetadataSchemas\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use MartinMulder\LaravelModelMetadata\TypeRegistry;

class MetadataSchemaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('owner_type')
                    ->label('Owner Model')
                    ->required()
                    ->helperText('Fully qualified class name of the HasMetadata model, e.g. App\\Models\\Norm.')
                    ->maxLength(255),

                TextInput::make('scope')
                    ->label('Scope')
                    ->helperText('Leave empty to apply to every instance without a more specific scope match.')
                    ->maxLength(255),

                TextInput::make('key')
                    ->required()
                    ->maxLength(255),

                Select::make('type')
                    ->options(function () {
                        $types = array_keys(app(TypeRegistry::class)->all());

                        return array_combine($types, array_map('ucfirst', $types));
                    })
                    ->default('string')
                    ->required(),

                Textarea::make('default')
                    ->helperText('Raw default value. For "badges", a comma-separated list or JSON array is accepted; for "json", a valid JSON string.')
                    ->columnSpanFull(),

                TagsInput::make('options')
                    ->helperText('Allowed values for this key. Leave empty for unconstrained.')
                    ->dehydrateStateUsing(fn (?array $state): ?array => filled($state) ? $state : null)
                    ->columnSpanFull(),

                Toggle::make('required')
                    ->label('Required')
                    ->helperText('When enabled, this key is auto-populated on new records and cannot be deleted.')
                    ->default(true),

                TextInput::make('sort_order')
                    ->numeric(),
            ]);
    }
}
