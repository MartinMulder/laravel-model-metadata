<?php

use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;
use MartinMulder\LaravelModelMetadata\Filament\RelationManagers\MetadataSchemasRelationManager;
use MartinMulder\LaravelModelMetadata\Filament\Resources\MetadataSchemas\Pages\CreateMetadataSchema;
use MartinMulder\LaravelModelMetadata\Filament\Resources\MetadataSchemas\Pages\EditMetadataSchema;
use MartinMulder\LaravelModelMetadata\Filament\Resources\MetadataSchemas\Pages\ListMetadataSchemas;
use MartinMulder\LaravelModelMetadata\Filament\Resources\MetadataSchemas\Schemas\MetadataSchemaForm;
use MartinMulder\LaravelModelMetadata\Models\MetadataSchema;
use MartinMulder\LaravelModelMetadata\Tests\Filament\Fixtures\Category;
use MartinMulder\LaravelModelMetadata\Tests\Filament\Fixtures\Item;
use MartinMulder\LaravelModelMetadata\Tests\Filament\Fixtures\Resources\Categories\Pages\EditCategory;

beforeEach(function () {
    $this->policy = Category::create(['slug' => 'policy', 'name' => 'Policy']);
    $this->report = Category::create(['slug' => 'report', 'name' => 'Report']);
});

it('offers registered owners by label, plus owner types already in use', function () {
    MetadataSchema::create(['owner_type' => 'App\\Models\\Legacy', 'key' => 'x', 'type' => 'string', 'required' => false]);

    expect(MetadataSchemaForm::ownerOptions())->toBe([
        'App\\Models\\Legacy' => 'App\\Models\\Legacy',
        Item::class => 'Item',
    ]);
});

it('creates a schema row with the scope chosen from the registered options', function () {
    Livewire::test(CreateMetadataSchema::class)
        ->fillForm(['owner_type' => Item::class])
        ->assertSeeHtml('value="policy"')
        ->assertSeeHtml('value="report"')
        ->assertSee('Category')
        ->fillForm(['scope' => 'report', 'key' => 'period', 'type' => 'string', 'required' => false])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(MetadataSchema::sole())
        ->owner_type->toBe(Item::class)
        ->scope->toBe('report');
});

it('keeps free text for an owner that is not registered', function () {
    Livewire::test(CreateMetadataSchema::class)
        ->fillForm(['custom_owner' => true])
        ->fillForm(['owner_type' => 'App\\Models\\Project', 'scope' => 'alpha', 'key' => 'code', 'type' => 'string', 'required' => false])
        ->assertDontSeeHtml('value="policy"')
        ->call('create')
        ->assertHasNoFormErrors();

    expect(MetadataSchema::sole())
        ->owner_type->toBe('App\\Models\\Project')
        ->scope->toBe('alpha');
});

it('opens an existing row of an unregistered owner with the free-text fields', function () {
    $row = MetadataSchema::create(['owner_type' => 'App\\Models\\Project', 'scope' => 'alpha', 'key' => 'code', 'type' => 'string', 'required' => false]);

    Livewire::test(EditMetadataSchema::class, ['record' => $row->getRouteKey()])
        ->assertFormSet(['custom_owner' => false, 'owner_type' => 'App\\Models\\Project', 'scope' => 'alpha'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($row->fresh()->owner_type)->toBe('App\\Models\\Project');
});

it('shows owner and scope labels in the list', function () {
    MetadataSchema::create(['owner_type' => Item::class, 'scope' => 'policy', 'key' => 'approved_by', 'type' => 'string', 'required' => false]);

    Livewire::test(ListMetadataSchemas::class)
        ->assertSee('Item')
        ->assertSee('Category: Policy');
});

it('manages the fields of one scope on the source record', function () {
    MetadataSchema::create(['owner_type' => Item::class, 'scope' => 'report', 'key' => 'period', 'type' => 'string', 'required' => false]);

    $component = Livewire::test(MetadataSchemasRelationManager::class, ['ownerRecord' => $this->policy, 'pageClass' => EditCategory::class])
        ->assertCanNotSeeTableRecords(MetadataSchema::where('scope', 'report')->get())
        ->mountAction(TestAction::make('create')->table());

    foreach (['key' => 'approved_by', 'type' => 'string', 'default' => 'board', 'required' => true] as $veld => $waarde) {
        $component->set("mountedActions.0.data.{$veld}", $waarde);
    }
    $component->callMountedAction()->assertHasNoActionErrors();

    $row = MetadataSchema::firstWhere('key', 'approved_by');

    expect($row->owner_type)->toBe(Item::class)
        ->and($row->scope)->toBe('policy');

    // The field applies to new items of that category only.
    $plan = Item::create(['name' => 'Plan', 'category_id' => $this->policy->id]);
    $q3 = Item::create(['name' => 'Q3', 'category_id' => $this->report->id]);

    expect($plan->fresh()->getMetadata('approved_by'))->toBe('board')
        ->and($q3->fresh()->hasMetadata('approved_by'))->toBeFalse();
});

it('renders the edit page of the source record with the relation manager', function () {
    $this->get(EditCategory::getUrl(['record' => $this->policy]))
        ->assertOk()
        ->assertSeeLivewire(MetadataSchemasRelationManager::class);
});

it('enters a date value through the metadata relation manager', function () {
    $item = Item::create(['name' => 'Plan', 'category_id' => $this->policy->id]);

    $component = Livewire::test(MartinMulder\LaravelModelMetadata\Filament\RelationManagers\MetadataRelationManager::class, ['ownerRecord' => $item, 'pageClass' => EditCategory::class])
        ->mountAction(TestAction::make('create')->table());

    foreach (['key' => 'valid_from', 'type' => 'date', 'value_date' => '2026-03-01'] as $veld => $waarde) {
        $component->set("mountedActions.0.data.{$veld}", $waarde);
    }
    $component->callMountedAction()->assertHasNoActionErrors();

    expect($item->fresh()->getMetadata('valid_from')->toDateString())->toBe('2026-03-01');

    Livewire::test(MartinMulder\LaravelModelMetadata\Filament\RelationManagers\MetadataRelationManager::class, ['ownerRecord' => $item->fresh(), 'pageClass' => EditCategory::class])
        ->assertSee('03/01/2026'); // locale "en": isoFormat('L')
});
