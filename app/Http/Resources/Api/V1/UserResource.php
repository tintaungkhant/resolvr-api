<?php

namespace App\Http\Resources\Api\V1;

use App\Enums\UserRole;
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

        $resource = $this->resource;

        if ($resource->role === UserRole::Agent) {
            return [
                'id'      => $resource->agent->id,
                'user_id' => $resource->id,
                'role'    => $resource->role,
                'name'    => $resource->agent->name,
                'email'   => $resource->agent->email,
            ];
        }

        return [
            'id'           => $resource->client->id,
            'user_id'      => $resource->id,
            'role'         => $resource->role,
            'name'         => $resource->client->name,
            'email'        => $resource->client->email,
            'organization' => $this->whenLoaded(
                'organization',
                fn () => OrganizationResource::make($resource->client->organization)
            ),
        ];
    }
}
