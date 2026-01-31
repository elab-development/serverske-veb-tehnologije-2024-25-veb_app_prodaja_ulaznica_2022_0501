<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketTypeResource extends JsonResource
{
    public static $wrap = 'ticket_type';

    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'event_id'        => $this->event_id,
            'name'            => $this->name,
            'category'        => $this->category,
            'price'           => (float) $this->price,
            'quantity_total'  => (int) $this->quantity_total,
            'quantity_sold'   => (int) $this->quantity_sold,
            'sales_start_at'  => optional($this->sales_start_at)?->toISOString(),
            'sales_end_at'    => optional($this->sales_end_at)?->toISOString(),
            'is_active'       => (bool) $this->is_active,
            'event'     => new EventResource($this->whenLoaded('event')),
            'purchases' => PurchaseResource::collection($this->whenLoaded('purchases')),
        ];
    }
}
