<?php

namespace App\Enums;

enum TicketStatus: string
{
    case Open = 'open';
    case OnHold = 'on-hold';
    case Archived = 'archived';
    case Resolved = 'resolved';
}
