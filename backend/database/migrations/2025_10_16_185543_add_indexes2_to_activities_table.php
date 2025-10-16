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
        Schema::table('activities', function (Blueprint $table) {
            // indeksi koji ubrzavaju filtriranje i grupisanje za destinacije
            $table->index(['location'], 'idx_activities_location');
            $table->index(['location', 'type'], 'idx_activities_location_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropIndex('idx_activities_location');
            $table->dropIndex('idx_activities_location_type');
        });
    }
};
