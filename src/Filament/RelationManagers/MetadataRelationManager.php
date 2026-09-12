<?php

namespace MartinMulder\LaravelModelMetadata\Filament\RelationManagers;

use Filament\Actions;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use MartinMulder\LaravelModelMetadata\TypeRegistry;

class MetadataRelationManager extends RelationManager
{
    protected static string $relationship = 'metadata';

    protected static ?string $recordTitleAttribute = 'key';

    protected static ?string $title = 'Metadata';

    protected static ?string $modelLabel = 'metadata';

    protected static ?string $pluralModelLabel = 'metadata';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('key')
                    ->required()
                    ->maxLength(255)
                    ->live()
                    ->unique(
                        table: 'model_metadata',
                        column: 'key',
                        ignoreRecord: true,
                        modifyRuleUsing: function ($rule, $livewire) {
                            return $rule
                                ->where('model_type', $livewire->getOwnerRecord()::class)
                                ->where('model_id', $livewire->getOwnerRecord()->getKey());
                        }
                    )
                    ->placeholder('e.g., tags, status, is_active')
                    // Renaming a required key's row would functionally delete it; the actual
                    // protection is enforced server-side in the EditAction below, this is just
                    // the matching UI cue.
                    ->disabled(fn (?Model $record): bool => $record !== null && $this->isMetadataKeyRequired($record))
                    ->helperText(fn (?Model $record): ?string => ($record !== null && $this->isMetadataKeyRequired($record))
                        ? 'This key is required and cannot be renamed.'
                        : null),

                Forms\Components\Select::make('type')
                    ->options(function () {
                        $types = array_keys(app(TypeRegistry::class)->all());
                        return array_combine($types, array_map('ucfirst', $types));
                    })
                    ->default('string')
                    ->required()
                    ->live()
                    ->disabled(fn (?Model $record): bool => $record !== null && $this->isMetadataKeyRequired($record)),

                // Value input when the key has declared options (single choice): replaces the
                // type-specific free-text/toggle fields below for string/integer/boolean types.
                Forms\Components\Select::make('value_select')
                    ->label('Value')
                    ->options(fn (Get $get) => array_combine(
                        $this->getOptionsForKey($get('key')) ?? [],
                        $this->getOptionsForKey($get('key')) ?? [],
                    ))
                    ->visible(fn (Get $get) => in_array($get('type'), ['string', 'integer', 'boolean'], true)
                        && $this->getOptionsForKey($get('key')) !== null)
                    ->afterStateHydrated(function ($component, $record) {
                        if ($record && in_array($record->type, ['string', 'integer', 'boolean'], true)
                            && $this->getOptionsForKey($record->key) !== null) {
                            $component->state((string) $record->value);
                        }
                    }),

                // Value input when a badges key has declared options (multiple choice): replaces
                // the free-form Repeater below, and does not support per-badge custom colors.
                Forms\Components\Select::make('value_multiselect')
                    ->label('Value')
                    ->multiple()
                    ->options(fn (Get $get) => array_combine(
                        $this->getOptionsForKey($get('key')) ?? [],
                        $this->getOptionsForKey($get('key')) ?? [],
                    ))
                    ->visible(fn (Get $get) => $get('type') === 'badges'
                        && $this->getOptionsForKey($get('key')) !== null)
                    ->afterStateHydrated(function ($component, $record) {
                        if ($record && $record->type === 'badges' && $this->getOptionsForKey($record->key) !== null) {
                            $component->state(is_array($record->value) ? array_column($record->value, 'text') : []);
                        }
                    }),

                // Conditional field for Boolean
                Forms\Components\Toggle::make('value_boolean')
                    ->label('Value')
                    ->visible(fn (Get $get) => $get('type') === 'boolean' && $this->getOptionsForKey($get('key')) === null)
                    ->afterStateHydrated(function ($component, $record) {
                        if ($record && $record->type === 'boolean' && $this->getOptionsForKey($record->key) === null) {
                            $component->state((bool) $record->value);
                        }
                    }),

                // Conditional field for Integer
                Forms\Components\TextInput::make('value_integer')
                    ->label('Value')
                    ->numeric()
                    ->visible(fn (Get $get) => $get('type') === 'integer' && $this->getOptionsForKey($get('key')) === null)
                    ->afterStateHydrated(function ($component, $record) {
                        if ($record && $record->type === 'integer' && $this->getOptionsForKey($record->key) === null) {
                            $component->state((int) $record->value);
                        }
                    }),

