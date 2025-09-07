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
        Schema::create('activities', function (Blueprint $table) {
            $table->id(); // activity_id
            $table->enum('type', ['Transport', 'Accommodation', 'Food&Drink', 'Culture&Sightseeing',
                        'Shopping&Souvenirs', 'Nature&Adventure', 'Relaxation&Wellness',
                        'Family-Friendly','Educational&Volunteering','Entertainment&Leisure','other'
                    ]);
            $table->string('name');
            $table->decimal('price', 10, 2);
            $table->integer('duration'); // Duration in minutes
            $table->string('location');
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
