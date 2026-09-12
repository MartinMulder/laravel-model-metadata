<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use MartinMulder\LaravelModelMetadata\Attributes\RequiresMetadata;
use MartinMulder\LaravelModelMetadata\Exceptions\RequiredMetadataKeyException;
use MartinMulder\LaravelModelMetadata\Models\Metadata;
use MartinMulder\LaravelModelMetadata\Traits\HasMetadata;

uses(RefreshDatabase::class);

#[RequiresMetadata(key: 'status', default: 'draft', type: 'string')]
#[RequiresMetadata(key: 'is_published', default: false, type: 'boolean')]
class RequiredTestModel extends Model
{
    use HasMetadata;

    protected $table = 'test_models';
    protected $fillable = ['name'];
}

#[RequiresMetadata(key: 'limit', default: 'not-a-number', type: 'integer')]
class MisconfiguredRequiredTestModel extends Model
{
    use HasMetadata;

    protected $table = 'test_models';
    protected $fillable = ['name'];
}

/**
 * A HasMetadata model with no #[RequiresMetadata] declarations, used to prove the
 * per-class definitions cache doesn't leak between model classes.
 */
class PlainTestModel extends Model
{
    use HasMetadata;

    protected $table = 'test_models';
    protected $fillable = ['name'];
}

/**
 * Separate fixture (rather than adding to RequiredTestModel) so the existing
 * query-count assertion on RequiredTestModel stays unaffected.
 */
#[RequiresMetadata(key: 'tags', default: 'beschikbaarheid', type: 'badges')]
class BadgesRequiredTestModel extends Model
{
    use HasMetadata;

    protected $table = 'test_models';
    protected $fillable = ['name'];
}

#[RequiresMetadata(key: 'tags', default: ['beschikbaarheid', 'populair'], type: 'badges')]
class BadgesListRequiredTestModel extends Model
{
    use HasMetadata;

    protected $table = 'test_models';
    protected $fillable = ['name'];
}

#[RequiresMetadata(key: 'status', default: 'draft', type: 'string', options: ['draft', 'published', 'archived'])]
class OptionsRequiredTestModel extends Model
{
    use HasMetadata;

    protected $table = 'test_models';
    protected $fillable = ['name'];
}

#[RequiresMetadata(key: 'tags', default: 'beschikbaarheid', type: 'badges', options: ['beschikbaarheid', 'populair', 'uitverkocht'])]
class BadgesOptionsRequiredTestModel extends Model
{
    use HasMetadata;

    protected $table = 'test_models';
    protected $fillable = ['name'];
}

#[RequiresMetadata(key: 'status', default: 'unknown', type: 'string', options: ['draft', 'published'])]
class MisconfiguredOptionsRequiredTestModel extends Model
{
    use HasMetadata;

    protected $table = 'test_models';
    protected $fillable = ['name'];
}

it('eagerly creates declared required metadata with defaults on model creation', function () {
    $model = RequiredTestModel::create(['name' => 'Product']);

    expect(Metadata::query()
        ->where('model_type', RequiredTestModel::class)
        ->where('model_id', $model->id)
        ->count())->toBe(2);

    expect($model->getMetadata('status'))->toBe('draft');
    expect($model->getMetadata('is_published'))->toBeFalse();
});

it('does not create required metadata rows again on a plain update', function () {
    $model = RequiredTestModel::create(['name' => 'Product']);

    $countAfterCreate = Metadata::query()
        ->where('model_type', RequiredTestModel::class)
        ->where('model_id', $model->id)
        ->count();
    expect($countAfterCreate)->toBe(2);

    $model->update(['name' => 'Product Updated']);

    $countAfterUpdate = Metadata::query()
        ->where('model_type', RequiredTestModel::class)
        ->where('model_id', $model->id)
        ->count();
    expect($countAfterUpdate)->toBe(2);
});

