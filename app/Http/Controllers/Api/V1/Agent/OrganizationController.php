<?php

namespace App\Http\Controllers\Api\V1\Agent;

use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;

class OrganizationController extends Controller
{
    public function index(): JsonResponse
    {
        $organizations = Organization::orderBy('name')
            ->get()
            ->map(fn (Organization $org) => [
                'id'   => $org->id,
                'name' => $org->name,
            ]);

        return successResponse($organizations);
    }
}
