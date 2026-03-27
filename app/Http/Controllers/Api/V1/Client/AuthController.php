<?php

namespace App\Http\Controllers\Api\V1\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Client\LoginRequest;
use App\Services\ClientAuthService;

class AuthController extends Controller
{
    public function __construct(
        private ClientAuthService $clientAuthService,
    ) {}

    public function login(LoginRequest $request)
    {
        $payload = $this->clientAuthService->login(
            $request->validated('email'),
            $request->validated('password'),
        );

        if ($payload === null) {
            return errorResponse(null, 'Invalid credentials');
        }

        return successResponse($payload);
    }
}
