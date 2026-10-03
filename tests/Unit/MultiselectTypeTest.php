<?php

use MartinMulder\LaravelModelMetadata\Types\MultiselectType;

it('serializes a list of choices as a JSON array of strings', function () {
    $type = new MultiselectType;

    expect($type->serialize(['logging', 'incidenten', 'logging', 3]))->toBe('["logging","incidenten","3"]')
        ->and($type->serialize('logging'))->toBe('["logging"]')
        ->and($type->serialize(null))->toBeNull();
});

it('casts stored JSON back to a list', function () {
    $type = new MultiselectType;

    expect($type->cast('["logging","incidenten"]'))->toBe(['logging', 'incidenten'])
        ->and($type->cast(null))->toBeNull()
        ->and($type->cast('niet-json'))->toBeNull();
});

it('accepts lists of strings and integers only', function () {
    $type = new MultiselectType;

    expect($type->validate(['a', 1]))->toBeTrue()
        ->and($type->validate(null))->toBeTrue()
        ->and($type->validate('a'))->toBeTrue()
        ->and($type->validate([['text' => 'a']]))->toBeFalse()
        ->and($type->validate(12))->toBeFalse();
});
