<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TravelPlanResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'user_id'            => $this->user_id,
            'user'               => new UserResource($this->whenLoaded('user')),
            'start_location'     => $this->start_location,
            'destination'        => $this->destination,
            'start_date'         => $this->start_date,
            'end_date'           => $this->end_date,
            'budget'             => (float) $this->budget,
            'total_cost'         => (float) $this->total_cost,
            'passenger_count'    => (int) $this->passenger_count,

            $this->mergeWhen(!empty($this->preferences), ['preferences' => $this->preferences,]),
            'transport_mode'     => $this->transport_mode,    // enum
            'accommodation_class'=> $this->accommodation_class, // enum

            'plan_items'         => PlanItemResource::collection($this->whenLoaded('planItems')),

            //'created_at'         => optional($this->created_at)->toISOString(),
            //'updated_at'         => optional($this->updated_at)->toISOString(),
        ];
    }
}
