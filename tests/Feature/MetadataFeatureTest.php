<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use MartinMulder\LaravelModelMetadata\Contracts\MetadataType;
use MartinMulder\LaravelModelMetadata\Models\Metadata;
use MartinMulder\LaravelModelMetadata\Traits\HasMetadata;
use MartinMulder\LaravelModelMetadata\TypeRegistry;

uses(RefreshDatabase::class);

/**
 * Concrete Eloquent model for testing.
 */
class TestModel extends Model
{
    use HasMetadata;

    protected $table = 'test_models';
    protected $fillable = ['name'];
}

/**
 * Custom MetadataType implementation for testing custom type registration.
 */
class UpperStringType implements MetadataType
{
    public function cast(mixed $value): ?string
    {
        return $value === null ? null : strtoupper((string) $value);
    }

    public function serialize(mixed $value): ?string
    {
        return $value === null ? null : (string) $value;
    }

    public function validate(mixed $value): bool
    {
        return $value === null || is_string($value);
    }
}

it('can set, check existence, retrieve, and delete metadata on a model', function () {
    $model = TestModel::create(['name' => 'Standard Model']);

    // Check existence initially
    expect($model->hasMetadata('description'))->toBeFalse();
    expect($model->getMetadata('description'))->toBeNull();
    expect($model->getMetadata('description', 'default'))->toBe('default');

    // Set metadata
    $metadata = $model->setMetadata('description', 'This is a test model', 'string');
    expect($metadata)->toBeInstanceOf(Metadata::class);
    expect($metadata->key)->toBe('description');
    expect($metadata->type)->toBe('string');
    expect($metadata->value)->toBe('This is a test model');

    // Verify retrieval
    expect($model->hasMetadata('description'))->toBeTrue();
    expect($model->getMetadata('description'))->toBe('This is a test model');

    // Delete metadata
    $deleted = $model->deleteMetadata('description');
    expect($deleted)->toBeTrue();
    expect($model->hasMetadata('description'))->toBeFalse();
    expect($model->getMetadata('description'))->toBeNull();
});

it('correctly casts basic data types based on type configuration', function () {
    $model = TestModel::create(['name' => 'Types Model']);

    // String Type
    $model->setMetadata('string_key', 'Hello World', 'string');
    expect($model->getMetadata('string_key'))->toBeString();
    expect($model->getMetadata('string_key'))->toBe('Hello World');

    // Integer Type
    $model->setMetadata('int_key', 42, 'integer');
    expect($model->getMetadata('int_key'))->toBeInt();
    expect($model->getMetadata('int_key'))->toBe(42);

    // Boolean Type
    $model->setMetadata('bool_true', true, 'boolean');
    expect($model->getMetadata('bool_true'))->toBeBool();
    expect($model->getMetadata('bool_true'))->toBeTrue();

    $model->setMetadata('bool_false', false, 'boolean');
    expect($model->getMetadata('bool_false'))->toBeBool();
    expect($model->getMetadata('bool_false'))->toBeFalse();

    // Json/Array Type
    $nestedData = ['nested' => ['array' => 'value'], 'number' => 123];
    $model->setMetadata('json_key', $nestedData, 'json');
    expect($model->getMetadata('json_key'))->toBeArray();
    expect($model->getMetadata('json_key'))->toBe($nestedData);
});

it('throws validation exceptions when storing invalid values for types', function () {
    $model = TestModel::create(['name' => 'Validation Model']);

    // String accepts scalar/null, should throw if we pass an array
    expect(fn () => $model->setMetadata('str_key', ['invalid_array'], 'string'))
        ->toThrow(\InvalidArgumentException::class, "Invalid value for metadata type 'string'");

    // Integer should throw if we pass a non-numeric string or array
    expect(fn () => $model->setMetadata('int_key', 'not-a-number', 'integer'))
        ->toThrow(\InvalidArgumentException::class, "Invalid value for metadata type 'integer'");

    // Boolean should throw if we pass an array or un-filterable string
    expect(fn () => $model->setMetadata('bool_key', ['invalid_array'], 'boolean'))
        ->toThrow(\InvalidArgumentException::class, "Invalid value for metadata type 'boolean'");

    // Json/Array should throw if we pass an un-decodable raw object/type
    // Note: scalars are not valid JSON structure typically, but string JSONs are checked
    expect(fn () => $model->setMetadata('json_key', fopen('php://memory', 'r'), 'json'))
        ->toThrow(\InvalidArgumentException::class, "Invalid value for metadata type 'json'");
});

it('avoids N+1 query issues by eager-loading metadata automatically', function () {
    // Create multiple models with metadata
    $model1 = TestModel::create(['name' => 'Model 1']);
    $model1->setMetadata('key1', 'value1');

    $model2 = TestModel::create(['name' => 'Model 2']);
    $model2->setMetadata('key1', 'value2');

    $model3 = TestModel::create(['name' => 'Model 3']);
    $model3->setMetadata('key1', 'value3');

    // Enable the database query log
    DB::enableQueryLog();

    // Fetch all models.
    // Because of initializeHasMetadata(), this should run exactly 2 queries:
    // 1. select * from test_models
    // 2. select * from model_metadata where model_type = ... and model_id in (...)
    $models = TestModel::all();

    expect(DB::getQueryLog())->toHaveCount(2);

    // Flush the query log to start clean
    DB::flushQueryLog();

    // Access the metadata values. This should NOT trigger any extra database queries,
    // because the entire relationship has already been eager-loaded in a single batch query.
    foreach ($models as $model) {
        $value = $model->getMetadata('key1');
        expect($value)->not->toBeNull();
    }

    expect(DB::getQueryLog())->toHaveCount(0);
    DB::disableQueryLog();
});

