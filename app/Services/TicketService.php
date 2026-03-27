<?php

namespace App\Services;

use App\Models\User;
use App\Models\Agent;
use App\Models\Ticket;
use App\Enums\TicketStatus;
use App\Utils\SlaTimeGenerator;
use App\Enums\TicketSlaPriority;
use App\Utils\SlaTimeCalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class TicketService
{
    /**
     * @return LengthAwarePaginator<int, Ticket>
     */
    public function paginateForAgent(User $user, string $type): LengthAwarePaginator
    {
        if ($type === 'mine') {
            return Ticket::where('assignee_id', $user->id)->latest('id')->paginate();
        }

        return Ticket::latest('id')->paginate();
    }

    /**
     * @return LengthAwarePaginator<int, Ticket>
     */
    public function paginateForClient(User $user, string $type): LengthAwarePaginator
    {
        if ($type === 'mine') {
            return Ticket::where('issuer_id', $user->id)->latest('id')->paginate();
        }

        $client = $user->client;

        return Ticket::where('organization_id', $client?->organization_id)->latest('id')->paginate();
    }

    public function createForClient(User $user, string $title, string $description, TicketSlaPriority $priority): Ticket
    {
        $slaResolutionTime = SlaTimeGenerator::generate($priority);
        $client = $user->client;
        $organizationId = $client?->organization_id;

        return DB::transaction(function () use ($user, $title, $description, $priority, $slaResolutionTime, $organizationId) {
            $ticket = Ticket::create([
                'organization_id'     => $organizationId,
                'issuer_id'           => $user->id,
                'assignee_id'         => $this->resolveAssigneeUserId(),
                'title'               => $title,
                'description'         => $description,
                'priority'            => $priority,
                'started_at'          => now(),
                'sla_resolution_time' => $slaResolutionTime,
                'due_at'              => now()->addSeconds($slaResolutionTime),
            ]);

            $ticket->messages()->create([
                'user_id'     => $user->id,
                'content'     => $description,
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
            'priority'            => $priority,
        ]);

        if ($lastPriority !== $ticket->priority) {
            $ticket->update([
                'due_at'     => SlaTimeCalculator::calcDueAt($ticket),
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
            $pausedSeconds = $ticket->last_sla_paused_at?->diffInSeconds(now()) ?? 0;
            $ticket->update([
                'sla_paused_time' => ceil($ticket->sla_paused_time + $pausedSeconds),
            ]);
        }

        return $ticket;
    }

    private function resolveAssigneeUserId(): ?int
    {
        $activeStatuses = [TicketStatus::Open->value, TicketStatus::OnHold->value];

        $activeTicketCounts = Ticket::query()
            ->selectRaw('assignee_id, COUNT(*) as active_count')
            ->whereIn('status', $activeStatuses)
            ->whereNotNull('assignee_id')
            ->groupBy('assignee_id');

        return Agent::query()
            ->leftJoinSub($activeTicketCounts, 'ticket_counts', function ($join): void {
                $join->on('agents.user_id', '=', 'ticket_counts.assignee_id');
            })
            ->orderByRaw('COALESCE(ticket_counts.active_count, 0) asc')
            ->orderBy('agents.user_id')
            ->value('agents.user_id');
    }
}
