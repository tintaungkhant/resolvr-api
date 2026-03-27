<?php

namespace App\Services;

use App\Models\User;
use App\Models\Client;

class ClientProfileService
{
    public function forUser(User $user): ?Client
    {
        $user->load('client.organization');

        return $user->client;
    }
}
