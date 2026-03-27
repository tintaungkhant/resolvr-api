<?php

namespace App\Http\Controllers\Api\V1\Agent;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Agent\LoginRequest;
use App\Models\Agent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(LoginRequest $request)
    {
        $profile = Agent::where('email', $request->email)->first();

        if (!$profile) {
            return errorResponse(null, 'Invalid credentials');
        }

        if (!Hash::check($request->password, $profile->password)) {
            return errorResponse(null, 'Invalid credentials');
        }

        $user = $profile->user;

        return successResponse([
            'token' => $user->createToken('auth_token', ['role:'.$user->role->value])->plainTextToken,
        ]);
    }
}
