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
            $table->enum('transport_mode', [ 'airplane', 'train', 'car', 'bus'])->nullable();
            $table->enum('accommodation_class', ['hostel','guesthouse','budget_hotel','standard_hotel','boutique_hotel','luxury_hotel',
                                                'resort','apartment','bed_and_breakfast','villa','mountain_lodge','camping','glamping'])->nullable();


        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropColumn(['transport_mode','accommodation_class']);
        });
    }
};
