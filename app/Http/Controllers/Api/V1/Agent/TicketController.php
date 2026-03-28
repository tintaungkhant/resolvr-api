<?php

namespace App\Http\Controllers\Api\V1\Agent;

use App\Models\Ticket;
use Illuminate\Http\Request;
use App\Services\TicketService;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\TicketResource;
use App\Http\Requests\Api\V1\TicketStatusUpdateRequest;
use App\Http\Requests\Api\V1\TicketPriorityUpdateRequest;
use App\Policies\Agent\TicketPolicy as AgentTicketPolicy;
use App\Http\Requests\Api\V1\Agent\TicketAssigneeUpdateRequest;

class TicketController extends Controller
{
    public function __construct(
        private TicketService $ticketService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorizePolicy(AgentTicketPolicy::class, 'viewAny');

        $user = $this->authUser();

        $type = $request->query('type', 'mine');
        $filters = $request->only([
            'search', 'organization_id', 'priority', 'status', 'sla_status',
            'started_from', 'started_to', 'due_from', 'due_to',
        ]);
        $tickets = $this->ticketService->paginateForAgent($user, $type, $filters);

        return successResponse(TicketResource::collection($tickets));
    }

    public function show(Ticket $ticket): JsonResponse
    {
        $this->authorizePolicy(AgentTicketPolicy::class, 'view', $ticket);

        $ticket->load(['assignee.agent', 'issuer.client', 'organization']);

        return successResponse(TicketResource::make($ticket));
    }

    public function updatePriority(Ticket $ticket, TicketPriorityUpdateRequest $request): JsonResponse
    {
        $this->authorizePolicy(AgentTicketPolicy::class, 'update', $ticket);

        $this->ticketService->updatePriority($ticket, $request->ticketSlaPriority());

        return successResponse(TicketResource::make($ticket));
    }

    public function updateStatus(Ticket $ticket, TicketStatusUpdateRequest $request): JsonResponse
    {
        $this->authorizePolicy(AgentTicketPolicy::class, 'update', $ticket);

        $this->ticketService->updateStatus($ticket, $request->ticketStatus());

        return successResponse(TicketResource::make($ticket));
    }

    public function updateAssignee(Ticket $ticket, TicketAssigneeUpdateRequest $request): JsonResponse
    {
        $this->ticketService->updateAssignee($ticket, $request->assigneeId());

        return successResponse(TicketResource::make($ticket));
    }
}
