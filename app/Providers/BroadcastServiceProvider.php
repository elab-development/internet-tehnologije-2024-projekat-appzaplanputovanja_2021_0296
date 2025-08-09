<?php

namespace App\Providers;

use Illuminate\Broadcasting\BroadcastServiceProvider as BaseBroadcastServiceProvider;
use Illuminate\Support\Facades\Broadcast;

class BroadcastServiceProvider extends BaseBroadcastServiceProvider
{
    public function boot(): void
    {
        Broadcast::routes();

        /*
         * Opcionalno: učitaj sve kanale iz routes/channels.php
         */
        //require base_path('routes/channels.php');
    }
}

