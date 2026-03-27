<?php

namespace App\Http\Controllers\Api\V1\Agent;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ProfileResource;
use App\Services\AgentProfileService;
use Illuminate\Http\Request;

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
