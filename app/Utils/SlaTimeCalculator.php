<?php

namespace App\Utils;

use App\Enums\TicketSlaStatus;
use App\Enums\TicketStatus;
use App\Models\Ticket;

class SlaTimeCalculator
{
    public static function calcConsumedTime(Ticket $ticket)
    {
        $totalPausedTime = $ticket->sla_paused_time;
        if($ticket->status === TicketStatus::OnHold){
            $totalPausedTime += $ticket->last_sla_paused_at->diffInSeconds(now());
        }

        $totalDuration = $ticket->created_at->diffInSeconds(now());

        $totalConsumedTime = $totalDuration - $totalPausedTime;

        return max(0, ceil($totalConsumedTime));
    }

    public static function calcSlaPercentage(Ticket $ticket)
    {
        $consumedTime = self::calcConsumedTime($ticket);

        return ceil($consumedTime / $ticket->sla_resolution_time * 100);
    }

    public static function calcSlaStatus(Ticket $ticket)
    {
        $slaPercentage = self::calcSlaPercentage($ticket);

        switch($slaPercentage){
            case $slaPercentage < 80:
                return TicketSlaStatus::OnTrack;
            case $slaPercentage < 100:
                return TicketSlaStatus::DueSoon;
            default:
                return TicketSlaStatus::Overdue;
        }
    }

    public static function calcDueAt(Ticket $ticket)
    {
        $dueAt = $ticket->created_at->addSeconds($ticket->sla_resolution_time + $ticket->sla_paused_time);

        if ($ticket->status === TicketStatus::OnHold && $ticket->last_sla_paused_at) {
            $currentHoldDuration = $ticket->last_sla_paused_at->diffInSeconds(now());
            $dueAt->addSeconds($currentHoldDuration);
        }

        return $dueAt;
    }
}