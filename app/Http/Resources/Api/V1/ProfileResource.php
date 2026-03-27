<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProfileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->resource->id,
            'user_id'      => $this->resource->user_id,
            'role'         => $this->resource->user->role,
            'name'         => $this->resource->name,
            'email'        => $this->resource->email,
            'organization' => $this->whenLoaded(
                'organization',
                fn () => OrganizationResource::make($this->resource->organization)
            ),
        ];
    }
}
