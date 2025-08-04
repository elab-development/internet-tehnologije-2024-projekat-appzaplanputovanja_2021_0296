<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('travel_plans', function (Blueprint $table) {
            DB::statement('ALTER TABLE travel_plans ADD CONSTRAINT check_budget_positive CHECK (budget >= 0)');
            DB::statement('ALTER TABLE travel_plans ADD CONSTRAINT check_passenger_count CHECK (passenger_count >= 1)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('travel_plans', function (Blueprint $table) {
            DB::statement('ALTER TABLE travel_plans DROP CONSTRAINT check_budget_positive');
            DB::statement('ALTER TABLE travel_plans DROP CONSTRAINT check_passenger_count');
        });
    }
};
