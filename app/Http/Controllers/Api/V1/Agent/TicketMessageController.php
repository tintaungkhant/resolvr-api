<?php

namespace App\Http\Controllers\Api\V1\Agent;

use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Services\TicketMessageService;
use App\Http\Resources\Api\V1\TicketMessageResource;
use App\Http\Requests\Api\V1\Agent\TicketMessageStoreRequest;

class TicketMessageController extends Controller
{
    public function __construct(
        private TicketMessageService $ticketMessageService,
    ) {}

    public function index(Ticket $ticket): JsonResponse
    {
        $messages = $this->ticketMessageService->paginateForAgent($ticket);

        return successResponse(TicketMessageResource::collection($messages));
    }

    public function store(Ticket $ticket, TicketMessageStoreRequest $request): JsonResponse
    {
        $user = $request->user();

        $message = $this->ticketMessageService->storeForAgent(
            $ticket,
            $user,
            $request->validated('content'),
            $request->boolean('is_internal', true),
        );

        return successResponse(TicketMessageResource::make($message));
    }
}
