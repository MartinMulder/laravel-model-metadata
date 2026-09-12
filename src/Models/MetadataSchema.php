<?php

namespace MartinMulder\LaravelModelMetadata\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use MartinMulder\LaravelModelMetadata\Attributes\RequiresMetadata;
use MartinMulder\LaravelModelMetadata\TypeRegistry;

#[Fillable(['owner_type', 'scope', 'key', 'type', 'default', 'options', 'required', 'sort_order'])]
class MetadataSchema extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'metadata_schemas';

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'options' => 'array',
            'required' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Convert this schema row into the same value object the #[RequiresMetadata]
     * attribute mechanism produces, so downstream consumers don't need to know
     * whether a definition came from a class attribute or a database row.
     */
    public function toDefinition(): RequiresMetadata
    {
        $registry = app(TypeRegistry::class);
        $default = $registry->has($this->type)
            ? $registry->get($this->type)->cast($this->default)
            : $this->default;

        return new RequiresMetadata(
            key: $this->key,
            default: $default,
            type: $this->type,
            options: $this->options,
            required: $this->required,
        );
    }
}
