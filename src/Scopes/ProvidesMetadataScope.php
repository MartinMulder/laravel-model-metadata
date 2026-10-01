<?php

namespace MartinMulder\LaravelModelMetadata\Scopes;

use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;
use MartinMulder\LaravelModelMetadata\Models\MetadataSchema;

/**
 * For a model whose records are the scope values of another model (registered with
 * MetadataScope::scopedBy()), e.g. DocumentType for Document. Gives it the metadata schema rows
 * of its scope, which the MetadataSchemasRelationManager manages.
 */
trait ProvidesMetadataScope
{
    /**
     * Schema rows for the owner model, within the scope of this record. Creating through this
     * relation fills both owner_type and scope.
     *
     * @return HasMany<MetadataSchema, $this>
     */
    public function metadataSchemas(?string $owner = null): HasMany
    {
        $definition = static::metadataScopeDefinition($owner);

        return $this->hasMany(MetadataSchema::class, 'scope', $definition->getSourceKey())
            ->withAttributes(['owner_type' => $definition->owner])
            ->orderBy('sort_order')
            ->orderBy('key');
    }

    /** The scope definition this model is the source of (the given owner when there are several). */
    public static function metadataScopeDefinition(?string $owner = null): MetadataScope
    {
        $definitions = app(MetadataScopeRegistry::class)->forSource(static::class);

        $definition = $owner !== null
            ? collect($definitions)->first(fn (MetadataScope $scope): bool => $scope->owner === $owner)
            : ($definitions[0] ?? null);

        if ($definition === null) {
            throw new LogicException(static::class . ' is not registered as the scope source of a metadata owner. Register MetadataScope::for(...)->scopedBy(' . class_basename(static::class) . '::class) first.');
        }

        return $definition;
    }
}
