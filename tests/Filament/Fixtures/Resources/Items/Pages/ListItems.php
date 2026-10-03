<?php

namespace MartinMulder\LaravelModelMetadata\Tests\Filament\Fixtures\Resources\Items\Pages;

use Filament\Resources\Pages\ListRecords;
use MartinMulder\LaravelModelMetadata\Tests\Filament\Fixtures\Resources\Items\ItemResource;

class ListItems extends ListRecords
{
    protected static string $resource = ItemResource::class;
}
