<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use MartinMulder\LaravelModelMetadata\Models\MetadataSchema;
use MartinMulder\LaravelModelMetadata\Options\MetadataOptionSource;
use MartinMulder\LaravelModelMetadata\Options\MetadataOptionSources;
use MartinMulder\LaravelModelMetadata\Traits\HasMetadata;

uses(RefreshDatabase::class);

class OptionSourceTestModel extends Model
{
    use HasMetadata;

    protected $table = 'test_models';

    protected $fillable = ['name'];
}

beforeEach(function () {
    $this->onderwerpen = ['logging' => 'Logging & monitoring', 'incidenten' => 'Incidenten & datalekken'];

    MetadataOptionSources::register(
        MetadataOptionSource::make('onderwerpen')
            ->label('Onderwerpen')
            ->options(fn (): array => $this->onderwerpen),
    );

    MetadataSchema::create([
        'owner_type' => OptionSourceTestModel::class,
        'key' => 'onderwerpen',
        'type' => 'multiselect',
        'options_source' => 'onderwerpen',
        'required' => false,
    ]);
});

it('takes the options of a schema row from the registered source, as key => label', function () {
    $model = OptionSourceTestModel::create(['name' => 'Beleid']);

    expect($model->resolvedMetadataDefinitions()['onderwerpen']->optionLabels())->toBe($this->onderwerpen);
});

it('evaluates the source on every call, so new choices show up', function () {
    $this->onderwerpen['cryptografie'] = 'Cryptografie';

    $definition = OptionSourceTestModel::create(['name' => 'Beleid'])->resolvedMetadataDefinitions()['onderwerpen'];

    expect($definition->optionLabels())->toHaveKey('cryptografie');
});

it('stores the chosen keys and rejects keys the source does not offer', function () {
    $model = OptionSourceTestModel::create(['name' => 'Beleid']);

    $model->setMetadata('onderwerpen', ['logging', 'incidenten'], 'multiselect');
    expect($model->getMetadata('onderwerpen'))->toBe(['logging', 'incidenten']);

    expect(fn () => $model->setMetadata('onderwerpen', ['logging', 'onbekend'], 'multiselect'))
        ->toThrow(InvalidArgumentException::class, "must be one of [logging, incidenten]");
});

it('allows clearing the choices', function () {
    $model = OptionSourceTestModel::create(['name' => 'Beleid']);

    $model->setMetadata('onderwerpen', [], 'multiselect');
    $model->setMetadata('onderwerpen', null, 'multiselect');

    expect($model->getMetadata('onderwerpen'))->toBeNull();
});

it('treats a source that is not registered as unconstrained', function () {
    MetadataSchema::query()->update(['options_source' => 'niet-geinstalleerd']);
    $model = OptionSourceTestModel::create(['name' => 'Beleid']);

    expect($model->resolvedMetadataDefinitions()['onderwerpen']->optionLabels())->toBeNull();

    $model->setMetadata('onderwerpen', ['wat-dan-ook'], 'multiselect');
    expect($model->getMetadata('onderwerpen'))->toBe(['wat-dan-ook']);
});

it('keeps fixed options strict, as before', function () {
    MetadataSchema::create([
        'owner_type' => OptionSourceTestModel::class,
        'key' => 'status',
        'type' => 'string',
        'options' => ['concept', 'definitief'],
        'required' => false,
    ]);
    $model = OptionSourceTestModel::create(['name' => 'Beleid']);

    expect($model->resolvedMetadataDefinitions()['status']->optionLabels())->toBe(['concept' => 'concept', 'definitief' => 'definitief']);

    $model->setMetadata('status', 'definitief');
    expect(fn () => $model->setMetadata('status', 'vervallen'))->toThrow(InvalidArgumentException::class);
});
