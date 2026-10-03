<?php

namespace MartinMulder\LaravelModelMetadata\Filament\Resources\MetadataSchemas\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use MartinMulder\LaravelModelMetadata\Models\MetadataSchema;
use MartinMulder\LaravelModelMetadata\Options\MetadataOptionSource;
use MartinMulder\LaravelModelMetadata\Options\MetadataOptionSourceRegistry;
use MartinMulder\LaravelModelMetadata\Scopes\MetadataScopeRegistry;
use MartinMulder\LaravelModelMetadata\TypeRegistry;

class MetadataSchemaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                ...static::ownerAndScopeComponents(),
                ...static::fieldComponents(),
            ]);
    }

    /**
     * Owner model and scope. Registered owners (MetadataScopes::register()) are offered as choices,
     * with their scope values; anything else stays possible as free text.
     *
     * @return array<int, mixed>
     */
    public static function ownerAndScopeComponents(): array
    {
        return [
            Toggle::make('custom_owner')
                ->label('Model not in the list')
                ->helperText('Enter the class name of a HasMetadata model yourself.')
                ->dehydrated(false)
                ->live()
                ->afterStateHydrated(fn (Toggle $component, ?MetadataSchema $record) => $component->state(
                    $record !== null && ! array_key_exists($record->owner_type, static::ownerOptions()),
                ))
                ->columnSpanFull(),

            Select::make('owner_type')
                ->label('Owner model')
                ->options(fn (): array => static::ownerOptions())
                ->searchable()
                ->required()
                ->live()
                ->afterStateUpdated(fn (Set $set) => $set('scope', null))
                ->visible(fn (Get $get): bool => ! $get('custom_owner')),

            TextInput::make('owner_type')
                ->label('Owner model')
                ->required()
                ->helperText('Fully qualified class name of the HasMetadata model, e.g. App\\Models\\Norm.')
                ->maxLength(255)
                ->live(onBlur: true)
                ->visible(fn (Get $get): bool => (bool) $get('custom_owner')),

            Select::make('scope')
                ->label(fn (Get $get): string => static::definition($get('owner_type'))?->getScopeLabel() ?? 'Scope')
                ->options(fn (Get $get): array => static::definition($get('owner_type'))?->getOptions() ?? [])
                ->placeholder('— all (global) —')
                ->helperText('Leave empty to apply to every instance without a more specific scope match.')
                ->visible(fn (Get $get): bool => (bool) static::definition($get('owner_type'))?->hasOptions()),

            TextInput::make('scope')
                ->label('Scope')
                ->helperText('Leave empty to apply to every instance without a more specific scope match.')
                ->maxLength(255)
                ->visible(fn (Get $get): bool => ! static::definition($get('owner_type'))?->hasOptions()),
        ];
    }

    /**
     * The definition of one field, shared with the MetadataSchemasRelationManager.
     *
     * @return array<int, mixed>
     */
    public static function fieldComponents(): array
    {
        return [
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
                ->helperText('Raw default value. For "badges", a comma-separated list or JSON array is accepted; for "json", a valid JSON string; for "date", YYYY-MM-DD (or empty).')
                ->columnSpanFull(),

            Select::make('options_source')
                ->label('Options from source')
                ->options(fn (): array => static::optionSourceOptions())
                ->placeholder('— fixed list below —')
                ->helperText('Choices offered by a package or the host app (MetadataOptionSources::register()). The stored values are the keys, e.g. slugs.')
                ->live()
                ->visible(fn (Get $get): bool => static::optionSourceOptions() !== [] || filled($get('options_source')))
                ->columnSpanFull(),

            TagsInput::make('options')
                ->helperText('Allowed values for this key. Leave empty for unconstrained.')
                ->dehydrateStateUsing(fn (?array $state, Get $get): ?array => filled($state) && blank($get('options_source')) ? $state : null)
                ->visible(fn (Get $get): bool => blank($get('options_source')))
                ->columnSpanFull(),

            Toggle::make('required')
                ->label('Required')
                ->helperText('When enabled, this key is auto-populated on new records and cannot be deleted.')
                ->default(true),

            TextInput::make('sort_order')
                ->numeric(),
        ];
    }

    /**
     * Registered owners by label, plus owner types already used in schema rows.
     *
     * @return array<string, string>
     */
    public static function ownerOptions(): array
    {
        $options = collect(app(MetadataScopeRegistry::class)->all())
            ->mapWithKeys(fn ($definition, string $owner): array => [$owner => $definition->getLabel()]);

        foreach (MetadataSchema::query()->distinct()->pluck('owner_type') as $owner) {
            $options[$owner] ??= $owner;
        }

        return $options->sort()->all();
    }

    /**
     * Registered option sources: [name => label].
     *
     * @return array<string, string>
     */
    public static function optionSourceOptions(): array
    {
        return collect(app(MetadataOptionSourceRegistry::class)->all())
            ->map(fn (MetadataOptionSource $source): string => $source->getLabel())
            ->sort()
            ->all();
    }

    private static function definition(?string $owner)
    {
        return filled($owner) ? app(MetadataScopeRegistry::class)->for($owner) : null;
    }
}