                // Conditional field for Badges
                Forms\Components\Repeater::make('value_badges')
                    ->label('Badges')
                    ->visible(fn (Get $get) => $get('type') === 'badges' && $this->getOptionsForKey($get('key')) === null)
                    ->schema([
                        Forms\Components\TextInput::make('text')
                            ->required()
                            ->maxLength(50)
                            ->placeholder('Label'),
                        Forms\Components\Select::make('color')
                            ->options([
                                'primary' => 'Primary (Blue)',
                                'secondary' => 'Secondary (Gray)',
                                'success' => 'Success (Green)',
                                'warning' => 'Warning (Yellow)',
                                'danger' => 'Danger (Red)',
                                'info' => 'Info (Cyan)',
                                'gray' => 'Gray',
                            ])
                            ->default('primary')
                            ->required(),
                    ])
                    ->columns(2)
                    ->itemLabel(fn (array $state): ?string => $state['text'] ?? null)
                    ->afterStateHydrated(function ($component, $record) {
                        if ($record && $record->type === 'badges' && $this->getOptionsForKey($record->key) === null) {
                            $state = $record->value;
                            if (is_string($state) && $state !== '') {
                                $decoded = json_decode($state, true);
                                if (json_last_error() === JSON_ERROR_NONE) {
                                    $component->state($decoded);
                                }
                            } elseif (is_array($state)) {
                                $component->state($state);
                            } else {
                                $component->state([]);
                            }
                        } else {
                            $component->state([]);
                        }
                    }),

