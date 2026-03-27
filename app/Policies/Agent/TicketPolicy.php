<?php

namespace App\Policies\Agent;

use App\Models\User;
use App\Models\Ticket;

class TicketPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->agent()->exists();
    }

    public function view(User $user, Ticket $ticket): bool
    {
        return true;
    }

    public function update(User $user, Ticket $ticket): bool
    {
        return $ticket->assignee_id !== null
            && (int) $ticket->assignee_id === (int) $user->id;
    }
}
