<?php

namespace App\Http\Controllers\Api\V1\Agent;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Agent\TicketMessageStoreRequest;
use App\Http\Resources\Api\V1\TicketMessageResource;
use App\Models\Ticket;
use App\Services\TicketMessageService;

class TicketMessageController extends Controller
{
    public function __construct(
        private TicketMessageService $ticketMessageService,
    ) {}

    public function index(Ticket $ticket)
    {
        $messages = $this->ticketMessageService->paginateForAgent($ticket);

        return successResponse(TicketMessageResource::collection($messages));
    }

    public function store(Ticket $ticket, TicketMessageStoreRequest $request)
    {
        $message = $this->ticketMessageService->storeForAgent(
            $ticket,
            $request->user(),
            $request->validated('content'),
            $request->boolean('is_internal', true),
        );

        return successResponse(TicketMessageResource::make($message));
    }
}
