<?php

namespace MartinMulder\LaravelModelMetadata\Types;

use MartinMulder\LaravelModelMetadata\Contracts\MetadataType;

class JsonType implements MetadataType
{
    public function cast(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }
        if (is_array($value) || is_object($value)) {
            return $value;
        }
        return json_decode((string) $value, true);
    }

    public function serialize(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_string($value)) {
            // If it is a string, check if it's already a JSON string.
            // If not, encode it as a string.
            json_decode($value);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $value;
            }
        }
        return json_encode($value);
    }

    public function validate(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }
        if (is_array($value) || is_object($value)) {
            return true;
        }
        if (is_string($value)) {
            // Check if it's a valid JSON string
            json_decode($value);
            return json_last_error() === JSON_ERROR_NONE;
        }
        return false;
    }
}
