<?php

namespace App\Services;

use App\Enums\TicketSlaPriority;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use App\Utils\SlaTimeCalculator;
use App\Utils\SlaTimeGenerator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TicketService
{
    public function paginateForAgent(User $user, string $type): LengthAwarePaginator
    {
        if ($type === 'mine') {
            return Ticket::where('assignee_id', $user->agent->id)->latest('id')->paginate();
        }

        return Ticket::latest('id')->paginate();
    }

    public function paginateForClient(User $user, string $type): LengthAwarePaginator
    {
        if ($type === 'mine') {
            return Ticket::where('issuer_id', $user->id)->latest('id')->paginate();
        }

        return Ticket::where('organization_id', $user->client->organization_id)->latest('id')->paginate();
    }

    public function createForClient(User $user, string $title, string $description, TicketSlaPriority $priority): Ticket
    {
        $slaResolutionTime = SlaTimeGenerator::generate($priority);

        return DB::transaction(function () use ($user, $title, $description, $priority, $slaResolutionTime) {
            $ticket = Ticket::create([
                'organization_id' => $user->client->organization_id,
                'issuer_id' => $user->id,
                'title' => $title,
                'description' => $description,
                'priority' => $priority,
                'started_at' => now(),
                'sla_resolution_time' => $slaResolutionTime,
                'due_at' => now()->addSeconds($slaResolutionTime),
            ]);

            $ticket->messages()->create([
                'user_id' => $user->id,
                'content' => $description,
                'is_internal' => false,
            ]);

            return $ticket;
        });
    }

    public function updatePriority(Ticket $ticket, TicketSlaPriority $priority): Ticket
    {
        $lastPriority = $ticket->priority;

        $ticket->update([
            'sla_resolution_time' => SlaTimeGenerator::generate($priority),
            'priority' => $priority,
        ]);

        if ($lastPriority !== $ticket->priority) {
            $ticket->update([
                'due_at' => SlaTimeCalculator::calcDueAt($ticket),
                'sla_status' => SlaTimeCalculator::calcSlaStatus($ticket),
            ]);
        }

        return $ticket;
    }

    public function updateStatus(Ticket $ticket, TicketStatus $status): Ticket
    {
        if (! in_array($ticket->status, [TicketStatus::Open, TicketStatus::OnHold], true)) {
            throw ValidationException::withMessages([
                'status' => ['Only open or on-hold tickets can be updated'],
            ]);
        }

        $lastStatus = $ticket->status;

        $ticket->update([
            'status' => $status,
        ]);

        if ($lastStatus === TicketStatus::Open && $ticket->status === TicketStatus::OnHold) {
            $ticket->update([
                'last_sla_paused_at' => now(),
            ]);
        }

        if ($lastStatus === TicketStatus::OnHold && $ticket->status === TicketStatus::Open) {
            $ticket->update([
                'sla_paused_time' => ceil($ticket->sla_paused_time + $ticket->last_sla_paused_at->diffInSeconds(now())),
            ]);
        }

        return $ticket;
    }
}
