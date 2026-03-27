<?php

namespace App\Services;

use App\Models\Client;
use Illuminate\Support\Facades\Hash;

class ClientAuthService
{
    /**
     * @return array{token: string}|null
     */
    public function login(string $email, string $password): ?array
    {
        $profile = Client::where('email', $email)->first();

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
