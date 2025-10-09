<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\TravelPlan;
use App\Models\PlanItem;
use App\Policies\TravelPlanPolicy;
use App\Policies\PlanItemPolicy;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        PlanItem::class   => PlanItemPolicy::class,
        TravelPlan::class => TravelPlanPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}
