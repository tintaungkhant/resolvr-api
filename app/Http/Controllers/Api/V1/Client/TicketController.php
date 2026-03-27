<?php

namespace App\Http\Controllers\Api\V1\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Client\TicketStoreRequest;
use App\Http\Resources\Api\V1\TicketResource;
use App\Models\Ticket;
use App\Utils\SlaTimeGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TicketController extends Controller
{
    public function index(Request $request)
    {
        $type = $request->query('type', 'mine');

        $user = $request->user();

        if ($type === 'mine') {
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

        $ticket = DB::transaction(function () use ($user, $request, $slaResolutionTime) {
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
        });

        return successResponse(TicketResource::make($ticket));
    }

    public function show(Ticket $ticket)
    {
        return successResponse(TicketResource::make($ticket));
    }

    public function updatePriority(Ticket $ticket, TicketPriorityUpdateRequest $request)
    {
        $lastPriority = $ticket->priority;

        $ticket->update([
            'priority' => $request->ticketSlaPriority(),
        ]);

        if ($lastPriority !== $ticket->priority) {
            $ticket->update([
                'due_at' => SlaTimeCalculator::calcDueAt($ticket),
                'sla_status' => SlaTimeCalculator::calcSlaStatus($ticket),
            ]);
        }

        return successResponse(TicketResource::make($ticket));
    }

    public function updateStatus(Ticket $ticket, TicketStatusUpdateRequest $request)
    {
        if(!in_array($ticket->status, [TicketStatus::Open, TicketStatus::OnHold])){
            throw ValidationException::withMessages([
                'status' => ['Only open or on-hold tickets can be updated'],
            ]);
        }

        $lastStatus = $ticket->status;

        $ticket->update([
            'status' => $request->ticketStatus(),
        ]);

        if($lastStatus === TicketStatus::Open && $ticket->status === TicketStatus::OnHold){
            $ticket->update([
                'last_sla_paused_at' => now(),
            ]);
        }

        if($lastStatus === TicketStatus::OnHold && $ticket->status === TicketStatus::Open){
            $ticket->update([
                'sla_paused_time' => $ticket->sla_paused_time + $ticket->last_sla_paused_at->diffInSeconds(now()),
            ]);
        }

        return successResponse(TicketResource::make($ticket));
    }
}
