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
            return response()->json([
                'message' => 'Invalid credentials',
            ], 401);
        }

        if (!Hash::check($request->password, $profile->password)) {
            return response()->json([
                'message' => 'Invalid credentials',
            ], 401);
        }

        $user = $profile->user;

        return response()->json([
            'message' => 'Login successful',
            'token' => $user->createToken('auth_token', ['role:'.$user->role->value])->plainTextToken,
        ]);
    }
}
