<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use MartinMulder\LaravelModelMetadata\Models\MetadataSchema;
use MartinMulder\LaravelModelMetadata\Scopes\MetadataScope;
use MartinMulder\LaravelModelMetadata\Scopes\MetadataScopeRegistry;
use MartinMulder\LaravelModelMetadata\Scopes\MetadataScopes;
use MartinMulder\LaravelModelMetadata\Scopes\ProvidesMetadataScope;
use MartinMulder\LaravelModelMetadata\Traits\HasMetadata;

uses(RefreshDatabase::class);

/** The scope source: a category, identified by its slug. */
class ScopeCategory extends Model
{
    use ProvidesMetadataScope;

    protected $table = 'scope_categories';

    protected $fillable = ['slug', 'name'];
}

/** The owner: an item belonging to a category; its scope comes from the registry, not an override. */
class ScopeItem extends Model
{
    use HasMetadata;

    protected $table = 'scope_items';

    protected $fillable = ['name', 'scope_category_id'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ScopeCategory::class, 'scope_category_id');
    }
}

/** An owner that overrides metadataScope() itself; the override must keep winning. */
class OverridingScopeItem extends ScopeItem
{
    public function metadataScope(): ?string
    {
        return 'override';
    }
}

beforeEach(function () {
    Schema::create('scope_categories', function (Blueprint $table) {
        $table->id();
        $table->string('slug');
        $table->string('name');
        $table->timestamps();
    });
    Schema::create('scope_items', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->foreignId('scope_category_id')->nullable();
        $table->timestamps();
    });

    MetadataScopes::register(
        MetadataScope::for(ScopeItem::class)
            ->label('Item')
            ->scopedBy(ScopeCategory::class, key: 'slug', title: 'name', label: 'Category')
            ->resolveUsing(fn (ScopeItem $item) => $item->category?->slug),
    );

    $this->policy = ScopeCategory::create(['slug' => 'policy', 'name' => 'Policy']);
    $this->report = ScopeCategory::create(['slug' => 'report', 'name' => 'Report']);
});

it('registers a scope definition per owner model', function () {
    $registry = app(MetadataScopeRegistry::class);
    $definition = $registry->for(ScopeItem::class);

    expect($definition->getLabel())->toBe('Item')
        ->and($definition->getScopeLabel())->toBe('Category')
        ->and($definition->getSource())->toBe(ScopeCategory::class)
        ->and($definition->getOptions())->toBe(['policy' => 'Policy', 'report' => 'Report'])
        ->and($definition->getOptionLabel('report'))->toBe('Report')
        ->and($definition->getOptionLabel('gone'))->toBe('gone')
        ->and($registry->forSource(ScopeCategory::class))->toHaveCount(1)
        ->and($registry->for(OverridingScopeItem::class))->toBeNull();
});

it('resolves the scope of an instance through the registry', function () {
    $item = ScopeItem::create(['name' => 'Plan', 'scope_category_id' => $this->policy->id]);
    $loose = ScopeItem::create(['name' => 'Loose']);

    expect($item->metadataScope())->toBe('policy')
        ->and($loose->metadataScope())->toBeNull();
});

it('lets a model override of metadataScope() win', function () {
    MetadataScopes::register(MetadataScope::for(OverridingScopeItem::class)->resolveUsing(fn () => 'registry'));

    expect((new OverridingScopeItem)->metadataScope())->toBe('override');
});

it('applies the schema rows of the resolved scope only', function () {
    MetadataSchema::create([
        'owner_type' => ScopeItem::class, 'scope' => 'policy', 'key' => 'approved_by',
        'type' => 'string', 'default' => 'board', 'required' => true,
    ]);

    $plan = ScopeItem::create(['name' => 'Plan', 'scope_category_id' => $this->policy->id]);
    $q3 = ScopeItem::create(['name' => 'Q3', 'scope_category_id' => $this->report->id]);

    expect($plan->fresh()->getMetadata('approved_by'))->toBe('board')
        ->and($q3->fresh()->hasMetadata('approved_by'))->toBeFalse();
});

it('gives the source model its schema rows, and creating fills owner and scope', function () {
    $row = $this->policy->metadataSchemas()->create(['key' => 'approved_by', 'type' => 'string', 'default' => '', 'required' => false]);
    MetadataSchema::create(['owner_type' => ScopeItem::class, 'scope' => 'report', 'key' => 'period', 'type' => 'string', 'required' => false]);
    MetadataSchema::create(['owner_type' => 'Other\\Model', 'scope' => 'policy', 'key' => 'foreign', 'type' => 'string', 'required' => false]);

    expect($row->owner_type)->toBe(ScopeItem::class)
        ->and($row->scope)->toBe('policy')
        ->and($this->policy->metadataSchemas()->pluck('key')->all())->toBe(['approved_by'])
        ->and($this->report->metadataSchemas()->pluck('key')->all())->toBe(['period']);
});

it('explains when a source model is not registered', function () {
    MetadataScopes::register(MetadataScope::for(ScopeItem::class)->label('Item')); // replaces: no source any more

    expect(fn () => $this->policy->metadataSchemas())->toThrow(LogicException::class, 'scopedBy');
});

it('reads scope definitions from the config', function () {
    config(['laravel-model-metadata.scopes' => [ConfigScopeDefinition::class]]);
    app()->forgetInstance(MetadataScopeRegistry::class);

    expect(app(MetadataScopeRegistry::class)->for(OverridingScopeItem::class)?->getLabel())->toBe('From config');
});

class ConfigScopeDefinition
{
    public function __invoke(): MetadataScope
    {
        return MetadataScope::for(OverridingScopeItem::class)->label('From config')->options(['a' => 'A']);
    }
}
