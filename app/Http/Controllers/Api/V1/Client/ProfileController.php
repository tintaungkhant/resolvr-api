<?php

namespace App\Http\Controllers\Api\V1\Client;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ProfileResource;
use App\Services\ClientProfileService;
use Illuminate\Http\Request;

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
