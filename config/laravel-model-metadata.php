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
        'date' => \MartinMulder\LaravelModelMetadata\Types\DateType::class,
        'multiselect' => \MartinMulder\LaravelModelMetadata\Types\MultiselectType::class,
    ],

    /**
     * Metadata scopes defined by the host app. Packages register their own scopes in their service
     * provider (MetadataScopes::register()). Each entry is an invokable class returning a
     * MartinMulder\LaravelModelMetadata\Scopes\MetadataScope.
     */
    'scopes' => [
        // App\Metadata\ProjectScope::class,
    ],
];
