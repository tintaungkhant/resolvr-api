<?php

namespace App\Http\Controllers\Api\V1\Client;

use App\Models\Ticket;
use Illuminate\Http\Request;
use App\Services\TicketService;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\TicketResource;
use App\Http\Requests\Api\V1\Client\TicketStoreRequest;
use App\Http\Requests\Api\V1\TicketStatusUpdateRequest;
use App\Http\Requests\Api\V1\TicketPriorityUpdateRequest;

class TicketController extends Controller
{
    public function __construct(
        private TicketService $ticketService,
    ) {}

    public function index(Request $request)
    {
        $type = $request->query('type', 'mine');
        $tickets = $this->ticketService->paginateForClient($request->user(), $type);

        return successResponse(TicketResource::collection($tickets));
    }

    public function store(TicketStoreRequest $request)
    {
        $user = $request->user();

        $ticket = $this->ticketService->createForClient(
            $user,
            $request->validated('title'),
            $request->validated('description'),
            $request->ticketSlaPriority(),
        );

        return successResponse(TicketResource::make($ticket));
    }

    public function show(Ticket $ticket)
    {
        return successResponse(TicketResource::make($ticket));
    }

    public function updatePriority(Ticket $ticket, TicketPriorityUpdateRequest $request)
    {
        $this->ticketService->updatePriority($ticket, $request->ticketSlaPriority());

        return successResponse(TicketResource::make($ticket));
    }

    public function updateStatus(Ticket $ticket, TicketStatusUpdateRequest $request)
    {
        $this->ticketService->updateStatus($ticket, $request->ticketStatus());

        return successResponse(TicketResource::make($ticket));
    }
}
