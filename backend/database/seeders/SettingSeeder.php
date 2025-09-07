<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Setting;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $pairs = [
            ['key' => 'outbound_start',            'value' => '08:00'],
            ['key' => 'checkin_time',              'value' => '14:00'],
            ['key' => 'checkout_time',             'value' => '09:00'],
            ['key' => 'return_start',              'value' => '15:00'],
            ['key' => 'buffer_after_outbound_min', 'value' => '30'],
            ['key' => 'buffer_before_return_min',  'value' => '0'],
            ['key' => 'default_day_start',         'value' => '09:00'],
            ['key' => 'default_day_end',           'value' => '20:00'],
        ];

        foreach ($pairs as $p) {
            Setting::setValue($p['key'], $p['value']);
        }
    }
}
