<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventResource extends JsonResource
{
    public static $wrap = 'event';

    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'title'       => $this->title,
            'slug'        => $this->slug,
            'description' => $this->description,
            'venue'       => $this->venue,
            'city'        => $this->city,
            'start_at'    => optional($this->start_at)?->toISOString(),
            'end_at'      => optional($this->end_at)?->toISOString(),
            'ticket_types' => TicketTypeResource::collection($this->whenLoaded('ticketTypes')),
            'purchases'    => PurchaseResource::collection($this->whenLoaded('purchases')),
        ];
    }
}
