<?php

namespace MartinMulder\LaravelModelMetadata\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class RequiresMetadata
{
    public function __construct(
        public readonly string $key,
        public readonly mixed $default,
        public readonly string $type = 'string',
        public readonly ?array $options = null,
        public readonly bool $required = true,
    ) {
    }
}
