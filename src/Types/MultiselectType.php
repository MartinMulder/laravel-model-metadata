<?php

namespace MartinMulder\LaravelModelMetadata\Types;

use MartinMulder\LaravelModelMetadata\Contracts\MetadataType;

/**
 * A list of chosen values, typically the keys of the field's options (e.g. slugs from an option
 * source): ["logging-monitoring", "incidenten-datalekken"]. Stored as a JSON array of strings;
 * labels are looked up from the options when displayed, so renaming a choice needs no data change.
 */
class MultiselectType implements MetadataType
{
    /** @return list<string>|null */
    public function cast(mixed $value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        return is_array($value) ? $this->normalize($value) : null;
    }

    /** Accepts a list (or a JSON string of one); a bare string becomes a single choice. */
    public function serialize(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [$value];
        }

        return json_encode($this->normalize((array) $value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function validate(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return trim($value) !== '';
            }
            $value = $decoded;
        }

        if (!is_array($value)) {
            return false;
        }

        foreach ($value as $item) {
            if (!is_string($item) && !is_int($item)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<mixed>  $value
     * @return list<string>
     */
    private function normalize(array $value): array
    {
        return array_values(array_unique(array_map(
            fn ($item) => (string) $item,
            array_filter($value, fn ($item) => is_string($item) || is_int($item)),
        )));
    }
}
