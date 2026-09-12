# Laravel Model Metadata

[![Latest Version on Packagist](https://img.shields.io/packagist/v/martinmulder/laravel-model-metadata.svg?style=flat-square)](https://packagist.org/packages/martinmulder/laravel-model-metadata)
[![Total Downloads](https://img.shields.io/packagist/dt/martinmulder/laravel-model-metadata.svg?style=flat-square)](https://packagist.org/packages/martinmulder/laravel-model-metadata)
[![Tests](https://img.shields.io/github/actions/workflow/status/MartinMulder/laravel-model-metadata/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/MartinMulder/laravel-model-metadata/actions)

Attach flexible, typed, polymorphic metadata to any Eloquent model — with class-based casting,
strict validation, N+1 prevention out of the box, and an optional Filament v5 integration.

Metadata values are stored per-instance from the start (`setMetadata()`/`getMetadata()` on a
single record). On top of that, a model can declare which metadata keys are *required*, what
their allowed values are, and what type they cast to — either statically via a repeatable
`#[RequiresMetadata]` PHP attribute on the model class, or dynamically via `metadata_schemas`
database rows scoped per group of instances (e.g. per tenant, per category) — so different
groups of the same model can each have their own set of required fields and options.

---

## Requirements

- PHP ^8.3 or ^8.4
- Laravel 13 (`illuminate/support` ^13.0)
- `filament/filament` ^5.0, only if you use the optional Filament relation manager / resource

---

## Installation

You can install the package via composer:

```bash
composer require martinmulder/laravel-model-metadata
```

---

## Quick start

Add the `HasMetadata` trait to any Eloquent model:

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use MartinMulder\LaravelModelMetadata\Traits\HasMetadata;

class Product extends Model
{
    use HasMetadata;
}
```

Then set and read metadata per record:

```php
$product = Product::find(1);

$product->setMetadata('short_description', 'High quality premium product', 'string');
$product->getMetadata('short_description'); // 'High quality premium product'
```

That's the basics — see **[USAGE.md](USAGE.md)** for the full guide, including:
- All built-in value types (`string`, `integer`, `boolean`, `json`, `badges`) and how to
  register your own.
- Declaring required metadata keys, defaults, and allowed options with `#[RequiresMetadata]`.
- Scope-aware metadata schemas (`metadata_schemas` + `metadataScope()`) for models where
  different groups of instances need different required fields/options.
- The bundled `MetadataRelationManager` and `MetadataSchemaResource` for FilamentPHP v5.

---

## Configuration

Publish the config file if you want to register custom metadata types:

```bash
php artisan vendor:publish --tag="laravel-model-metadata-config"
```

This creates `config/laravel-model-metadata.php`, which maps type names to the classes that
implement them:

```php
return [
    'types' => [
        'string' => \MartinMulder\LaravelModelMetadata\Types\StringType::class,
        'integer' => \MartinMulder\LaravelModelMetadata\Types\IntegerType::class,
        'boolean' => \MartinMulder\LaravelModelMetadata\Types\BooleanType::class,
        'json' => \MartinMulder\LaravelModelMetadata\Types\JsonType::class,
        'badges' => \MartinMulder\LaravelModelMetadata\Types\BadgesType::class,
    ],
];
```

Add your own entry here to register a custom type — see USAGE.md for a worked example.

---

## Testing

```bash
composer install
vendor/bin/pest
```

---

## License

The MIT License (MIT). Please see the [LICENSE](LICENSE) file for more information.
