# Usage Guide - Laravel Model Metadata

This package allows you to attach flexible metadata to any Eloquent model with class-based casting, strict validation, and out-of-the-box N+1 query prevention.

---

## 1. Preparation

### Step 1: Use the Trait on your Eloquent Model
Simply add the `HasMetadata` trait to any Eloquent model you wish to attach metadata to:

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use MartinMulder\LaravelModelMetadata\Traits\HasMetadata;

class Product extends Model
{
    use HasMetadata;

    protected $fillable = ['name', 'price'];
}
```

---

## 2. Basic Usage

### Setting Metadata (`setMetadata`)
You can store metadata values with specific types. The package will validate and serialize the values automatically before storing them.

```php
$product = Product::find(1);

// 1. Store a String
$product->setMetadata('short_description', 'High quality premium product', 'string');

// 2. Store an Integer (automatically validated and cast)
$product->setMetadata('stock_limit', 150, 'integer');

// 3. Store a Boolean (automatically validated and cast)
$product->setMetadata('is_exclusive', true, 'boolean');

// 4. Store JSON/Array data (automatically serialized)
$product->setMetadata('specifications', [
    'color' => 'Space Gray',
    'weight_grams' => 850,
    'tags' => ['tech', 'new']
], 'json');
```

> **Note:** If no type is specified, `'string'` is used as the default type.

### Retrieving Metadata (`getMetadata`)
When retrieving metadata, the value is automatically cast back to its original PHP type (e.g., array/object, integer, boolean) based on its defined type:

```php
$shortDesc = $product->getMetadata('short_description'); // returns string: "High quality premium product"
$stockLimit = $product->getMetadata('stock_limit');      // returns integer: 150
$isExclusive = $product->getMetadata('is_exclusive');    // returns boolean: true
$specs = $product->getMetadata('specifications');        // returns array: ['color' => 'Space Gray', ...]

// With default fallback value if key does not exist:
$discount = $product->getMetadata('discount_percentage', 0); // returns 0
```

### Checking for Metadata Existence (`hasMetadata`)
Check if a metadata key exists on the model:

```php
if ($product->hasMetadata('is_exclusive')) {
    // Perform logic...
}
```

### Deleting Metadata (`deleteMetadata`)
Remove metadata for a specific key:

```php
$product->deleteMetadata('is_exclusive'); // returns boolean (true on success)
```

---

## 3. High Performance (N+1 Query Prevention)

Standard polymorphic metadata implementations suffer from N+1 query problems because they trigger a query for every helper call on each model instance. 

This package is designed to **fully prevent N+1 queries** out of the box:
1. **Auto Eager-Loading:** When the `HasMetadata` trait is initialized, it automatically appends `'metadata'` to the model's `$with` relationship property. When fetching models, all associated metadata is eager-loaded in a single query.
2. **Collection Caching:** The helper methods (`getMetadata`, `hasMetadata`, etc.) operate on the loaded Eloquent collection (`$this->metadata`) rather than building database queries.

```php
// Triggers exactly 2 database queries:
// 1. select * from products;
// 2. select * from model_metadata where model_type = ... and model_id in (...);
$products = Product::all();

foreach ($products as $product) {
    // Triggers 0 database queries! It uses the preloaded cache in memory.
    $limit = $product->getMetadata('stock_limit');
}
```

---

## 4. Configuring & Registering Custom Types

The default supported types are configured in `config/laravel-model-metadata.php`:
- `string`
- `integer`
- `boolean`
- `json`
- `badges`

### The `badges` Type

Stores a list of labeled tags, each with an optional color — useful for status/label chips in the
Filament relation manager. The full form is an array of `{text, color?}` items:

```php
$product->setMetadata('tags', [
    ['text' => 'New', 'color' => 'info'],
    ['text' => 'Popular', 'color' => 'success'],
], 'badges');
```

Two shorthands are also accepted when you don't need per-badge colors (they fall back to the
default color wherever badges are rendered):

```php
// A bare string becomes a single badge:
$product->setMetadata('tags', 'beschikbaarheid', 'badges');
// => [['text' => 'beschikbaarheid']]

