<?php

namespace App\Http\Controllers\Api\V1\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Client\TicketStoreRequest;
use App\Http\Resources\Api\V1\Client\TicketResource;
use App\Models\Ticket;
use App\Utils\SlaTimeGenerator;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    public function index(Request $request)
    {
        $type = $request->query('type', 'mine');

        $user = $request->user();

        if($type === 'mine') {
            $tickets = Ticket::where('issuer_id', $user->id)->latest('id')->paginate();
        } else {
            $tickets = Ticket::where('organization_id', $user->client->organization_id)->latest('id')->paginate();
        }

        return successResponse(TicketResource::collection($tickets));
    }
    
    public function store(TicketStoreRequest $request)
    {
        $user = $request->user();

        $slaResolutionTime = SlaTimeGenerator::generate($request->ticketSlaPriority());

        $ticket = Ticket::create([
            'organization_id' => $user->client->organization_id,
            'issuer_id' => $user->id,
            'title' => $request->validated('title'),
            'description' => $request->validated('description'),
            'priority' => $request->validated('priority'),
            'started_at' => now(),
            'sla_resolution_time' => $slaResolutionTime,
            'due_at' => now()->addSeconds($slaResolutionTime),
        ]);

        $ticket->messages()->create([
            'user_id' => $user->id,
            'content' => $request->validated('description'),
            'is_internal' => false,
        ]);

        return successResponse(TicketResource::make($ticket));
    }
}
