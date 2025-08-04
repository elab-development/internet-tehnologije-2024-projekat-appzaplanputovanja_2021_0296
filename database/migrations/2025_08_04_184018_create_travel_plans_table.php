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
        Schema::create('travel_plans', function (Blueprint $table) {
            $table->id(); // travel_plan_id
            $table->string('start_location');
            $table->string('destination');
            $table->date('start_date');
            $table->date('end_date');
            $table->text('preferences')->nullable();
            $table->decimal('budget', 10, 2);
            $table->integer('passenger_count')->default(1);
            $table->decimal('total_cost', 10, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('travel_plans');
    }
};
