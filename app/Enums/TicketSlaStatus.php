<?php

namespace App\Enums;

enum TicketSlaStatus: string
{
    case OnTrack = 'on-track';
    case DueSoon = 'due-soon';
    case Overdue = 'overdue';
}
