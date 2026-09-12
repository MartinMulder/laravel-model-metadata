<?php

namespace MartinMulder\LaravelModelMetadata\Traits;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\DB;
use MartinMulder\LaravelModelMetadata\Attributes\RequiresMetadata;
use MartinMulder\LaravelModelMetadata\Exceptions\RequiredMetadataKeyException;
use MartinMulder\LaravelModelMetadata\Models\Metadata;
use MartinMulder\LaravelModelMetadata\Models\MetadataSchema;
use ReflectionAttribute;
use ReflectionClass;

trait HasMetadata
{
    /**
     * @var array<class-string, array<string, RequiresMetadata>>
     */
    protected static array $requiredMetadataDefinitionsCache = [];

    /**
     * Boot the HasMetadata trait for the model.
     * Registers the enforcement of any #[RequiresMetadata] declarations on model creation.
     */
    public static function bootHasMetadata(): void
    {
        static::created(function (self $model): void {
            $model->syncRequiredMetadata();
        });
    }

    /**
     * Boot/initialize the HasMetadata trait for the model.
     * This automatically appends 'metadata' to the eager-loaded relations,
     * ensuring N+1 queries are prevented for any model using this trait.
     */
    public function initializeHasMetadata(): void
    {
        $this->with[] = 'metadata';
    }

    /**
     * Get this model's declared #[RequiresMetadata] definitions, keyed by metadata key.
     *
     * @return array<string, RequiresMetadata>
     */
    public static function requiredMetadataDefinitions(): array
    {
        return static::$requiredMetadataDefinitionsCache[static::class] ??= collect(
            (new ReflectionClass(static::class))->getAttributes(RequiresMetadata::class)
        )
            ->map(fn (ReflectionAttribute $attribute) => $attribute->newInstance())
            ->keyBy(fn (RequiresMetadata $definition) => $definition->key)
            ->all();
    }

    /**
     * The scope this instance belongs to, for resolving scope-specific metadata schemas.
     * Models that don't need per-instance/per-group schema variation can leave this as null,
     * in which case only class-level #[RequiresMetadata] definitions and global (scope-less)
     * metadata_schemas rows apply.
     */
    public function metadataScope(): ?string
    {
        return null;
    }

    /**
     * Per-instance cache for resolvedMetadataDefinitions(), so repeated calls against the same
     * instance (e.g. several setMetadata() calls in a row) don't re-query metadata_schemas.
     *
     * @var array<string, RequiresMetadata>|null
     */
    protected ?array $resolvedMetadataDefinitionsCache = null;

    /**
     * Resolve this instance's effective metadata definitions: class-level #[RequiresMetadata]
     * attributes as the global fallback, overridden/extended per key by any metadata_schemas
     * rows matching this instance's owner_type and scope (or the scope-less fallback rows).
     *
     * @return array<string, RequiresMetadata>
     */
    public function resolvedMetadataDefinitions(): array
    {
        if ($this->resolvedMetadataDefinitionsCache !== null) {
            return $this->resolvedMetadataDefinitionsCache;
        }

        $definitions = static::requiredMetadataDefinitions();

        $scope = $this->metadataScope();

        MetadataSchema::query()
            ->where('owner_type', static::class)
            ->where(function ($query) use ($scope) {
                $query->whereNull('scope')
                    ->when($scope !== null, fn ($q) => $q->orWhere('scope', $scope));
            })
            ->orderByRaw('scope IS NULL DESC')
            ->get()
            ->each(function (MetadataSchema $row) use (&$definitions) {
                $definitions[$row->key] = $row->toDefinition();
            });

        return $this->resolvedMetadataDefinitionsCache = $definitions;
    }

    /**
     * Create any declared required metadata rows that are missing for this instance.
     * Existing rows are left untouched — this only fills gaps, it never overwrites a value.
     */
    public function syncRequiredMetadata(): void
    {
        $definitions = collect($this->resolvedMetadataDefinitions())
            ->filter(fn (RequiresMetadata $definition) => $definition->required);

        if ($definitions->isEmpty()) {
            return;
        }

        $existingKeys = $this->relationLoaded('metadata')
            ? $this->metadata->pluck('key')
            : $this->metadata()->pluck('key');

        $missing = $definitions->reject(
            fn (RequiresMetadata $definition) => $existingKeys->contains($definition->key)
        );

        if ($missing->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($missing): void {
            foreach ($missing as $definition) {
                $this->metadata()->create([
                    'key' => $definition->key,
                    'value' => $definition->default,
                    'type' => $definition->type,
                ]);
            }
        });

        $this->load('metadata');
    }

    /**
     * Get all metadata associated with the model.
     */
    public function metadata(): MorphMany
    {
        // ->inverse('model') hydrates the morphTo side on rows created through this relation,
        // so the owner-lookup inside Metadata's saving hook (options/scope resolution) doesn't
        // have to re-query the database for an owner we already have in memory.
        return $this->morphMany(Metadata::class, 'model')->inverse('model');
    }

    /**
     * Retrieve the casted value of a metadata key.
     */
    public function getMetadata(string $key, mixed $default = null): mixed
    {
        // Operate on the loaded collection to prevent N+1 queries.
        $metadata = $this->metadata->firstWhere('key', $key);

        return $metadata ? $metadata->value : $default;
    }

    /**
     * Set a metadata key to a specific value and type.
     */
    public function setMetadata(string $key, mixed $value, string $type = 'string'): Metadata
    {
        $metadata = $this->metadata()->updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'type' => $type]
         );

        // Reload the relation to keep the in-memory cache synchronized and up-to-date.
        $this->load('metadata');

        return $metadata;
    }

    /**
     * Determine if a metadata key exists.
     */
    public function hasMetadata(string $key): bool
    {
        // Check the loaded collection.
        return $this->metadata->contains('key', $key);
    }

    /**
     * Delete a metadata key.
     *
     * @throws RequiredMetadataKeyException if the key is declared as required (via
     *   #[RequiresMetadata] or a scope-matching metadata_schemas row).
     */
    public function deleteMetadata(string $key): bool
    {
        $definitions = $this->resolvedMetadataDefinitions();

        if (isset($definitions[$key]) && $definitions[$key]->required) {
            throw RequiredMetadataKeyException::forKey($key, static::class);
        }

        $deleted = $this->metadata()->where('key', $key)->delete();

        if ($deleted) {
            // Reload the relation to keep the in-memory cache synchronized.
            $this->load('metadata');
            return true;
        }

        return false;
    }
}
