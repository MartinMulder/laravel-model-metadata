<?php

namespace MartinMulder\LaravelModelMetadata\Tests\Filament\Fixtures\Resources\Categories\Pages;

use Filament\Resources\Pages\EditRecord;
use MartinMulder\LaravelModelMetadata\Tests\Filament\Fixtures\Resources\Categories\CategoryResource;

class EditCategory extends EditRecord
{
    protected static string $resource = CategoryResource::class;
}
