<?php

namespace App\Services;

use App\Models\Agent;
use Illuminate\Support\Facades\Hash;

class AgentAuthService
{
    /**
     * @return array{token: string}|null
     */
    public function login(string $email, string $password): ?array
    {
        $profile = Agent::where('email', $email)->first();

        if (! $profile || ! Hash::check($password, $profile->password)) {
            return null;
        }

        $user = $profile->user;

        if (! $user) {
            return null;
        }

        return [
            'token' => $user->createToken('auth_token', ['role:'.$user->role->value])->plainTextToken,
        ];
    }
}
