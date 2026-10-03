<?php

namespace MartinMulder\LaravelModelMetadata\Tests\Filament\Fixtures\Resources\Items\Pages;

use Filament\Resources\Pages\EditRecord;
use MartinMulder\LaravelModelMetadata\Tests\Filament\Fixtures\Resources\Items\ItemResource;

class EditItem extends EditRecord
{
    protected static string $resource = ItemResource::class;
}
