<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Activity;
use App\Models\TravelPlan;
use App\Models\PlanItem;
use App\Models\Setting;
use App\Services\TravelPlanStoreService;
use Symfony\Component\HttpKernel\Exception\HttpException;

class TravelPlanSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::where('is_admin', false)->get();
        if ($users->isEmpty()) {
            $users = User::factory()->count(2)->create();
        }

       $destinations = Activity::query()->distinct()->pluck('location')->toArray();
        $startLocations = Activity::query()
            ->where('type','Transport')
            ->whereNotNull('start_location')
            ->distinct()->pluck('start_location')->toArray();

        $svc = app(TravelPlanStoreService::class);

        // UZMI LISTU SVIH POSTOJEĆIH TRANSPORT RUTA (sa modom)
        $routes = Activity::query()
            ->where('type', 'Transport')
            ->whereNotNull('start_location')
            ->whereNotNull('location')
            ->whereNotNull('transport_mode')
            ->get(['start_location','location','transport_mode'])
            ->unique(fn($r) => $r->start_location.'|'.$r->location.'|'.$r->transport_mode)
            ->values();

        if ($routes->isEmpty()) {
            throw new \RuntimeException('No transport routes seeded. Ensure ActivitySeeder creates both directions.');
        }

        foreach (range(1, rand(8,12)) as $i) {
            $user = $users->random();

            // 1) IZABERI REALNU RUTU KOJA POSTOJI
            $route = $routes->random();
            $startLocation = $route->start_location;
            $destination   = $route->location;
            $mode          = $route->transport_mode;

            // 2) PROVERI DA LI POSTOJE OBE CENE (skuplja i jeftinija) za istu rutu i mode
            $count = Activity::query()
                ->where('type','Transport')
                ->where('start_location', $startLocation)
                ->where('location', $destination)
                ->where('transport_mode', $mode)
                ->count();

            if ($count < 2) {
                // ako nema oba zapisa (outbound + return), preskoči kombinaciju
                $i--;
                continue;
            }
            // 3) UZMI POSTOJEĆU SMEŠTAJNU KLASU ZA TU DESTINACIJU
            $accClass = Activity::query()
                ->where('type','Accommodation')
                ->where('location', $destination)
                ->whereNotNull('accommodation_class')
                ->inRandomOrder()
                ->value('accommodation_class');

            if (!$accClass) {
                // nema smeštaja u toj destinaciji – probaj drugi route
                $i--;
                continue;
            }

            // 4) OSTALE VREDNOSTI
            $prefsAll = Activity::availablePreferenceTypes();
            shuffle($prefsAll);
            $prefs = array_slice($prefsAll, 0, rand(2,5));

            $passengers = rand(2,4);
            $startDate = now()->addDays(rand(10,90))->startOfDay()->toDateString();
            $endDate   = now()->parse($startDate)->addDays(rand(2,4))->toDateString();
            $days      = Carbon::parse($endDate)->diffInDays($startDate);
            $nights = max(1, (int)$days);
                        
            // Izračunaj minimalne obavezne troškove da ih uklopis u budzet
            $outbound = Activity::where('type','Transport')
                ->where('start_location', $startLocation)
                ->where('location', $destination)
                ->where('transport_mode', $mode)
                ->orderBy('price','desc')
                ->first();

            $return = Activity::where('type','Transport')
                ->where('start_location', $startLocation)
                ->where('location', $destination)
                ->where('transport_mode', $mode)
                ->orderBy('price','asc')
                ->first();

            $accommodation = Activity::where('type','Accommodation')
                ->where('location', $destination)
                ->where('accommodation_class', $accClass)
                ->orderBy('price','asc')
                ->first();

             // Sigurnosne vrednosti (ako iz seedera neka cena dođe null)
            $tpOut  = (int) max(0, (int) ($outbound->price ?? 0));
            $tpRet  = (int) max(0, (int) ($return->price ?? 0));
            $accP   = (int) max(0, (int) ($accommodation->price ?? 0));

            // Minimalni trošak za sve putnike
            $mandatory = ($tpOut + $tpRet + ($accP * $nights) ) * $passengers;

            // Dodaj realan dnevni budžet (60–120$ po osobi)
            // Više dnevnog budžeta po osobi + dodatna rezerva za iskustva
            $perDayPerPerson   = rand(90, 110); 
            $variablePart      = $perDayPerPerson * $passengers * max(1, $days);

            // Rezerva nezavisna od obaveznih (npr. ulaznice, ture…)
            $experienceReserve = rand(50, 100) * $passengers * max(1, $days);

            // preko svega  jos 20–30%
            $marginFactor = [1.20, 1.30, 1.35][array_rand([1,2,3])];

            // Konačni budžet: obavezno + dnevno + rezerva, pa margina
            $budget = (int) ceil(max($mandatory * 1.6,  // bar 60% preko mandatory
                                    ($mandatory + $variablePart + $experienceReserve) * $marginFactor));

            $minAllowed = max($mandatory, 100);  // barem mandatory, i barem 100
            if ($budget < $minAllowed) {
                $budget = (int) ceil($minAllowed * 1.10); // malo iznad minimuma
            }

            // 5) PROSLEDI SERVISU (KOJI KREIRA 3 OBAVEZNE + OSTALO)
            $payload = [
                'user_id'             => $user->id,
                'start_location'      => $startLocation,
                'destination'         => $destination,
                'start_date'          => $startDate,
                'end_date'            => $endDate,
                'budget'              => $budget,
                'passenger_count'     => $passengers,
                'preferences'         => $prefs,
                'transport_mode'      => $mode,
                'accommodation_class' => $accClass,
            ];

            try {
                $svc->createWithGeneratedItems($payload);
            } catch (HttpException $e) {
                if ($e->getStatusCode() === 422) {
                    $payload['budget'] = (int) ceil($payload['budget'] * 1.25);
                    $svc->createWithGeneratedItems($payload);
                } else {
                    throw $e;
                }
            }

        }
    }
}