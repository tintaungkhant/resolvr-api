<?php

namespace App\Http\Controllers\Api\V1\Client;

use App\Models\Ticket;
use Illuminate\Http\Request;
use App\Services\TicketService;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\TicketResource;
use App\Http\Requests\Api\V1\Client\TicketStoreRequest;
use App\Policies\Client\TicketPolicy as ClientTicketPolicy;

class TicketController extends Controller
{
    public function __construct(
        private TicketService $ticketService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorizePolicy(ClientTicketPolicy::class, 'viewAny');

        $user = $this->authUser();

        $type = $request->query('type', 'mine');
        $filters = $request->only([
            'search', 'priority', 'status', 'sla_status',
            'started_from', 'started_to', 'due_from', 'due_to',
        ]);
        $tickets = $this->ticketService->paginateForClient($user, $type, $filters);

        return successResponse(TicketResource::collection($tickets));
    }

    public function store(TicketStoreRequest $request): JsonResponse
    {
        $this->authorizePolicy(ClientTicketPolicy::class, 'create');

        $user = $this->authUser();

        $ticket = $this->ticketService->createForClient(
            $user,
            $request->validated('title'),
            $request->validated('description'),
            $request->ticketSlaPriority(),
        );

        return successResponse(TicketResource::make($ticket));
    }

    public function show(Ticket $ticket): JsonResponse
    {
        $this->authorizePolicy(ClientTicketPolicy::class, 'view', $ticket);

        return successResponse(TicketResource::make($ticket));
    }
}
