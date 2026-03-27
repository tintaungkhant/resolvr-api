<?php

namespace App\Http\Resources\Api\V1\Client;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketMessageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            "id" => $this->resource->id,
            "ticket_id" => $this->resource->ticket_id,
            "user_id" => $this->resource->user_id,
            "content" => $this->resource->content,
            "is_internal" => $this->resource->is_internal,
            "created_at" => $this->resource->created_at,
        ];
    }
}
