<?php

use App\Models\User;
use App\Models\Agent;
use App\Models\Client;
use App\Models\Ticket;
use App\Enums\UserRole;
use App\Models\Organization;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->org = Organization::factory()->create();

    $this->agentUser = User::factory()->create(['role' => UserRole::Agent]);
    Agent::factory()->create(['user_id' => $this->agentUser->id]);

    $this->clientUser = User::factory()->create(['role' => UserRole::Client]);
    Client::factory()->create([
        'user_id'         => $this->clientUser->id,
        'organization_id' => $this->org->id,
    ]);

    $this->ticket = Ticket::factory()->create([
        'organization_id' => $this->org->id,
        'issuer_id'       => $this->clientUser->id,
        'assignee_id'     => $this->agentUser->id,
    ]);
});

it('blocks a client token from accessing agent endpoints', function () {
    Sanctum::actingAs($this->clientUser, ['role:client']);

    $this->getJson('/api/v1/agent/tickets')->assertForbidden();
    $this->getJson("/api/v1/agent/tickets/{$this->ticket->id}")->assertForbidden();
    $this->getJson('/api/v1/agent/profile')->assertForbidden();
});

it('blocks an agent token from accessing client endpoints', function () {
    Sanctum::actingAs($this->agentUser, ['role:agent']);

    $this->getJson('/api/v1/client/tickets')->assertForbidden();
    $this->getJson("/api/v1/client/tickets/{$this->ticket->id}")->assertForbidden();
    $this->getJson('/api/v1/client/profile')->assertForbidden();
});

it('blocks unauthenticated access to both panels', function () {
    $this->getJson('/api/v1/agent/tickets')->assertUnauthorized();
    $this->getJson('/api/v1/client/tickets')->assertUnauthorized();
});
