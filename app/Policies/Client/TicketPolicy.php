<?php

namespace App\Policies\Client;

use App\Models\User;
use App\Models\Ticket;

class TicketPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->client()->exists();
    }

    public function view(User $user, Ticket $ticket): bool
    {
        return (int) $user->client?->organization_id === (int) $ticket->organization_id;
    }

    public function create(User $user): bool
    {
        return $user->client()->exists();
    }

    public function update(User $user, Ticket $ticket): bool
    {
        return $this->view($user, $ticket) && $user->id === $ticket->issuer_id;
    }
}
