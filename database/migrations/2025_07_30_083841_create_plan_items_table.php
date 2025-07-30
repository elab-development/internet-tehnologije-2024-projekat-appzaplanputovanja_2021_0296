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

           // $table->foreign('travelplan_id')-constrained()->onDelete('cascade');
           // $table->foreign('activity_id')->constrained()->onDelete('cascade');
            
            $table->unsignedBigInteger('travelplan_id');
            $table->unsignedBigInteger('activity_id');
            $table->foreign('travelplan_id')->references('id')->on('travel_plans')->onDelete('cascade');
            $table->foreign('activity_id')->references('id')->on('activities')->onDelete('cascade');

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
