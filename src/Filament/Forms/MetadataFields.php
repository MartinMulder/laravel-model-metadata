<?php

namespace MartinMulder\LaravelModelMetadata\Filament\Forms;

use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Group;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use MartinMulder\LaravelModelMetadata\Attributes\RequiresMetadata;
use MartinMulder\LaravelModelMetadata\TypeRegistry;

/**
 * The metadata of a record as real form fields, one per defined key (#[RequiresMetadata] and the
 * metadata_schemas rows of the record's scope), instead of the key/value table of the
 * MetadataRelationManager:
 *
 *     Section::make('Metadata')->components([MetadataFields::make()])
 *
 * Fields by type: string/integer/boolean become a Select when the key has options; date a date
 * picker; badges and multiselect a multiple Select (with options) or tags; json a textarea. Values
 * are saved with setMetadata() after the record is saved. Only on an existing record (edit page):
 * the definitions depend on the record's scope. Keys without a definition are left alone.
 */
final class MetadataFields
{
    /**
     * @param  array<int, string>|null  $only  limit to these keys (in this order)
     * @param  array<int, string>  $except  leave these keys out
     */
    public static function make(?array $only = null, array $except = []): Group
    {
        return Group::make()
            ->statePath('metadata_fields')
            ->dehydrated(false)
            ->components(fn (?Model $record): array => static::fieldsFor($record, $only, $except))
            ->columns(2);
    }

    /**
     * @param  array<int, string>|null  $only
     * @param  array<int, string>  $except
     * @return array<int, Field>
     */
    public static function fieldsFor(?Model $record, ?array $only = null, array $except = []): array
    {
        if (! $record?->exists || ! method_exists($record, 'resolvedMetadataDefinitions')) {
            return [];
        }

        $definitions = $record->resolvedMetadataDefinitions();

        if ($only !== null) {
            $definitions = array_filter(array_replace(array_flip($only), $definitions), fn ($definition) => $definition instanceof RequiresMetadata);
        }

        $fields = [];
        foreach ($definitions as $key => $definition) {
            if (! in_array($key, $except, true)) {
                $fields[] = static::field($definition);
            }
        }

        return $fields;
    }

    public static function field(RequiresMetadata $definition): Field
    {
        $key = $definition->key;
        $type = $definition->type;
        $options = $definition->optionLabels();

        $field = match (true) {
            in_array($type, ['badges', 'multiselect'], true) && $options !== null => Select::make(static::name($key))
                ->multiple()
                ->searchable()
                ->options($options),
            in_array($type, ['badges', 'multiselect'], true) => TagsInput::make(static::name($key)),
            $options !== null => Select::make(static::name($key))
                ->options($options),
            $type === 'boolean' => Toggle::make(static::name($key)),
            $type === 'integer' => TextInput::make(static::name($key))->integer(),
            $type === 'date' => DatePicker::make(static::name($key)),
            $type === 'json' => Textarea::make(static::name($key))
                ->rows(4)
                ->rule(fn () => function (string $attribute, mixed $value, Closure $fail): void {
                    if (filled($value)) {
                        json_decode((string) $value);
                        if (json_last_error() !== JSON_ERROR_NONE) {
                            $fail('Must be valid JSON.');
                        }
                    }
                })
                ->columnSpanFull(),
            default => TextInput::make(static::name($key)),
        };

        return $field
            ->label(Str::of($key)->replace(['_', '-'], ' ')->ucfirst()->toString())
            ->dehydrated(false)
            ->afterStateHydrated(fn (Field $component, ?Model $record) => $component->state(
                static::toState($record?->getMetadata($key), $type, $options !== null),
            ))
            ->saveRelationshipsUsing(fn (mixed $state, Model $record) => static::save($record, $definition, $state));
    }

    /** The field name for a key: dots would be read as nesting. */
    public static function name(string $key): string
    {
        return str_replace('.', '__', $key);
    }

    private static function toState(mixed $value, string $type, bool $hasOptions): mixed
    {
        return match ($type) {
            'date' => $value?->format('Y-m-d'),
            'badges' => is_array($value) ? array_column($value, 'text') : [],
            'multiselect' => $value ?? [],
            'json' => $value === null ? null : (is_string($value) ? $value : json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
            'boolean' => $hasOptions ? ($value === null ? null : (string) $value) : (bool) $value,
            default => $value,
        };
    }

    private static function save(Model $record, RequiresMetadata $definition, mixed $state): void
    {
        $key = $definition->key;
        $value = match ($definition->type) {
            'date', 'string' => filled($state) ? $state : null,
            'integer' => filled($state) ? (int) $state : null,
            'boolean' => $state === null || $state === '' ? null : app(TypeRegistry::class)->get('boolean')->cast($state),
            'multiselect' => array_values((array) ($state ?? [])),
            'badges' => static::badges($record->getMetadata($key), (array) ($state ?? [])),
            'json' => filled($state) ? (json_decode((string) $state, true) ?? $state) : null,
            default => $state,
        };

        // Don't create rows for keys that were never set and are still empty.
        if (! $record->hasMetadata($key) && in_array($value, [null, '', []], true)) {
            return;
        }

        if ($record->hasMetadata($key) && $record->getMetadata($key) == $value) {
            return;
        }

        $record->setMetadata($key, $value, $definition->type);
    }

    /**
     * Badges from chosen texts, keeping the color a badge already had.
     *
     * @param  array<int, string>  $texts
     * @return array<int, array<string, string>>
     */
    private static function badges(mixed $current, array $texts): array
    {
        $colors = is_array($current) ? array_column($current, 'color', 'text') : [];

        return array_map(
            fn (string $text): array => isset($colors[$text]) ? ['text' => $text, 'color' => $colors[$text]] : ['text' => $text],
            array_values($texts),
        );
    }
}
