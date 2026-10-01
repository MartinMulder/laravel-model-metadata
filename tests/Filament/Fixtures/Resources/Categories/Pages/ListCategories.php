<?php

namespace MartinMulder\LaravelModelMetadata\Tests\Filament\Fixtures\Resources\Categories\Pages;

use Filament\Resources\Pages\ListRecords;
use MartinMulder\LaravelModelMetadata\Tests\Filament\Fixtures\Resources\Categories\CategoryResource;

class ListCategories extends ListRecords
{
    protected static string $resource = CategoryResource::class;
}
