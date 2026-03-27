<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\User;

class AgentProfileService
{
    public function forUser(User $user): Agent
    {
        return $user->agent;
    }
}
