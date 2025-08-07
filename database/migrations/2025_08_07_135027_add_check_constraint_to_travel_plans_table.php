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
       DB::statement(
            'ALTER TABLE `travel_plans` 
             ADD CONSTRAINT `check_total_cost_budget` 
             CHECK (`total_cost` <= `budget`)'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
         DB::statement(
            'ALTER TABLE `travel_plans` 
             DROP CHECK `check_total_cost_budget`'
        );
    }
};
