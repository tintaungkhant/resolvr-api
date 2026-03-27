<?php

namespace App\Http\Controllers\Api\V1\Agent;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Services\AgentProfileService;
use App\Http\Resources\Api\V1\ProfileResource;

class ProfileController extends Controller
{
    public function __construct(
        private AgentProfileService $agentProfileService,
    ) {}

    public function show(Request $request)
    {
        $profile = $this->agentProfileService->forUser($request->user());

        return successResponse(ProfileResource::make($profile));
    }
}
