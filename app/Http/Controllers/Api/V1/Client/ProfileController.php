<?php

namespace App\Http\Controllers\Api\V1\Client;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Services\ClientProfileService;
use App\Http\Resources\Api\V1\ProfileResource;

class ProfileController extends Controller
{
    public function __construct(
        private ClientProfileService $clientProfileService,
    ) {}

    public function show(Request $request)
    {
        $profile = $this->clientProfileService->forUser($request->user());

        return successResponse(ProfileResource::make($profile));
    }
}
