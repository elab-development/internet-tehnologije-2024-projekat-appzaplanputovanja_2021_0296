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
        Schema::table('plan_items', function (Blueprint $table) {
            $table->unsignedBigInteger('travel_plan_id')->after('id');
            $table->unsignedBigInteger('activity_id')->after('travel_plan_id');

            $table->foreign('travel_plan_id')->references('id')->on('travel_plans')->onDelete('cascade');
            $table->foreign('activity_id')->references('id')->on('activities')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plan_items', function (Blueprint $table) {
            $table->dropForeign(['travel_plan_id']);
            $table->dropForeign(['activity_id']);
            $table->dropColumn(['travel_plan_id', 'activity_id']);
        });
    }
};
