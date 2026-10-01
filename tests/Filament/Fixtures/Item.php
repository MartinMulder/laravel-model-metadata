<?php

namespace MartinMulder\LaravelModelMetadata\Tests\Filament\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use MartinMulder\LaravelModelMetadata\Traits\HasMetadata;

class Item extends Model
{
    use HasMetadata;

    protected $table = 'items';

    protected $fillable = ['name', 'category_id'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
