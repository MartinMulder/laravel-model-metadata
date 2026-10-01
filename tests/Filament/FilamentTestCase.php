<?php

namespace MartinMulder\LaravelModelMetadata\Tests\Filament;

use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Filament\Actions\ActionsServiceProvider;
use Filament\Facades\Filament;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Infolists\InfolistsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Support\SupportServiceProvider;
use Filament\Tables\TablesServiceProvider;
use Filament\Widgets\WidgetsServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Kirschbaum\PowerJoins\PowerJoinsServiceProvider;
use Livewire\LivewireServiceProvider;
use MartinMulder\LaravelModelMetadata\LaravelModelMetadataServiceProvider;
use MartinMulder\LaravelModelMetadata\Scopes\MetadataScope;
use MartinMulder\LaravelModelMetadata\Scopes\MetadataScopes;
use MartinMulder\LaravelModelMetadata\Tests\Filament\Fixtures\Category;
use MartinMulder\LaravelModelMetadata\Tests\Filament\Fixtures\Item;
use MartinMulder\LaravelModelMetadata\Tests\Filament\Fixtures\TestPanelProvider;
use MartinMulder\LaravelModelMetadata\Tests\Filament\Fixtures\User;
use Orchestra\Testbench\Attributes\WithMigration;
use Orchestra\Testbench\TestCase as OrchestraTestCase;
use RyanChandler\BladeCaptureDirective\BladeCaptureDirectiveServiceProvider;

/**
 * Boots Filament with a "test" panel (MetadataSchemaResource + a Category resource with the
 * MetadataSchemasRelationManager), a logged-in user, and Item registered as owner scoped by
 * Category (slug).
 */
#[WithMigration]
abstract class FilamentTestCase extends OrchestraTestCase
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [
            LivewireServiceProvider::class,
            BladeCaptureDirectiveServiceProvider::class,
            BladeIconsServiceProvider::class,
            BladeHeroiconsServiceProvider::class,
            PowerJoinsServiceProvider::class,
            SupportServiceProvider::class,
            ActionsServiceProvider::class,
            FormsServiceProvider::class,
            InfolistsServiceProvider::class,
            NotificationsServiceProvider::class,
            SchemasServiceProvider::class,
            TablesServiceProvider::class,
            WidgetsServiceProvider::class,
            FilamentServiceProvider::class,
            LaravelModelMetadataServiceProvider::class,
            TestPanelProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('app.key', 'base64:' . base64_encode(str_repeat('a', 32)));
    }

    protected function defineDatabaseMigrations(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug');
            $table->string('name');
            $table->timestamps();
        });
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('category_id')->nullable();
            $table->timestamps();
        });
    }

    protected function setUp(): void
    {
        parent::setUp();

        MetadataScopes::register(
            MetadataScope::for(Item::class)
                ->label('Item')
                ->scopedBy(Category::class, key: 'slug', title: 'name', label: 'Category')
                ->resolveUsing(fn (Item $item) => $item->category?->slug),
        );

        $this->actingAs(User::forceCreate(['name' => 'Test', 'email' => 'test@example.com', 'password' => 'x']));
        Filament::setCurrentPanel('test');
    }
}
