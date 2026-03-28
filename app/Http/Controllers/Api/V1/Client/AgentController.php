<?php

namespace App\Http\Controllers\Api\V1\Client;

use App\Models\Agent;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;

class AgentController extends Controller
{
    public function index(): JsonResponse
    {
        $agents = Agent::with('user')
            ->orderBy('name')
            ->get()
            ->map(fn (Agent $agent) => [
                'user_id' => $agent->user_id,
                'name'    => $agent->name,
            ]);

        return successResponse($agents);
    }
}
