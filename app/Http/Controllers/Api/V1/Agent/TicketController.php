<?php

namespace App\Http\Controllers\Api\V1\Agent;

use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\TicketPriorityUpdateRequest;
use App\Http\Requests\Api\V1\TicketStatusUpdateRequest;
use App\Http\Resources\Api\V1\TicketResource;
use App\Models\Ticket;
use App\Utils\SlaTimeCalculator;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TicketController extends Controller
{
    public function index(Request $request)
    {
        $type = $request->query('type', 'mine');

        $user = $request->user();

        if ($type === 'mine') {
            $tickets = Ticket::where('assignee_id', $user->agent->id)->latest('id')->paginate();
        } else {
            $tickets = Ticket::latest('id')->paginate();
        }

        return successResponse(TicketResource::collection($tickets));
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
