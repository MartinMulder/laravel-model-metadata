<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use MartinMulder\LaravelModelMetadata\Models\MetadataSchema;
use MartinMulder\LaravelModelMetadata\Traits\HasMetadata;
use MartinMulder\LaravelModelMetadata\TypeRegistry;

uses(RefreshDatabase::class);

class DatedTestModel extends Model
{
    use HasMetadata;

    protected $table = 'test_models';

    protected $fillable = ['name'];
}

it('stores and reads a date', function () {
    $model = DatedTestModel::create(['name' => 'Beleid']);
    $model->setMetadata('valid_from', '2026-03-01', 'date');

    $fresh = $model->fresh();

    expect($fresh->getMetadata('valid_from'))->toBeInstanceOf(CarbonImmutable::class)
        ->and($fresh->getMetadata('valid_from')->toDateString())->toBe('2026-03-01')
        ->and($fresh->metadata()->where('key', 'valid_from')->value('value'))->toBe('2026-03-01');
});

it('refuses something that is not a date', function () {
    $model = DatedTestModel::create(['name' => 'Beleid']);

    expect(fn () => $model->setMetadata('valid_from', 'binnenkort', 'date'))->toThrow(InvalidArgumentException::class);
});

it('creates a required date field with an empty default as null', function () {
    MetadataSchema::create([
        'owner_type' => DatedTestModel::class, 'key' => 'valid_until', 'type' => 'date', 'default' => '', 'required' => true,
    ]);

    $model = DatedTestModel::create(['name' => 'Beleid']);

    expect($model->fresh()->hasMetadata('valid_until'))->toBeTrue()
        ->and($model->fresh()->getMetadata('valid_until'))->toBeNull();
});

it('compares stored dates as strings in SQL', function () {
    foreach (['2025-12-31', '2026-03-01', '2027-01-15'] as $i => $datum) {
        DatedTestModel::create(['name' => "m{$i}"])->setMetadata('valid_from', $datum, 'date');
    }

    $started = DatedTestModel::whereHas('metadata', fn ($q) => $q->where('key', 'valid_from')->where('value', '<=', '2026-06-01'))->pluck('name')->all();

    expect($started)->toBe(['m0', 'm1']);
});

it('keeps the built-in types when the host published a config without them', function () {
    config(['laravel-model-metadata.types' => ['string' => \MartinMulder\LaravelModelMetadata\Types\StringType::class]]);
    app()->forgetInstance(TypeRegistry::class);

    expect(app(TypeRegistry::class)->has('date'))->toBeTrue()
        ->and(app(TypeRegistry::class)->has('badges'))->toBeTrue();
});
