<?php

namespace MartinMulder\LaravelModelMetadata\Types;

use MartinMulder\LaravelModelMetadata\Contracts\MetadataType;

class StringType implements MetadataType
{
    public function cast(mixed $value): ?string
    {
        return $value === null ? null : (string) $value;
    }

    public function serialize(mixed $value): ?string
    {
        return $value === null ? null : (string) $value;
    }

    public function validate(mixed $value): bool
    {
        return $value === null || is_scalar($value);
    }
}
