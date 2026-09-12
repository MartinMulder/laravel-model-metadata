<?php

namespace MartinMulder\LaravelModelMetadata\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use MartinMulder\LaravelModelMetadata\TypeRegistry;

#[Fillable(['key', 'value', 'type'])]
class Metadata extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'model_metadata';

    /**
     * The raw, un-serialized PHP value set by the user.
     */
    protected mixed $rawPhpValue = null;

    /**
     * Whether a raw PHP value has been explicitly set.
     */
    protected bool $isRawPhpValueSet = false;

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::saving(function (Metadata $metadata) {
            $registry = app(TypeRegistry::class);
            $type = $metadata->type ?? 'string';

            // If the type was changed but the value wasn't explicitly set in this instance,
            // we re-validate and re-serialize the existing value under the new type.
            if (!$metadata->isRawPhpValueSet && $metadata->isDirty('type') && array_key_exists('value', $metadata->attributes)) {
                $oldType = $metadata->getOriginal('type') ?? 'string';
                $oldValue = $metadata->getOriginal('value');
                
                // Get the old casted value
                if ($registry->has($oldType)) {
                    $metadata->rawPhpValue = $registry->get($oldType)->cast($oldValue);
                } else {
                    $metadata->rawPhpValue = $oldValue;
                }
                $metadata->isRawPhpValueSet = true;
            }

            if ($metadata->isRawPhpValueSet) {
                if (!$registry->has($type)) {
                    throw new \InvalidArgumentException("Metadata type '{$type}' is not registered.");
                }

                $typeInstance = $registry->get($type);

                if (!$typeInstance->validate($metadata->rawPhpValue)) {
                    throw new \InvalidArgumentException("Invalid value for metadata type '{$type}'.");
                }

                $options = static::requiredOptionsFor($metadata);

                if ($options !== null) {
                    foreach (static::extractComparableValues($metadata->rawPhpValue) as $value) {
                        if (!in_array($value, $options, true)) {
                            throw new \InvalidArgumentException(
                                "Invalid value for metadata key '{$metadata->key}': must be one of [" . implode(', ', $options) . '].'
                            );
                        }
                    }
                }

                $metadata->attributes['value'] = $typeInstance->serialize($metadata->rawPhpValue);
            }
        });
    }

    /**
     * Get the owning model.
     */
    public function model(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Resolve the effective (attribute + scope-specific schema) options for this metadata
     * row's key, if any, from its owning model instance.
     *
     * @return array<int, mixed>|null
     */
    protected static function requiredOptionsFor(self $metadata): ?array
    {
        $owner = $metadata->model;

        if (!$owner || !method_exists($owner, 'resolvedMetadataDefinitions')) {
            return null;
        }

        $options = $owner->resolvedMetadataDefinitions()[$metadata->key]->options ?? null;

        // An empty list is never a meaningful constraint (every value would be rejected) — it's
        // an input artifact (e.g. a TagsInput left empty saves `[]`, not `null`), so treat it the
        // same as "unconstrained".
        return $options === [] ? null : $options;
    }

    /**
     * Flatten a raw metadata value into the atomic values to check against declared options:
     * a scalar stays itself, and an array's items are either themselves (if a string) or their
     * 'text' key (if a badge-shaped array item).
     *
     * @return array<int, mixed>
     */
    protected static function extractComparableValues(mixed $value): array
    {
        if (!is_array($value)) {
            return [$value];
        }

        return array_map(
            fn ($item) => is_array($item) ? ($item['text'] ?? null) : $item,
            $value
        );
    }

    /**
     * Get the casted value attribute.
     */
    public function getValueAttribute($value)
    {
        if ($this->isRawPhpValueSet) {
            return $this->rawPhpValue;
        }

        $registry = app(TypeRegistry::class);
        $type = $this->type ?? 'string';

        if ($registry->has($type)) {
            return $registry->get($type)->cast($value);
        }

        return $value;
    }

    /**
     * Set and store the raw PHP value.
     */
    public function setValueAttribute($value): void
    {
        $this->rawPhpValue = $value;
        $this->isRawPhpValueSet = true;
        $this->attributes['value'] = $value;
    }
}
