<?php

namespace App\Http\Controllers\Api\V1\Agent;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\TicketResource;
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
}
