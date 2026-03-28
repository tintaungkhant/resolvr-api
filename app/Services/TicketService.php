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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class TicketService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Ticket>
     */
    public function paginateForAgent(User $user, string $type, array $filters = []): LengthAwarePaginator
    {
        $query = Ticket::query()->with(['assignee', 'issuer', 'organization']);

        if ($type === 'mine') {
            $query->where('assignee_id', $user->id);
        }

        return $this->applyFilters($query, $filters)->latest('id')->paginate();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Ticket>
     */
    public function paginateForClient(User $user, string $type, array $filters = []): LengthAwarePaginator
    {
        $query = Ticket::query();

        if ($type === 'mine') {
            $query->where('issuer_id', $user->id);
        } else {
            $client = $user->client;
            $query->where('organization_id', $client?->organization_id);
        }

        return $this->applyFilters($query, $filters)->latest('id')->paginate();
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
        $this->validateIfUpdatable($ticket);

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
        $this->validateIfUpdatable($ticket);

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

    public function updateAssignee(Ticket $ticket, int $assigneeId): Ticket
    {
        $ticket->update([
            'assignee_id' => $assigneeId,
        ]);

        return $ticket;
    }

    /**
     * @param  Builder<Ticket>  $query
     * @param  array<string, mixed>  $filters
     * @return Builder<Ticket>
     */
    private function applyFilters(Builder $query, array $filters): Builder
    {
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhereHas('messages', function ($mq) use ($search) {
                        $mq->where('content', 'like', "%{$search}%");
                    });
            });
        }

        if (! empty($filters['organization_id'])) {
            $query->where('organization_id', $filters['organization_id']);
        }

        if (! empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['sla_status'])) {
            $query->where('sla_status', $filters['sla_status']);
        }

        if (! empty($filters['started_from'])) {
            $query->whereDate('started_at', '>=', $filters['started_from']);
        }

        if (! empty($filters['started_to'])) {
            $query->whereDate('started_at', '<=', $filters['started_to']);
        }

        if (! empty($filters['due_from'])) {
            $query->whereDate('due_at', '>=', $filters['due_from']);
        }

        if (! empty($filters['due_to'])) {
            $query->whereDate('due_at', '<=', $filters['due_to']);
        }

        return $query;
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

    private function validateIfUpdatable(Ticket $ticket): void
    {
        if (! in_array($ticket->status, [TicketStatus::Open, TicketStatus::OnHold], true)) {
            throw ValidationException::withMessages([
                'status' => ['Only open or on-hold tickets can be updated'],
            ]);
        }
    }
}