it('creates a bounded, predictable number of queries when enforcing required metadata', function () {
    DB::enableQueryLog();

    RequiredTestModel::create(['name' => 'Product']);

    $queryCount = count(DB::getQueryLog());
    DB::flushQueryLog();
    DB::disableQueryLog();

    // 1 insert for the model itself, 1 select to resolve scope-aware metadata schemas,
    // 1 select to check existing metadata keys, 1 insert per missing required key (2 here),
    // and 1 select to reload the relation.
    expect($queryCount)->toBe(6);
});

it('propagates type validation failures from a misconfigured required-metadata default', function () {
    expect(fn () => MisconfiguredRequiredTestModel::create(['name' => 'Bad Config']))
        ->toThrow(\InvalidArgumentException::class, "Invalid value for metadata type 'integer'");
});

it('refuses to delete a required metadata key', function () {
    $model = RequiredTestModel::create(['name' => 'Product']);

    expect(fn () => $model->deleteMetadata('status'))
        ->toThrow(
            RequiredMetadataKeyException::class,
            "The metadata key 'status' is required on [" . RequiredTestModel::class . '] and cannot be deleted.'
        );

    expect($model->hasMetadata('status'))->toBeTrue();
});

it('still allows deleting non-required metadata keys on a model with required keys', function () {
    $model = RequiredTestModel::create(['name' => 'Product']);
    $model->setMetadata('note', 'hello');

    expect($model->deleteMetadata('note'))->toBeTrue();
    expect($model->hasMetadata('note'))->toBeFalse();
});

it('backfills only missing required keys without overwriting existing values', function () {
    $model = RequiredTestModel::create(['name' => 'Product']);

    // Simulate a pre-existing record that predates the #[RequiresMetadata] declaration,
    // by removing a required row directly (bypassing deleteMetadata's protection).
    $model->metadata()->where('key', 'status')->delete();
    $model->load('metadata');
    expect($model->hasMetadata('status'))->toBeFalse();

    // Override the other required key's value away from its declared default.
    $model->setMetadata('is_published', true, 'boolean');

    $model->syncRequiredMetadata();

    expect($model->getMetadata('status'))->toBe('draft');
    expect($model->getMetadata('is_published'))->toBeTrue();
});

it('reflects required metadata definitions per model class, keyed by key', function () {
    $definitions = RequiredTestModel::requiredMetadataDefinitions();

    expect($definitions)->toHaveKeys(['status', 'is_published']);
    expect($definitions['status'])->toBeInstanceOf(RequiresMetadata::class);
    expect($definitions['status']->default)->toBe('draft');
    expect($definitions['status']->type)->toBe('string');
    expect($definitions['is_published']->default)->toBeFalse();
    expect($definitions['is_published']->type)->toBe('boolean');

    // A model class without any #[RequiresMetadata] declarations gets its own empty result,
    // proving the per-class cache doesn't leak between model classes.
    expect(PlainTestModel::requiredMetadataDefinitions())->toBe([]);
});

it('eagerly creates a required badges field from a bare string default', function () {
    $model = BadgesRequiredTestModel::create(['name' => 'Product']);

    expect($model->getMetadata('tags'))->toBe([['text' => 'beschikbaarheid']]);
});

it('eagerly creates a required badges field from an array-of-strings default', function () {
    $model = BadgesListRequiredTestModel::create(['name' => 'Product']);

    expect($model->getMetadata('tags'))->toBe([
        ['text' => 'beschikbaarheid'],
        ['text' => 'populair'],
    ]);
});

it('accepts values within declared options for a string field', function () {
    $model = OptionsRequiredTestModel::create(['name' => 'Product']);
    expect($model->getMetadata('status'))->toBe('draft');

    $model->setMetadata('status', 'published', 'string');
    expect($model->getMetadata('status'))->toBe('published');
});

it('rejects values outside declared options for a string field', function () {
    $model = OptionsRequiredTestModel::create(['name' => 'Product']);

    expect(fn () => $model->setMetadata('status', 'not-an-option', 'string'))
        ->toThrow(
            \InvalidArgumentException::class,
            "Invalid value for metadata key 'status': must be one of [draft, published, archived]."
        );
});

