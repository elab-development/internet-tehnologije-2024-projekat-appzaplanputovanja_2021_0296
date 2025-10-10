<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        //generiši 3 random korisnika
        //User::factory()->count(3)->create();

        
        User::firstOrCreate(
            ['email' => 'admin@example.com'],
            ['name'=>'Admin', 'password'=>'admin123', 'is_admin'=>true]
        );
        User::factory()->create([
            'name' => 'Masa',
            'email' => 'masaljekocevic@gmail.com',
            'password' => 'masa123',
        ]);
        User::factory()->create([
            'name' => 'Tasa',
            'email' => 'tamaralukovic@gmail.com',
            'password' => 'tasa123',
        ]);
        User::factory()->create([
            'name' => 'Stefan',
            'email' => 'stefan@gmail.com',
            'password' => 'stefan123',
        ]);
    }
}
