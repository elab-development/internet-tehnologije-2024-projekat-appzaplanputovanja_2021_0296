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
            //za lakse ucitavanje u formama
            $table->index(['type', 'location'],             'idx_act_type_location');
            $table->index(['type', 'start_location'],       'idx_act_type_start_location');
            $table->index(['type', 'transport_mode'],       'idx_act_type_transport_mode');
            $table->index(['type', 'accommodation_class'],  'idx_act_type_accommodation_class');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropIndex('idx_act_type_location');
            $table->dropIndex('idx_act_type_start_location');
            $table->dropIndex('idx_act_type_transport_mode');
            $table->dropIndex('idx_act_type_accommodation_class');
        });
    }
};
