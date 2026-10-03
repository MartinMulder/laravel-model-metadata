<?php

use Livewire\Livewire;
use MartinMulder\LaravelModelMetadata\Filament\Resources\MetadataSchemas\Pages\CreateMetadataSchema;
use MartinMulder\LaravelModelMetadata\Models\MetadataSchema;
use MartinMulder\LaravelModelMetadata\Options\MetadataOptionSource;
use MartinMulder\LaravelModelMetadata\Options\MetadataOptionSources;
use MartinMulder\LaravelModelMetadata\Tests\Filament\Fixtures\Category;
use MartinMulder\LaravelModelMetadata\Tests\Filament\Fixtures\Item;
use MartinMulder\LaravelModelMetadata\Tests\Filament\Fixtures\Resources\Items\Pages\EditItem;

beforeEach(function () {
    MetadataOptionSources::register(
        MetadataOptionSource::make('topics')
            ->label('Topics')
            ->options(['logging' => 'Logging & monitoring', 'incidents' => 'Incidents']),
    );

    $policy = Category::create(['slug' => 'policy', 'name' => 'Policy']);
    $report = Category::create(['slug' => 'report', 'name' => 'Report']);

    foreach ([
        ['key' => 'valid_from', 'type' => 'date', 'sort_order' => 1],
        ['key' => 'topics', 'type' => 'multiselect', 'options_source' => 'topics', 'sort_order' => 2],
        ['key' => 'status', 'type' => 'string', 'options' => ['draft', 'final'], 'sort_order' => 3],
        ['key' => 'public', 'type' => 'boolean', 'sort_order' => 4],
    ] as $row) {
        MetadataSchema::create(['owner_type' => Item::class, 'scope' => 'policy', 'required' => false, ...$row]);
    }

    $this->item = Item::create(['name' => 'Information security policy', 'category_id' => $policy->id]);
    $this->report = Item::create(['name' => 'Annual report', 'category_id' => $report->id]);
});

it('shows a field per defined key of the record\'s scope, filled from its metadata', function () {
    $this->item->setMetadata('valid_from', '2026-01-01', 'date');
    $this->item->setMetadata('topics', ['incidents'], 'multiselect');

    Livewire::test(EditItem::class, ['record' => $this->item->getRouteKey()])
        ->assertFormFieldExists('metadata_fields.valid_from')
        ->assertFormFieldExists('metadata_fields.topics')
        ->assertFormFieldExists('metadata_fields.status')
        ->assertFormFieldExists('metadata_fields.public')
        ->assertFormSet([
            'metadata_fields.valid_from' => '2026-01-01',
            'metadata_fields.topics' => ['incidents'],
        ]);
});

it('has no fields for keys of another scope', function () {
    Livewire::test(EditItem::class, ['record' => $this->report->getRouteKey()])
        ->assertFormFieldDoesNotExist('metadata_fields.topics');
});

it('saves the fields as metadata', function () {
    Livewire::test(EditItem::class, ['record' => $this->item->getRouteKey()])
        ->fillForm([
            'metadata_fields.valid_from' => '2026-03-01',
            'metadata_fields.topics' => ['logging', 'incidents'],
            'metadata_fields.status' => 'final',
            'metadata_fields.public' => true,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $item = $this->item->fresh();
    expect($item->getMetadata('valid_from')->format('Y-m-d'))->toBe('2026-03-01')
        ->and($item->getMetadata('topics'))->toBe(['logging', 'incidents'])
        ->and($item->getMetadata('status'))->toBe('final')
        ->and($item->getMetadata('public'))->toBeTrue();
});

it('does not create rows for fields left empty', function () {
    Livewire::test(EditItem::class, ['record' => $this->item->getRouteKey()])
        ->fillForm(['name' => 'Renamed'])
        ->call('save')
        ->assertHasNoFormErrors();

    // Only the toggle has a value (false) when left untouched.
    expect($this->item->fresh()->metadata->pluck('key')->all())->toBe(['public']);
});

it('keeps the model attributes free of the metadata fields', function () {
    Livewire::test(EditItem::class, ['record' => $this->item->getRouteKey()])
        ->fillForm(['name' => 'Renamed', 'metadata_fields.status' => 'draft'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($this->item->fresh())
        ->name->toBe('Renamed')
        ->getMetadata('status')->toBe('draft');
});

it('offers registered option sources when defining a field', function () {
    Livewire::test(CreateMetadataSchema::class)
        ->fillForm(['owner_type' => Item::class])
        ->assertSee('Topics')
        ->fillForm(['scope' => 'report', 'key' => 'themes', 'type' => 'multiselect', 'options_source' => 'topics', 'required' => false])
        ->assertFormFieldHidden('options')
        ->call('create')
        ->assertHasNoFormErrors();

    expect(MetadataSchema::where('key', 'themes')->sole())
        ->options_source->toBe('topics')
        ->options->toBeNull();
});