it('accepts values within declared options for a badges field', function () {
    $model = BadgesOptionsRequiredTestModel::create(['name' => 'Product']);
    expect($model->getMetadata('tags'))->toBe([['text' => 'beschikbaarheid']]);

    $model->setMetadata('tags', ['populair'], 'badges');
    expect($model->getMetadata('tags'))->toBe([['text' => 'populair']]);
});

it('rejects values outside declared options for a badges field', function () {
    $model = BadgesOptionsRequiredTestModel::create(['name' => 'Product']);

    expect(fn () => $model->setMetadata('tags', 'niet-toegestaan', 'badges'))
        ->toThrow(
            \InvalidArgumentException::class,
            "Invalid value for metadata key 'tags': must be one of [beschikbaarheid, populair, uitverkocht]."
        );
});

it('rejects a required-metadata default that is not among its own declared options', function () {
    expect(fn () => MisconfiguredOptionsRequiredTestModel::create(['name' => 'Product']))
        ->toThrow(
            \InvalidArgumentException::class,
            "Invalid value for metadata key 'status': must be one of [draft, published]."
        );
});

if (class_exists(\Filament\Resources\RelationManagers\RelationManager::class)) {
    class TestRequiredMetadataRelationManager extends \MartinMulder\LaravelModelMetadata\Filament\RelationManagers\MetadataRelationManager
    {
        public function testIsMetadataKeyRequired(Model $record): bool
        {
            return $this->isMetadataKeyRequired($record);
        }

        public function testGetOptionsForKey(?string $key): ?array
        {
            return $this->getOptionsForKey($key);
        }

        /**
         * @param  array<string, mixed>  $data
         * @return array<string, mixed>
         */
        public function testPreventRequiredKeyRename(array $data, Model $record): array
        {
            return $this->preventRequiredKeyRename($data, $record);
        }
    }

    it('identifies required vs non-required metadata rows for the Filament relation manager', function () {
        $model = RequiredTestModel::create(['name' => 'Product']);
        $model->setMetadata('note', 'hello');

        $manager = new TestRequiredMetadataRelationManager();
        $manager->ownerRecord = $model;

        $requiredRecord = $model->metadata->firstWhere('key', 'status');
        $optionalRecord = $model->metadata->firstWhere('key', 'note');

        expect($manager->testIsMetadataKeyRequired($requiredRecord))->toBeTrue();
        expect($manager->testIsMetadataKeyRequired($optionalRecord))->toBeFalse();
    });

    it('resolves declared options for a key from the Filament relation manager', function () {
        $model = OptionsRequiredTestModel::create(['name' => 'Product']);

        $manager = new TestRequiredMetadataRelationManager();
        $manager->ownerRecord = $model;

        expect($manager->testGetOptionsForKey('status'))->toBe(['draft', 'published', 'archived']);
        expect($manager->testGetOptionsForKey('unknown_key'))->toBeNull();
        expect($manager->testGetOptionsForKey(null))->toBeNull();
    });

    it('prevents renaming or retyping a required metadata row via an edit, since that is equivalent to deleting it', function () {
        $model = RequiredTestModel::create(['name' => 'Product']);
        $model->setMetadata('note', 'hello');

        $manager = new TestRequiredMetadataRelationManager();
        $manager->ownerRecord = $model;

        $requiredRecord = $model->metadata->firstWhere('key', 'status');
        $optionalRecord = $model->metadata->firstWhere('key', 'note');

        // A required row's key/type are forced back to their original values, no matter what
        // was submitted — this is what stops "rename the key away" from being an end-run
        // around the deletion protection.
        $mutated = $manager->testPreventRequiredKeyRename(
            ['key' => 'renamed_away', 'type' => 'integer', 'value_string' => 'draft'],
            $requiredRecord,
        );
        expect($mutated['key'])->toBe('status');
        expect($mutated['type'])->toBe('string');

        // A non-required row is unaffected — it can still be freely renamed/retyped.
        $mutatedOptional = $manager->testPreventRequiredKeyRename(
            ['key' => 'renamed_note', 'type' => 'integer', 'value_integer' => 42],
            $optionalRecord,
        );
        expect($mutatedOptional['key'])->toBe('renamed_note');
        expect($mutatedOptional['type'])->toBe('integer');
    });
}
