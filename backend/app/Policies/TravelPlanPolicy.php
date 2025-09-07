<?php

namespace App\Policies;

use App\Models\TravelPlan;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class TravelPlanPolicy //Omogućava da korisnik pristupa samo svojim planovima, dok admin pristupa samo pregledu tuđih planova
{

    public function viewAny(User $user): bool
    {
        // Svi ulogovani mogu listanje, ali filter u kontroleru
        return true;
    }

    public function view(User $user, TravelPlan $plan): bool
    {
        if ($user->is_admin) {
            return $plan->user_id !== $user->id; // admin vidi SVE TUĐE
        }
        return $plan->user_id === $user->id;     // user vidi SVOJE
    }

    public function create(User $user): bool
    {
        return !$user->is_admin; // admin ne kreira planove
    }

    public function update(User $user, TravelPlan $plan): bool
    {
        return !$user->is_admin && $plan->user_id === $user->id; // admin ne kreira planove i običan user može da menja samo svoje planove
    }

    public function delete(User $user, TravelPlan $plan): bool
    {
        return !$user->is_admin && $plan->user_id === $user->id;
    }

    public function export(User $user, TravelPlan $plan): bool
    {
        // admin ne exportuje svoje (nema ih), niti tuđe; user samo svoj
        return !$user->is_admin && $plan->user_id === $user->id;
    }

    public function search(User $user): bool
    {
        return true; // filter u kontroleru rešava ko šta vidi
    }
}
