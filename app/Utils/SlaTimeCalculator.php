<?php

namespace App\Utils;

use App\Models\Ticket;
use App\Enums\TicketStatus;
use App\Enums\TicketSlaStatus;
use Illuminate\Support\Carbon;

class SlaTimeCalculator
{
    public static function calcConsumedTime(Ticket $ticket): int
    {
        $totalPausedTime = $ticket->sla_paused_time;
        if ($ticket->status === TicketStatus::OnHold && $ticket->last_sla_paused_at !== null) {
            $totalPausedTime += $ticket->last_sla_paused_at->diffInSeconds(now());
        }

        $totalDuration = $ticket->created_at !== null ? $ticket->created_at->diffInSeconds(now()) : 0;

        $totalConsumedTime = $totalDuration - $totalPausedTime;

        return max(0, (int) ceil($totalConsumedTime));
    }

    public static function calcSlaPercentage(Ticket $ticket): int
    {
        if ($ticket->sla_resolution_time <= 0) {
            return 0;
        }

        $consumedTime = self::calcConsumedTime($ticket);

        return (int) ceil($consumedTime / $ticket->sla_resolution_time * 100);
    }

    public static function calcSlaStatus(Ticket $ticket): TicketSlaStatus
    {
        $slaPercentage = self::calcSlaPercentage($ticket);

        if ($slaPercentage < 80) {
            return TicketSlaStatus::OnTrack;
        }

        if ($slaPercentage < 100) {
            return TicketSlaStatus::DueSoon;
        }

        return TicketSlaStatus::Overdue;
    }

    public static function calcDueAt(Ticket $ticket): Carbon
    {
        /** @var Carbon $createdAt */
        $createdAt = $ticket->created_at;
        $dueAt = $createdAt->copy()->addSeconds($ticket->sla_resolution_time + $ticket->sla_paused_time);

        if ($ticket->status === TicketStatus::OnHold && $ticket->last_sla_paused_at !== null) {
            $currentHoldDuration = $ticket->last_sla_paused_at->diffInSeconds(now());
            $dueAt->addSeconds($currentHoldDuration);
        }

        return $dueAt;
    }
}
