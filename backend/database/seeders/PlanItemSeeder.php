<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Carbon\Carbon;
use App\Models\TravelPlan;
use App\Models\PlanItem;
use App\Services\TravelPlanStoreService;

class PlanItemSeeder extends Seeder
{
    /**
     * Popunjava postojeće TravelPlan-ove:
     *  - osigura 2x Transport + 1x Accommodation (ako fale)
     *  - doda neobavezne aktivnosti koliko budžet i vreme dozvole
     */
    public function run(): void
    {
        $plans = TravelPlan::with('planItems.activity')->get();
        if ($plans->isEmpty()) return;

        /** @var TravelPlanStoreService $svc */
        $svc = app(TravelPlanStoreService::class);

        foreach ($plans as $plan) {
            // Uvek radi na svežim podacima
            $plan->load('planItems.activity');

            // --- 1) Proveri da li postoje mandatory stavke
            $hasOutbound = $plan->planItems->first(function ($pi) use ($plan) {
                return optional($pi->activity)->type === 'Transport'
                    && Carbon::parse($pi->time_from)->isSameDay($plan->start_date);
            });

            $hasReturn = $plan->planItems->first(function ($pi) use ($plan) {
                return optional($pi->activity)->type === 'Transport'
                    && Carbon::parse($pi->time_from)->isSameDay($plan->end_date);
            });

            $hasAccommodation = $plan->planItems->first(function ($pi) {
                return optional($pi->activity)->type === 'Accommodation';
            });

            // --- 2) Ako bilo koja obavezna stavka fali, generiši sve obavezne iz servisa
            if (!$hasOutbound || !$hasReturn || !$hasAccommodation) {
                // Servis će odabrati najjeftinije varijante i napraviti:
                // outbound, accommodation (za ceo boravak), return
                $svc->generateMandatoryItems($plan);
                $plan->refresh()->load('planItems.activity');
            }

            // --- 3) Popuni plan dodatnim (neobaveznim) aktivnostima
            $svc->fillWithMatchingActivities($plan);

            // (opciono) ponovo učitaj – čisto da bude konzistentno za sledeći krug
            $plan->refresh();
        }
    }
}
