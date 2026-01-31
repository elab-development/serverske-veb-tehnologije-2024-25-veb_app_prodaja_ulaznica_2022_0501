<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseResource extends JsonResource
{
    public static $wrap = 'purchase';

    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'user_id'        => $this->user_id,
            'event_id'       => $this->event_id,
            'ticket_type_id' => $this->ticket_type_id,
            'quantity'       => (int) $this->quantity,
            'unit_price'     => (float) $this->unit_price,
            'total_amount'   => (float) $this->total_amount,
            'status'         => $this->status,
            'reserved_until' => optional($this->reserved_until)?->toISOString(),
            'user'        => new UserResource($this->whenLoaded('user')),
            'event'       => new EventResource($this->whenLoaded('event')),
            'ticket_type' => new TicketTypeResource($this->whenLoaded('ticketType')),
        ];
    }
}
