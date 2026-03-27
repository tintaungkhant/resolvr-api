<?php

namespace App\Services;

use App\Models\Agent;
use Illuminate\Support\Facades\Hash;

class AgentAuthService
{
    public function login(string $email, string $password): ?array
    {
        $profile = Agent::where('email', $email)->first();

        if (! $profile || ! Hash::check($password, $profile->password)) {
            return null;
        }

        $user = $profile->user;

        return [
            'token' => $user->createToken('auth_token', ['role:'.$user->role->value])->plainTextToken,
        ];
    }
}
