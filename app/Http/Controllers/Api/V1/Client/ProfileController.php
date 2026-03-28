<?php

namespace App\Http\Controllers\Api\V1\Client;

use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Services\ClientProfileService;
use App\Http\Resources\Api\V1\ProfileResource;

class ProfileController extends Controller
{
    public function __construct(
        private ClientProfileService $clientProfileService,
    ) {}

    public function show(): JsonResponse
    {
        $user = $this->authUser();

        $profile = $this->clientProfileService->forUser($user);

        return successResponse(ProfileResource::make($profile));
    }
}