// An array of strings becomes one badge per string:
$product->setMetadata('tags', ['beschikbaarheid', 'populair'], 'badges');
// => [['text' => 'beschikbaarheid'], ['text' => 'populair']]
```

### Creating a Custom Type
To define a custom metadata type (e.g., to prepare for FilamentPHP custom layout selection in a later phase), follow these steps:

#### 1. Implement the `MetadataType` Contract
Create your custom class and implement `MartinMulder\LaravelModelMetadata\Contracts\MetadataType`:

```php
namespace App\MetadataTypes;

use MartinMulder\LaravelModelMetadata\Contracts\MetadataType;

class ColorPickerType implements MetadataType
{
    public function cast(mixed $value): ?string
    {
        return $value; // e.g. return Hex code
    }

    public function serialize(mixed $value): ?string
    {
        return (string) $value;
    }

    public function validate(mixed $value): bool
    {
        // Simple Hex color validation regex
        return is_string($value) && preg_match('/^#[a-fA-F0-9]{6}$/', $value);
    }
}
```

#### 2. Register the custom type
Add your class to the `'types'` array in `config/laravel-model-metadata.php`:

```php
return [
    'enabled' => true,

    'types' => [
        'string' => \MartinMulder\LaravelModelMetadata\Types\StringType::class,
        'integer' => \MartinMulder\LaravelModelMetadata\Types\IntegerType::class,
        'boolean' => \MartinMulder\LaravelModelMetadata\Types\BooleanType::class,
        'json' => \MartinMulder\LaravelModelMetadata\Types\JsonType::class,
        
        // Your custom type:
        'color' => \App\MetadataTypes\ColorPickerType::class,
    ],
];
```

#### 3. Use it on your model
Now you can set, validate, and fetch values with your custom color type:

```php
// Works:
$product->setMetadata('primary_theme_color', '#ff0000', 'color');

