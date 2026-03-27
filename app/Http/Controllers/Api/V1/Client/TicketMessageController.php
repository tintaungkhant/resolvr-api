<?php

namespace App\Http\Controllers\Api\V1\Client;

use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Services\TicketMessageService;
use App\Http\Resources\Api\V1\TicketMessageResource;
use App\Policies\Client\TicketPolicy as ClientTicketPolicy;
use App\Http\Requests\Api\V1\Client\TicketMessageStoreRequest;

class TicketMessageController extends Controller
{
    public function __construct(
        private TicketMessageService $ticketMessageService,
    ) {}

    public function index(Ticket $ticket): JsonResponse
    {
        $this->authorizePolicy(ClientTicketPolicy::class, 'view', $ticket);

        $messages = $this->ticketMessageService->paginateForClient($ticket);

        return successResponse(TicketMessageResource::collection($messages));
    }

    public function store(Ticket $ticket, TicketMessageStoreRequest $request): JsonResponse
    {
        $this->authorizePolicy(ClientTicketPolicy::class, 'update', $ticket);

        $user = $request->user();

        $message = $this->ticketMessageService->storeForClient(
            $ticket,
            $user,
            $request->validated('content'),
        );

        return successResponse(TicketMessageResource::make($message));
    }
}
