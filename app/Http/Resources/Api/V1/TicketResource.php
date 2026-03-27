<?php

namespace App\Http\Resources\Api\V1;

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
            'organization_id' => $this->resource->organization_id,
            'issuer_id' => $this->resource->issuer_id,
            'assignee_id' => $this->resource->assignee_id,
            'title' => $this->resource->title,
            'description' => $this->resource->description,
            'priority' => $this->resource->priority,
            'status' => $this->resource->status,
            'sla_status' => $this->resource->sla_status,
            'sla_resolution_time' => $this->resource->sla_resolution_time,
            'sla_paused_time' => $this->resource->sla_paused_time,
            'started_at' => $this->resource->started_at,
            'due_at' => $this->resource->due_at,
            'resolved_at' => $this->resource->resolved_at,
            'overdue_at' => $this->resource->overdue_at,
            'last_sla_paused_at' => $this->resource->last_sla_paused_at,
            'updated_at' => $this->resource->updated_at,
            'created_at' => $this->resource->created_at,
        ];
    }
}
