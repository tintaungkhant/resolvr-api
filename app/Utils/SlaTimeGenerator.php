<?php

namespace App\Utils;

use App\Enums\TicketSlaPriority;

class SlaTimeGenerator
{
    public static function generate(TicketSlaPriority $priority): int
    {
        return match ($priority) {
            TicketSlaPriority::Low    => 8 * 60 * 60,
            TicketSlaPriority::Medium => 6 * 60 * 60,
            TicketSlaPriority::High   => 4 * 60 * 60,
            TicketSlaPriority::Urgent => 2 * 60 * 60,
        };
    }
}