                // Conditional field for JSON
                Forms\Components\Textarea::make('value_json')
                    ->label('Value')
                    ->visible(fn (Get $get) => $get('type') === 'json')
                    ->afterStateHydrated(function ($component, $record) {
                        if ($record && $record->type === 'json') {
                            $state = $record->value;
                            if (is_array($state) || is_object($state)) {
                                $component->state(json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
                            } else {
                                $component->state((string) $state);
                            }
                        }
                    })
                    ->rules([
                        fn () => function (string $attribute, $value, \Closure $fail) {
                            if (is_string($value) && $value !== '') {
                                json_decode($value);
                                if (json_last_error() !== JSON_ERROR_NONE) {
                                    $fail('The :attribute field must be a valid JSON string.');
                                }
                            }
                        }
                    ]),

                // Default fallback for String and custom types
                Forms\Components\TextInput::make('value_string')
                    ->label('Value')
                    ->visible(fn (Get $get) => !in_array($get('type'), ['boolean', 'json', 'integer', 'badges'])
                        && $this->getOptionsForKey($get('key')) === null)
                    ->afterStateHydrated(function ($component, $record) {
                        if ($record && !in_array($record->type, ['boolean', 'json', 'integer', 'badges'])
                            && $this->getOptionsForKey($record->key) === null) {
                            $component->state((string) $record->value);
                        }
                    }),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('key')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold'),

                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->color('info')
                    ->sortable(),

                Tables\Columns\TextColumn::make('value')
                    ->state(function (Model $record) {
                        if ($record->type === 'badges' && is_array($record->value)) {
                            return array_column($record->value, 'text');
                        }
                        if ($record->type === 'boolean') {
                            return $record->value ? 'true' : 'false';
                        }
                        if ($record->type === 'json' || is_array($record->value) || is_object($record->value)) {
                            return json_encode($record->value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                        }
                        return (string) $record->value;
                    })
                    ->badge(fn (Model $record): bool => $record->type === 'badges')
                    ->color(function (string | array $state, Model $record) {
                        if ($record->type === 'badges' && is_string($state) && is_array($record->value)) {
                            foreach ($record->value as $badge) {
                                if (($badge['text'] ?? null) === $state) {
                                    return $badge['color'] ?? 'primary';
                                }
                            }
                        }
                        return 'gray';
                    })
                    ->limit(50)
                    ->searchable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options(function () {
                        $types = array_keys(app(TypeRegistry::class)->all());
                        return array_combine($types, array_map('ucfirst', $types));
                    }),
            ])
            ->checkIfRecordIsSelectableUsing(
                fn (Model $record): bool => !$this->isMetadataKeyRequired($record)
            )
            ->headerActions([
                Actions\CreateAction::make()
                    ->mutateFormDataUsing(fn (array $data): array => $this->mutateMetadataValue($data)),
            ])
            ->recordActions([
                Actions\EditAction::make()
                    ->mutateFormDataUsing(fn (array $data, Model $record): array => $this->mutateMetadataValue(
                        $this->preventRequiredKeyRename($data, $record)
                    )),
                Actions\DeleteAction::make()
                    ->hidden(fn (Model $record): bool => $this->isMetadataKeyRequired($record)),
            ])
            ->toolbarActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Determine whether a metadata row's key is declared as required (via #[RequiresMetadata]
     * or a scope-matching metadata_schemas row) on the owning record, and therefore must not
     * be deletable from this relation manager.
     */
    protected function isMetadataKeyRequired(Model $record): bool
    {
        $definitions = $this->getOwnerRecord()->resolvedMetadataDefinitions();

        return isset($definitions[$record->key]) && $definitions[$record->key]->required;
    }

    /**
     * Force a required row's 'key' and 'type' back to their current, unmodified values before
     * an edit is saved. Renaming a required key, or changing its type, away from what the
     * schema declares is functionally equivalent to deleting it — which is already blocked —
     * so it must not be reachable via an edit either. This is enforced here (server-side) since
     * the form's disabled() on these fields is only a client-side UI cue.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function preventRequiredKeyRename(array $data, Model $record): array
    {
        if ($this->isMetadataKeyRequired($record)) {
            $data['key'] = $record->key;
            $data['type'] = $record->type;
        }

        return $data;
    }

    /**
     * Resolve the effective options for a key on the owning record, if any.
     *
     * @return array<int, mixed>|null
     */
    protected function getOptionsForKey(?string $key): ?array
    {
        if (!$key) {
            return null;
        }

        $options = $this->getOwnerRecord()->resolvedMetadataDefinitions()[$key]->options ?? null;

        // An empty list is never a meaningful constraint — treat it as "unconstrained" so the
        // form falls back to a free-text input instead of an unusable, option-less Select.
        return $options === [] ? null : $options;
    }

    /**
     * Mutate form data to map the temporary unique fields to the single 'value' field.
     */
    protected function mutateMetadataValue(array $data): array
    {
        $type = $data['type'] ?? 'string';
        $options = $this->getOptionsForKey($data['key'] ?? null);

        if ($options !== null && in_array($type, ['string', 'integer', 'boolean'], true)) {
            $data['value'] = app(TypeRegistry::class)->get($type)->cast($data['value_select'] ?? null);
            unset($data['value_boolean'], $data['value_integer'], $data['value_badges'], $data['value_json'], $data['value_string'], $data['value_select'], $data['value_multiselect']);

            return $data;
        }

        if ($options !== null && $type === 'badges') {
            $data['value'] = $data['value_multiselect'] ?? [];
            unset($data['value_boolean'], $data['value_integer'], $data['value_badges'], $data['value_json'], $data['value_string'], $data['value_select'], $data['value_multiselect']);

            return $data;
        }

        switch ($type) {
            case 'boolean':
                $data['value'] = (bool) ($data['value_boolean'] ?? false);
                break;
            case 'integer':
                $data['value'] = isset($data['value_integer']) && $data['value_integer'] !== '' ? (int) $data['value_integer'] : null;
                break;
            case 'badges':
                $data['value'] = $data['value_badges'] ?? [];
                break;
            case 'json':
                $json = $data['value_json'] ?? '';
                if (is_string($json) && $json !== '') {
                    $decoded = json_decode($json, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $data['value'] = $decoded;
                    } else {
                        $data['value'] = $json;
                    }
                } else {
                    $data['value'] = $json;
                }
                break;
            default:
                $data['value'] = $data['value_string'] ?? null;
                break;
        }

        // Clean up temporary fields so they are not saved as database columns
        unset($data['value_boolean'], $data['value_integer'], $data['value_badges'], $data['value_json'], $data['value_string'], $data['value_select'], $data['value_multiselect']);

        return $data;
    }
}
