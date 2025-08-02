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
        Schema::create('plan_items', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('travel_plan_id')->constrained('travel_plans')->onDelete('cascade');
            $table->foreignId('activity_id')->constrained('activities')->onDelete('cascade');


            $table->string('name');
            $table->dateTime('time_from');
            $table->dateTime('time_to');
            $table->double('amount')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plan_items');
    }
};
