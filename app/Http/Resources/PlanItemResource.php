<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            //'id'             => $this->id,
            //'travel_plan_id' => $this->travel_plan_id,
            //'activity_id'    => $this->activity_id,
            'name'           => $this->name,
            'time_from'      => $this->time_from,
            'time_to'        => $this->time_to,
            'amount'         => (float) $this->amount,
            
            'activity'       => new ActivityResource($this->whenLoaded('activity')),
        ];
    }
}
