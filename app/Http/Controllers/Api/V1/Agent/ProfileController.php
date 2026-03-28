<?php

namespace App\Http\Controllers\Api\V1\Agent;

use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Services\AgentProfileService;
use App\Http\Resources\Api\V1\ProfileResource;

class ProfileController extends Controller
{
    public function __construct(
        private AgentProfileService $agentProfileService,
    ) {}

    public function show(): JsonResponse
    {
        $user = $this->authUser();

        $profile = $this->agentProfileService->forUser($user);

        return successResponse(ProfileResource::make($profile));
    }
}
