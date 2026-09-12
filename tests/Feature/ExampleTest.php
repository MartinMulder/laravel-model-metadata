<?php

test('it can access the configuration settings', function () {
    $isEnabled = config('laravel-model-metadata.enabled');
    $exampleSetting = config('laravel-model-metadata.example_setting');

    expect($isEnabled)->toBeTrue();
    expect($exampleSetting)->toBe('default_value');
});
