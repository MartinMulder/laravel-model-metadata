<?php

return [
    /**
     * Set this to false to disable package features.
     */
    'enabled' => true,

    /**
     * An example configuration setting.
     */
    'example_setting' => 'default_value',

    /**
     * Registered metadata types.
     * You can register custom type classes here. They must implement
     * MartinMulder\LaravelModelMetadata\Contracts\MetadataType.
     */
    'types' => [
        'string' => \MartinMulder\LaravelModelMetadata\Types\StringType::class,
        'integer' => \MartinMulder\LaravelModelMetadata\Types\IntegerType::class,
        'boolean' => \MartinMulder\LaravelModelMetadata\Types\BooleanType::class,
        'json' => \MartinMulder\LaravelModelMetadata\Types\JsonType::class,
        'badges' => \MartinMulder\LaravelModelMetadata\Types\BadgesType::class,
    ],
];
