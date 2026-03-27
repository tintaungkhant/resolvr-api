<?php

namespace App\Services;

use App\Models\User;
use App\Models\Agent;

class AgentProfileService
{
    public function forUser(User $user): ?Agent
    {
        return $user->agent;
    }
}
