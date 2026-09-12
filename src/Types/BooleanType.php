<?php

namespace MartinMulder\LaravelModelMetadata\Types;

use MartinMulder\LaravelModelMetadata\Contracts\MetadataType;

class BooleanType implements MetadataType
{
    public function cast(mixed $value): ?bool
    {
        if ($value === null) {
            return null;
        }
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public function serialize(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $boolean = filter_var($value, FILTER_VALIDATE_BOOLEAN);
        return $boolean ? '1' : '0';
    }

    public function validate(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }
        if (is_bool($value)) {
            return true;
        }
        if (is_scalar($value)) {
            $filtered = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            return $filtered !== null;
        }
        return false;
    }
}
