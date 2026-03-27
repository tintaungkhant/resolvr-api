<?php

namespace App\Services;

use App\Models\Client;
use App\Models\User;

class ClientProfileService
{
    public function forUser(User $user): Client
    {
        $user->load('client.organization');

        return $user->client;
    }
}
