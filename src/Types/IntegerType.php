<?php

namespace MartinMulder\LaravelModelMetadata\Types;

use MartinMulder\LaravelModelMetadata\Contracts\MetadataType;

class IntegerType implements MetadataType
{
    public function cast(mixed $value): ?int
    {
        return $value === null ? null : (int) $value;
    }

    public function serialize(mixed $value): ?string
    {
        return $value === null ? null : (string) $value;
    }

    public function validate(mixed $value): bool
    {
        return $value === null || is_numeric($value);
    }
}
