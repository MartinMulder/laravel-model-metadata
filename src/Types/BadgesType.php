<?php

namespace MartinMulder\LaravelModelMetadata\Types;

use MartinMulder\LaravelModelMetadata\Contracts\MetadataType;

class BadgesType implements MetadataType
{
    /**
     * Cast the database value to a PHP representation (array of badges).
     */
    public function cast(mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            return $value;
        }

        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Serialize the PHP value to a database string (JSON representation).
     *
     * Accepts two shorthand forms besides the full array-of-badges shape:
     * a bare string becomes a single badge, and a string item inside an
     * array becomes a badge with that text and no color.
     */
    public function serialize(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return json_encode([['text' => $value]]);
            }
            $value = $decoded;
        }

        if (is_array($value)) {
            $value = array_map(
                fn ($item) => is_string($item) ? ['text' => $item] : $item,
                $value
            );
        }

        return json_encode($value);
    }

    /**
     * Validate that the value matches the badges structure.
     *
     * Accepts the same two shorthand forms as serialize(): a bare string,
     * or an array whose items may be plain strings instead of {text,color?}.
     */
    public function validate(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        $data = $value;
        if (is_string($value)) {
            $data = json_decode($value, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return trim($value) !== '';
            }
        }

        if (!is_array($data)) {
            return false;
        }

        // Each badge must be a non-empty string, or an array containing a 'text' key.
        foreach ($data as $item) {
            if (is_string($item)) {
                if (trim($item) === '') {
                    return false;
                }
                continue;
            }

            if (!is_array($item)) {
                return false;
            }
            if (!isset($item['text']) || !is_string($item['text']) || trim($item['text']) === '') {
                return false;
            }
            if (isset($item['color']) && !is_string($item['color'])) {
                return false;
            }
        }

        return true;
    }
}
