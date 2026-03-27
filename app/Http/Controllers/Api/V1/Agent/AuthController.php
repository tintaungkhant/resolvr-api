<?php

namespace App\Http\Controllers\Api\V1\Agent;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Agent\LoginRequest;
use App\Services\AgentAuthService;

class AuthController extends Controller
{
    public function __construct(
        private AgentAuthService $agentAuthService,
    ) {}

    public function login(LoginRequest $request)
    {
        $payload = $this->agentAuthService->login(
            $request->validated('email'),
            $request->validated('password'),
        );

        if ($payload === null) {
            return errorResponse(null, 'Invalid credentials');
        }

        return successResponse($payload);
    }
}
