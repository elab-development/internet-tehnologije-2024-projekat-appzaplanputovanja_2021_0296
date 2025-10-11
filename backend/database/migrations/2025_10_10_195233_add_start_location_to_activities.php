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
            $table->string('start_location')->nullable()->index();
            $table->index(['type','transport_mode','start_location','location','price'], 'idx_transport_route_price');
            //Brzo pronalaženje transporta za konkretnu rutu i mode, sortirano po ceni.
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropIndex('idx_transport_route_price');
            $table->dropColumn('start_location');
        });
    }
};
