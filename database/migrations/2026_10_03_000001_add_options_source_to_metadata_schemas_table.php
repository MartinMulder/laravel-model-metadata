<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A schema row can take its options from a registered option source instead of a fixed list.
     */
    public function up(): void
    {
        Schema::table('metadata_schemas', function (Blueprint $table) {
            $table->string('options_source')->nullable()->after('options');
        });
    }

    public function down(): void
    {
        Schema::table('metadata_schemas', function (Blueprint $table) {
            $table->dropColumn('options_source');
        });
    }
};
