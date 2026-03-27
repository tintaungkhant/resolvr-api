<?php

namespace App\Http\Resources\V1\Client;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
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
            'role' => $this->resource->role,
            'profile' => $this->whenLoaded(
                'client',
                fn() => ProfileResource::make($this->resource->client)
            ),
        ];
    }
}
