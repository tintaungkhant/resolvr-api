<?php

namespace App\Http\Controllers\Api\V1\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Client\TicketMessageStoreRequest;
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
        $messages = $this->ticketMessageService->paginateForClient($ticket);

        return successResponse(TicketMessageResource::collection($messages));
    }

    public function store(Ticket $ticket, TicketMessageStoreRequest $request)
    {
        $message = $this->ticketMessageService->storeForClient(
            $ticket,
            $request->user(),
            $request->validated('content'),
        );

        return successResponse(TicketMessageResource::make($message));
    }
}
