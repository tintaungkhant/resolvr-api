<?php

namespace App\Services;

use App\Models\Client;
use Illuminate\Support\Facades\Hash;

class ClientAuthService
{
    public function login(string $email, string $password): ?array
    {
        $profile = Client::where('email', $email)->first();

        if (! $profile || ! Hash::check($password, $profile->password)) {
            return null;
        }

        $user = $profile->user;

        return [
            'token' => $user->createToken('auth_token', ['role:'.$user->role->value])->plainTextToken,
        ];
    }
}
