<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('metadata_schemas', function (Blueprint $table) {
            $table->id();
            $table->string('owner_type');
            $table->string('scope')->nullable();
            $table->string('key');
            $table->string('type')->default('string');
            $table->text('default')->nullable();
            $table->json('options')->nullable();
            $table->boolean('required')->default(true);
            $table->unsignedInteger('sort_order')->nullable();
            $table->timestamps();

            // NULL is treated as distinct by SQL unique indexes, which would let multiple
            // fallback rows (scope IS NULL) coexist for the same owner_type + key. Coalescing
            // scope into a generated column closes that gap for the most common row shape.
            $table->string('scope_key')->virtualAs("coalesce(scope, '')");
            $table->unique(['owner_type', 'scope_key', 'key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('metadata_schemas');
    }
};
