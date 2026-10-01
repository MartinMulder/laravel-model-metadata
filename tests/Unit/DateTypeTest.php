<?php

use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use MartinMulder\LaravelModelMetadata\Types\DateType;

it('casts stored dates to the start of that day', function () {
    $date = (new DateType)->cast('2026-03-01');

    expect($date)->toBeInstanceOf(CarbonImmutable::class)
        ->and($date->format('Y-m-d H:i:s'))->toBe('2026-03-01 00:00:00')
        ->and((new DateType)->cast(null))->toBeNull()
        ->and((new DateType)->cast(''))->toBeNull();
});

it('serializes dates, date-times and date objects as Y-m-d without shifting the day', function (mixed $input, ?string $expected) {
    expect((new DateType)->serialize($input))->toBe($expected);
})->with([
    'date string' => ['2026-03-01', '2026-03-01'],
    'iso date-time with zone' => ['2026-03-01T23:30:00+02:00', '2026-03-01'],
    'date-time with space' => ['2026-03-01 08:00:00', '2026-03-01'],
    'carbon' => [Carbon::create(2026, 12, 31, 18, 45), '2026-12-31'],
    'null' => [null, null],
    'empty' => ['', null],
]);

it('accepts real dates only', function (mixed $input, bool $valid) {
    expect((new DateType)->validate($input))->toBe($valid);
})->with([
    ['2026-02-28', true],
    ['2024-02-29', true],
    [null, true],
    ['', true],
    [new DateTimeImmutable('2026-01-01'), true],
    ['2026-02-30', false],
    ['01-03-2026', false],
    ['tomorrow', false],
    ['onzin', false],
    [20260301, false],
    [['2026-03-01'], false],
]);