it('supports registering and utilizing custom metadata types dynamically', function () {
    $registry = app(TypeRegistry::class);

    // Register our custom UpperStringType
    $registry->register('uppercase', UpperStringType::class);
    expect($registry->has('uppercase'))->toBeTrue();

    $model = TestModel::create(['name' => 'Custom Type Model']);
    $model->setMetadata('shout_key', 'hello from test', 'uppercase');

    // It should save the raw string, but when retrieved, cast it to uppercase
    expect($model->getMetadata('shout_key'))->toBe('HELLO FROM TEST');
});

it('updates and validates correctly when changing the metadata type', function () {
    $model = TestModel::create(['name' => 'Type Mutation Model']);
    
    // Save as integer first (using 1, which is also a valid representation for a boolean)
    $metadata = $model->setMetadata('config_val', 1, 'integer');
    expect($model->getMetadata('config_val'))->toBe(1);

    // Now change type to boolean and save
    $metadata->type = 'boolean';
    $metadata->save();

    // Reload relation
    $model->load('metadata');

    // Retrieve should now return a boolean
    expect($model->getMetadata('config_val'))->toBeBool();
    expect($model->getMetadata('config_val'))->toBeTrue();
});

it('correctly casts and validates BadgesType metadata', function () {
    $model = TestModel::create(['name' => 'Badges Model']);

    // Set correct format for badges
    $badges = [
        ['text' => 'New', 'color' => 'info'],
        ['text' => 'Popular', 'color' => 'success'],
    ];

    $model->setMetadata('tags', $badges, 'badges');

    // Retrieve and verify casting
    $retrieved = $model->getMetadata('tags');
    expect($retrieved)->toBeArray();
    expect($retrieved)->toHaveCount(2);
    expect($retrieved[0]['text'])->toBe('New');
    expect($retrieved[0]['color'])->toBe('info');

    // Test validation of invalid badges (missing 'text')
    $invalidBadges = [
        ['color' => 'danger'],
    ];
    expect(fn () => $model->setMetadata('tags_invalid', $invalidBadges, 'badges'))
        ->toThrow(\InvalidArgumentException::class, "Invalid value for metadata type 'badges'");
});

it('accepts a bare string as shorthand for a single uncolored badge', function () {
    $model = TestModel::create(['name' => 'Badges Shorthand Model']);

    $model->setMetadata('tags', 'beschikbaarheid', 'badges');

    $retrieved = $model->getMetadata('tags');
    expect($retrieved)->toBe([['text' => 'beschikbaarheid']]);
});

it('accepts an array of bare strings as shorthand for multiple uncolored badges', function () {
    $model = TestModel::create(['name' => 'Badges Shorthand List Model']);

    $model->setMetadata('tags', ['beschikbaarheid', 'populair'], 'badges');

    $retrieved = $model->getMetadata('tags');
    expect($retrieved)->toBe([
        ['text' => 'beschikbaarheid'],
        ['text' => 'populair'],
    ]);
    expect($retrieved[0])->not->toHaveKey('color');
    expect($retrieved[1])->not->toHaveKey('color');
});

if (class_exists(\Filament\Resources\RelationManagers\RelationManager::class)) {
    class TestMetadataRelationManager extends \MartinMulder\LaravelModelMetadata\Filament\RelationManagers\MetadataRelationManager
    {
        public function testMutateMetadataValue(array $data): array
        {
            return $this->mutateMetadataValue($data);
        }
    }

    it('correctly mutates form data inside the MetadataRelationManager', function () {
        $manager = new TestMetadataRelationManager();

        // Test Boolean
        $data = [
            'type' => 'boolean',
            'value_boolean' => true,
        ];
        $mutated = $manager->testMutateMetadataValue($data);
        expect($mutated)->toHaveKey('value');
        expect($mutated['value'])->toBeTrue();
        expect($mutated)->not->toHaveKey('value_boolean');

        // Test Integer
        $data = [
            'type' => 'integer',
            'value_integer' => '123',
        ];
        $mutated = $manager->testMutateMetadataValue($data);
        expect($mutated['value'])->toBe(123);
        expect($mutated)->not->toHaveKey('value_integer');

        // Test Badges
        $data = [
            'type' => 'badges',
            'value_badges' => [
                ['text' => 'Popular', 'color' => 'success']
            ],
        ];
        $mutated = $manager->testMutateMetadataValue($data);
        expect($mutated['value'])->toBeArray();
        expect($mutated['value'][0]['text'])->toBe('Popular');
        expect($mutated)->not->toHaveKey('value_badges');

        // Test JSON
        $data = [
            'type' => 'json',
            'value_json' => '{"foo":"bar"}',
        ];
        $mutated = $manager->testMutateMetadataValue($data);
        expect($mutated['value'])->toBeArray();
        expect($mutated['value']['foo'])->toBe('bar');
        expect($mutated)->not->toHaveKey('value_json');

        // Test JSON (invalid string fallback)
        $data = [
            'type' => 'json',
            'value_json' => 'invalid-json',
        ];
        $mutated = $manager->testMutateMetadataValue($data);
        expect($mutated['value'])->toBe('invalid-json');

        // Test String (fallback)
        $data = [
            'type' => 'string',
            'value_string' => 'Hello World',
        ];
        $mutated = $manager->testMutateMetadataValue($data);
        expect($mutated['value'])->toBe('Hello World');
        expect($mutated)->not->toHaveKey('value_string');
    });
}
