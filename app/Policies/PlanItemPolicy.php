<?php

namespace App\Policies;

use App\Models\PlanItem;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class PlanItemPolicy // Omogućava da korisnik pristupa samo pregledu svojih stavki planova, dok admin pristupa tuđim stavkama
{
    /** index nad stavkama jednog plana */
    public function viewAny(User $user, TravelPlan $plan): bool
    {
        if ($user->is_admin) {
            return $plan->user_id !== $user->id; // admin samo TUĐE
        }
        return $plan->user_id === $user->id;     // običan user samo SVOJE
    }

    /** show jedne stavke-sa endpoint */
    /**public function view(User $user, PlanItem $item): bool
    {
        $ownerId = $item->travelPlan->user_id;
        if ($user->is_admin) {
            return $ownerId !== $user->id;
        }
        return $ownerId === $user->id;
    }**/

    /** create nad stavkama KONKRETNOG plana */
    public function create(User $user, TravelPlan $plan): bool
    {
        if ($user->is_admin) {
            return $plan->user_id !== $user->id; // admin sme TUĐE
        }
        return false; // običan user NE sme da kreira/menja/briše stavke
    }

    public function update(User $user, PlanItem $item): bool
    {
        $ownerId = $item->travelPlan->user_id;
        if ($user->is_admin) {
            return $ownerId !== $user->id;
        }
        return false;
    }

    public function delete(User $user, PlanItem $item): bool
    {
        $ownerId = $item->travelPlan->user_id;
        if ($user->is_admin) {
            return $ownerId !== $user->id;
        }
        return false;
    }
}
