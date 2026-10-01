<?php

namespace MartinMulder\LaravelModelMetadata\Tests\Filament\Fixtures;

use Illuminate\Database\Eloquent\Model;
use MartinMulder\LaravelModelMetadata\Scopes\ProvidesMetadataScope;

class Category extends Model
{
    use ProvidesMetadataScope;

    protected $table = 'categories';

    protected $fillable = ['slug', 'name'];
}
