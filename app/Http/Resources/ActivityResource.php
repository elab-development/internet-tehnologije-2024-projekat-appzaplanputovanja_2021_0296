<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivityResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
       return [
            'id'                  => $this->id,
            'type'                => $this->type,        // enum
            //'name'                => $this->name,
            'price'               => (float) $this->price,
            'duration'            => (int) $this->duration, // minuti
            'location'            => $this->location,

            //mergeWhen-dodaje vise polja odjednom ako je uslov ispunjen
            $this->mergeWhen(!is_null($this->content), ['content' => $this->content,]),
            $this->mergeWhen(!empty($this->preference_types), ['preference_types' => $this->preference_types,]),
            
            // Dodajemo transport_mode i accommodation_class samo ako su relevantni
            'transport_mode' => $this->when(
                $this->type === 'Transport' && !is_null($this->transport_mode),
                $this->transport_mode
            ),
            'accommodation_class' => $this->when(
                $this->type === 'Accommodation' && !is_null($this->accommodation_class),
                $this->accommodation_class
            ),

            //'created_at'          => optional($this->created_at)->toISOString(),
            //'updated_at'          => optional($this->updated_at)->toISOString(),
        ];
    }
}
