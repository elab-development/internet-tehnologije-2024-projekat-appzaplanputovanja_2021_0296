<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\TravelPlan;
use App\Policies\TravelPlanPolicy;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        TravelPlan::class => TravelPlanPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}
