<?php

namespace App\Http\Controllers\Api\V1\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Client\TicketMessageStoreRequest;
use App\Http\Requests\Api\V1\Client\TicketStoreRequest;
use App\Http\Resources\Api\V1\TicketMessageResource;
use App\Http\Resources\Api\V1\TicketResource;
use App\Models\Ticket;
use App\Utils\SlaTimeGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TicketMessageController extends Controller
{
    public function index(Ticket $ticket)
    {
        $messages = $ticket->messages()->with(['user' => function ($q) {
            $q->with(['agent', 'client']);
        }])
            ->where('is_internal', false)
            ->latest('id')->paginate();

        return successResponse(TicketMessageResource::collection($messages));
    }

    public function store(Ticket $ticket, TicketMessageStoreRequest $request)
    {
        $user = $request->user();

        $message = $ticket->messages()->create([
            'user_id' => $user->id,
            'content' => $request->validated('content'),
            'is_internal' => false,
        ]);

        $message->load('user');

        return successResponse(TicketMessageResource::make($message));
    }
}
