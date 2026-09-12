<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use MartinMulder\LaravelModelMetadata\Exceptions\RequiredMetadataKeyException;
use MartinMulder\LaravelModelMetadata\Models\MetadataSchema;
use MartinMulder\LaravelModelMetadata\Traits\HasMetadata;

uses(RefreshDatabase::class);

/**
 * A HasMetadata model whose scope is a plain, non-persisted property, set manually per
 * instance before saving — enough to prove metadataScope() drives schema resolution
 * without needing a real "tenant"/"group" column on the fixture table.
 */
class ScopedTestModel extends Model
{
    use HasMetadata;

    protected $table = 'test_models';
    protected $fillable = ['name'];

    public ?string $scope = null;

    public function metadataScope(): ?string
    {
        return $this->scope;
    }
}

it('resolves different metadata definitions for different instances based on scope', function () {
    MetadataSchema::create([
        'owner_type' => ScopedTestModel::class,
        'scope' => null,
        'key' => 'shared',
        'type' => 'string',
        'default' => 'global-default',
        'required' => true,
    ]);

    MetadataSchema::create([
        'owner_type' => ScopedTestModel::class,
        'scope' => 'tenant-a',
        'key' => 'shared',
        'type' => 'string',
        'default' => 'a-default',
        'options' => ['a-default', 'other-a'],
        'required' => true,
    ]);

    MetadataSchema::create([
        'owner_type' => ScopedTestModel::class,
        'scope' => 'tenant-b',
        'key' => 'only-b',
        'type' => 'string',
        'default' => 'b-default',
        'required' => false,
    ]);

    $modelA = new ScopedTestModel(['name' => 'A']);
    $modelA->scope = 'tenant-a';
    $modelA->save();

    $modelB = new ScopedTestModel(['name' => 'B']);
    $modelB->scope = 'tenant-b';
    $modelB->save();

    // Tenant A: 'shared' is overridden with its own default/options, 'only-b' does not apply.
    $definitionsA = $modelA->resolvedMetadataDefinitions();
    expect($definitionsA)->toHaveKey('shared');
    expect($definitionsA)->not->toHaveKey('only-b');
    expect($definitionsA['shared']->default)->toBe('a-default');
    expect($definitionsA['shared']->options)->toBe(['a-default', 'other-a']);
    expect($modelA->getMetadata('shared'))->toBe('a-default');
    expect($modelA->hasMetadata('only-b'))->toBeFalse();

    // Tenant B: 'shared' falls back to the global (scope-less) definition, and 'only-b' applies.
    $definitionsB = $modelB->resolvedMetadataDefinitions();
    expect($definitionsB['shared']->default)->toBe('global-default');
    expect($definitionsB['shared']->options)->toBeNull();
    expect($definitionsB)->toHaveKey('only-b');
    expect($modelB->getMetadata('shared'))->toBe('global-default');
    // 'only-b' is declared but not required, so it is not auto-synced on creation.
    expect($modelB->hasMetadata('only-b'))->toBeFalse();
});

it('allows deleting a metadata key declared with required: false via a metadata_schemas row', function () {
    MetadataSchema::create([
        'owner_type' => ScopedTestModel::class,
        'scope' => 'tenant-b',
        'key' => 'shared',
        'type' => 'string',
        'default' => 'global-default',
        'required' => true,
    ]);

    MetadataSchema::create([
        'owner_type' => ScopedTestModel::class,
        'scope' => 'tenant-b',
        'key' => 'only-b',
        'type' => 'string',
        'default' => 'b-default',
        'required' => false,
    ]);

    $model = new ScopedTestModel(['name' => 'B']);
    $model->scope = 'tenant-b';
    $model->save();

    $model->setMetadata('only-b', 'custom-value', 'string');

    expect($model->deleteMetadata('only-b'))->toBeTrue();
    expect($model->hasMetadata('only-b'))->toBeFalse();

    expect(fn () => $model->deleteMetadata('shared'))
        ->toThrow(RequiredMetadataKeyException::class);
});

it('treats an empty options list as unconstrained instead of rejecting every value', function () {
    // Reproduces a real bug: a TagsInput left empty in the Filament form saves `options` as `[]`,
    // not `null` — which must NOT be interpreted as "no value is ever valid".
    MetadataSchema::create([
        'owner_type' => ScopedTestModel::class,
        'key' => 'reference',
        'type' => 'string',
        'default' => 'first-value',
        'options' => [],
        'required' => true,
    ]);

    $model = new ScopedTestModel(['name' => 'A']);
    $model->save();

    expect($model->getMetadata('reference'))->toBe('first-value');

    $model->setMetadata('reference', 'anything-else', 'string');
    expect($model->getMetadata('reference'))->toBe('anything-else');
});

it('enforces one scope-less fallback schema row per owner_type + key at the database level', function () {
    MetadataSchema::create([
        'owner_type' => ScopedTestModel::class,
        'key' => 'shared',
        'type' => 'string',
        'default' => 'first',
    ]);

    expect(fn () => MetadataSchema::create([
        'owner_type' => ScopedTestModel::class,
        'key' => 'shared',
        'type' => 'string',
        'default' => 'second',
    ]))->toThrow(QueryException::class);
});