// Throws InvalidArgumentException (invalid format):
$product->setMetadata('primary_theme_color', 'red', 'color');
```

---

## 5. Integrating with FilamentPHP v5

The package provides a fully-featured, high-performance **FilamentPHP v5** compatible relation manager class:
`MartinMulder\LaravelModelMetadata\Filament\RelationManagers\MetadataRelationManager`.

This relation manager supports managing model metadata directly through the Filament panel, with tailored input components matching each metadata type dynamically:
- **Toggle** for `boolean` values.
- **TextInput** with numeric validation for `integer` values.
- **Textarea** with formatting and syntax validation for `json` values.
- **TextInput** as a fallback for strings and custom registered types.

### How to use:

Add the relation manager to your model's Filament Resource class in the `getRelations()` method:

```php
namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\Product;
use Filament\Resources\Resource;
use MartinMulder\LaravelModelMetadata\Filament\RelationManagers\MetadataRelationManager;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    // ... standard form and table configuration

    public static function getRelations(): array
    {
        return [
            MetadataRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
```

Now, when you edit a product, the Filament panel will show a dedicated **Metadata** relation manager where you can add, view, update, or remove metadata keys, automatically respecting the type registry, casting, and polymorphic database unique keys!

> **Note:** rows whose key is declared as required — via `#[RequiresMetadata]` (see [section
> 6](#6-declaring-required-metadata)) or a scope-matching `metadata_schemas` row (see [section
> 7](#7-scope-aware-metadata-schemas-per-instanceper-group)) — cannot be deleted from this relation
> manager: the row delete action and the bulk-selection checkbox are automatically hidden for
> them. Their `key` and `type` fields are also locked (disabled in the form, and enforced
> server-side even if bypassed) — renaming or retyping a required row away from what the schema
> declares would otherwise be equivalent to deleting it.

---

## 6. Declaring Required Metadata

Some models need certain metadata keys to **always** exist — for every instance, from the moment
it's created. Declare this directly on the model class with the repeatable `#[RequiresMetadata]`
attribute:

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use MartinMulder\LaravelModelMetadata\Attributes\RequiresMetadata;
use MartinMulder\LaravelModelMetadata\Traits\HasMetadata;

#[RequiresMetadata(key: 'status', default: 'draft', type: 'string')]
#[RequiresMetadata(key: 'is_featured', default: false, type: 'boolean')]
#[RequiresMetadata(key: 'tags', default: 'beschikbaarheid', type: 'badges')]
class Product extends Model
{
    use HasMetadata;

    protected $fillable = ['name', 'price'];
}
```

Declare as many `#[RequiresMetadata]` attributes as you need — one per key.

By default, a declared key is enforced (auto-populated on creation, undeletable). Pass
`required: false` to declare a key's type/options without enforcing its presence — it behaves
like an ordinary optional `setMetadata()` key, just with validated options if you also set them:

```php
#[RequiresMetadata(key: 'internal_note', default: '', type: 'string', required: false)]
```

> **Note:** `default` must always be supplied, and must be a valid value for the declared `type`
> (the same validation `setMetadata()` uses applies here) — there is no implicit `null` fallback.
> For `type: 'badges'`, `default` can use either shorthand described in
> [section 4](#the-badges-type): a bare string (`'beschikbaarheid'`) for a single badge, or an
> array of strings (`['beschikbaarheid', 'populair']`) for several — both without a color.

### Eager enforcement

Required metadata rows are created **immediately in the database** when the model is created —
not computed on read:

```php
$product = Product::create(['name' => 'Widget', 'price' => 999]);

$product->getMetadata('status');      // 'draft' — already persisted, no extra call needed
$product->getMetadata('is_featured'); // false
```

Updating an existing record does **not** re-trigger this — enforcement only runs on creation.

### Deletion protection

Required keys cannot be removed via `deleteMetadata()`:

```php
$product->deleteMetadata('status'); // throws RequiredMetadataKeyException
```

### Backfilling pre-existing records

Records created **before** a `#[RequiresMetadata]` declaration was added won't have the new
key(s) yet. Call `syncRequiredMetadata()` on an instance to fill in any missing required keys —
existing values are never overwritten:

```php
$product->syncRequiredMetadata();
```

> There is currently no bulk/artisan command to backfill every existing record in a table — call
> `syncRequiredMetadata()` per-record (e.g. from a one-off script) if you need that.

### Restricting to a set of options

Add `options` to constrain which values a required key accepts — for any type, not just `badges`:

```php
#[RequiresMetadata(
    key: 'status',
    default: 'draft',
    type: 'string',
    options: ['draft', 'published', 'archived'],
)]
#[RequiresMetadata(
    key: 'tags',
    default: 'beschikbaarheid',
    type: 'badges',
    options: ['beschikbaarheid', 'populair', 'uitverkocht'],
)]
class Product extends Model
{
    use HasMetadata;

    protected $fillable = ['name', 'price'];
}
```

- For scalar types (`string`, `integer`, `boolean`), the value must equal one of `options`.
- For `badges`, every selected badge's text must be one of `options`.
- `default` itself must be one of `options` too — a misconfigured default fails the same way an
  invalid `setMetadata()` call would, right when the model is created.

```php
$product->setMetadata('status', 'archived'); // ok, 'archived' is in options
$product->setMetadata('status', 'cancelled'); // throws InvalidArgumentException
```

The [Filament relation manager](#5-integrating-with-filamentphp-v5) automatically swaps the
free-text input for a key with declared `options` for a `Select` (scalar types) or a multi-select
(`badges`) populated from those options — so users can only pick a valid value in the first place.
Note that a `badges` key with `options` loses the per-badge custom-color picker in the form (every
selected tag falls back to the default badge color); the free-form Repeater with color picker is
only shown for `badges` keys without declared `options`.

---

## 7. Scope-Aware Metadata Schemas (per-instance/per-group)

`#[RequiresMetadata]` is a PHP attribute, so it is always **class-wide**: every instance of a model
gets exactly the same declared keys, defaults and options. That's fine when one schema fits every
row, but some domains need different instances — or groups of instances — of the *same* model to
have different metadata fields entirely (e.g. records belonging to different tenants, categories,
or frameworks each with their own set of classification fields).

For that, declare schemas as database rows instead of (or alongside) class attributes, using the
`MetadataSchema` model and a `metadataScope()` override on your model.

### Declaring a scope

Override `metadataScope()` on your model to return a string identifying which "group" an instance
belongs to. Return `null` (the default the trait already provides) for instances that should only
see class-attribute definitions and scope-less/global schema rows:

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use MartinMulder\LaravelModelMetadata\Traits\HasMetadata;

class Norm extends Model
{
    use HasMetadata;

    public function metadataScope(): ?string
    {
        return $this->element?->normenkader_id !== null
            ? (string) $this->element->normenkader_id
            : null;
    }
}
```

### Declaring the schema itself

Create `MetadataSchema` rows (directly, via a seeder, or via the bundled
[`MetadataSchemaResource`](#8-managing-schemas-through-filament)) instead of — or in addition to —
`#[RequiresMetadata]` attributes:

```php
use MartinMulder\LaravelModelMetadata\Models\MetadataSchema;

MetadataSchema::create([
    'owner_type' => \App\Models\Norm::class,
    'scope' => '1',                 // e.g. a specific Normenkader's id; null = applies to every instance
    'key' => 'security_domains',
    'type' => 'badges',
    'default' => '[]',
    'options' => ['Governance_en_Ecosysteem', 'Bescherming', 'Verdediging', 'Veerkracht'],
    'required' => true,
]);
```

> **Note:** `default` is stored the same way `Metadata::value` is — pre-serialized, not a raw PHP
> value. For a `required: true` row with `options` set, `default` must itself be one of those
> options (or, for `badges`, an empty list): a plain PHP `null` is **not** a valid badges default
> here and would throw when the first instance in this scope is created — `'[]'` (the serialized
> empty-badges list) passes validation vacuously and reads back as `[]` via `getMetadata()`.

- `scope: null` is the global fallback — it applies to every instance of `owner_type` that has no
  more specific scope row for that key. This is the database equivalent of a class attribute.
- A scope-specific row (`scope` matching `$model->metadataScope()`) overrides a global row or a
  `#[RequiresMetadata]` attribute for the same key. Different scopes can declare entirely different
  keys — nothing requires them to overlap.
- `required` behaves exactly like on the attribute: `true` auto-populates the key with `default` on
  model creation and blocks `deleteMetadata()`; `false` makes the key available and option-validated
  but optional.

### Resolving definitions

Call `resolvedMetadataDefinitions()` on an instance to get its *effective* definitions — attribute
declarations merged with any matching schema rows, scope-specific rows winning on key collisions:

```php
$definitions = $norm->resolvedMetadataDefinitions(); // array<string, RequiresMetadata>, keyed by key
```

`syncRequiredMetadata()`, `deleteMetadata()`, `setMetadata()`'s options validation, and the
[Filament relation manager](#5-integrating-with-filamentphp-v5) all use this resolver automatically
— there is nothing else to wire up once `metadataScope()` is declared. Models that never override
`metadataScope()` see no behavior change: `resolvedMetadataDefinitions()` then only ever matches
scope-less (`scope: null`) rows plus class attributes, which is exactly today's attribute-only
behavior.

---

## 8. Managing Schemas Through Filament

The package ships a full Filament v5 resource for managing `metadata_schemas` rows without writing
code: `MartinMulder\LaravelModelMetadata\Filament\Resources\MetadataSchemas\MetadataSchemaResource`.

Register it on any panel where you want administrators to define or edit scope-aware schemas:

```php
use MartinMulder\LaravelModelMetadata\Filament\Resources\MetadataSchemas\MetadataSchemaResource;

public function panel(Panel $panel): Panel
{
    return $panel
        // ...
        ->resources([
            MetadataSchemaResource::class,
        ]);
}
```

The resource is **not** auto-registered on every panel — register it explicitly, the same way you
register `MetadataRelationManager` on a specific resource's `getRelations()`. It lets you manage
`owner_type`, `scope`, `key`, `type`, `default`, `options` and `required` per row, so a new scope
(e.g. a new Normenkader) can get its own metadata fields without a developer writing a migration.

