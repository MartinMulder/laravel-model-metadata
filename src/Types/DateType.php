<?php

namespace MartinMulder\LaravelModelMetadata\Types;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use MartinMulder\LaravelModelMetadata\Contracts\MetadataType;
use Throwable;

/**
 * A calendar date without a time. Stored as "Y-m-d" (ISO 8601), so stored values compare and sort
 * correctly as strings — e.g. `where('value', '<=', today()->toDateString())`. Read back as a
 * CarbonImmutable at the start of that day.
 */
class DateType implements MetadataType
{
    public function cast(mixed $value): ?CarbonImmutable
    {
        return $this->toDate($value);
    }

    public function serialize(mixed $value): ?string
    {
        return $this->toDate($value)?->format('Y-m-d');
    }

    public function validate(mixed $value): bool
    {
        if ($value === null || $value === '' || $value instanceof DateTimeInterface) {
            return true;
        }

        return is_string($value) && $this->toDate($value) !== null;
    }

    /**
     * Accepts a DateTimeInterface, "Y-m-d", or an ISO 8601 date-time ("2026-03-01T10:00:00+01:00",
     * as date pickers and JSON produce); the time is dropped. Anything else is not a date.
     */
    private function toDate(mixed $value): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value)->startOfDay();
        }

        if (! is_string($value) || ! preg_match('/^(\d{4})-(\d{2})-(\d{2})(?:[ T].*)?$/', trim($value), $m)) {
            return null;
        }

        if (! checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return null;
        }

        try {
            // Only the date part: a time zone in the input must not shift the day.
            return CarbonImmutable::createFromFormat('!Y-m-d', "{$m[1]}-{$m[2]}-{$m[3]}");
        } catch (Throwable) {
            return null;
        }
    }
}
