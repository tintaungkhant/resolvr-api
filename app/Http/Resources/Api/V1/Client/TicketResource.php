<?php

namespace App\Http\Resources\Api\V1\Client;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            "organization_id" => $this->resource->organization_id,
            "issuer_id" => $this->resource->issuer_id,
            "title" => $this->resource->title,
            "description" => $this->resource->description,
            "priority" => $this->resource->priority,
            "started_at" => $this->resource->started_at,
            "sla_resolution_time" => $this->resource->sla_resolution_time,
            "due_at" => $this->resource->due_at,
            "updated_at" => $this->resource->updated_at,
            "created_at" => $this->resource->created_at,
            "messages" => $this->whenLoaded(
                'messages',
                fn() => TicketMessageResource::collection($this->resource->messages)
            ),
        ];
    }
}
