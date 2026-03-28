<?php

use App\Models\User;
use App\Models\Agent;
use App\Models\Ticket;
use App\Enums\UserRole;
use App\Models\Organization;
use Laravel\Sanctum\Sanctum;
use App\Models\TicketMessage;

beforeEach(function () {
    $this->organization = Organization::factory()->create();

    $this->agent = User::factory()->create(['role' => UserRole::Agent]);
    Agent::factory()->create(['user_id' => $this->agent->id]);

    $this->otherAgent = User::factory()->create(['role' => UserRole::Agent]);
    Agent::factory()->create(['user_id' => $this->otherAgent->id]);

    $this->ticketAssignedToMe = Ticket::factory()->create([
        'organization_id' => $this->organization->id,
        'assignee_id'     => $this->agent->id,
    ]);

    $this->ticketAssignedToPeer = Ticket::factory()->create([
        'organization_id' => $this->organization->id,
        'assignee_id'     => $this->otherAgent->id,
    ]);
});

it('returns ticket details with SLA fields when viewing a ticket', function () {
    Sanctum::actingAs($this->agent, ['role:agent']);

    $this->getJson("/api/v1/agent/tickets/{$this->ticketAssignedToMe->id}")
        ->assertSuccessful()
        ->assertJsonPath('data.id', $this->ticketAssignedToMe->id)
        ->assertJsonStructure([
            'data' => ['id', 'title', 'status', 'priority', 'sla_status', 'due_at', 'assignee_id'],
        ]);
});

it('allows an agent to view a ticket assigned to another agent', function () {
    Sanctum::actingAs($this->agent, ['role:agent']);

    $this->getJson("/api/v1/agent/tickets/{$this->ticketAssignedToPeer->id}")
        ->assertSuccessful()
        ->assertJsonPath('data.id', $this->ticketAssignedToPeer->id);
});

it('forbids updates to a ticket not assigned to the agent', function () {
    Sanctum::actingAs($this->agent, ['role:agent']);

    $this->patchJson("/api/v1/agent/tickets/{$this->ticketAssignedToPeer->id}/priority", [
        'priority' => 'urgent',
    ])->assertForbidden();
});

it('includes internal notes when an agent fetches messages', function () {
    Sanctum::actingAs($this->agent, ['role:agent']);

    TicketMessage::factory()->create([
        'ticket_id'   => $this->ticketAssignedToMe->id,
        'user_id'     => $this->agent->id,
        'content'     => 'Public reply',
        'is_internal' => false,
    ]);

    TicketMessage::factory()->create([
        'ticket_id'   => $this->ticketAssignedToMe->id,
        'user_id'     => $this->agent->id,
        'content'     => 'Secret internal note',
        'is_internal' => true,
    ]);

    $this->getJson("/api/v1/agent/tickets/{$this->ticketAssignedToMe->id}/messages")
        ->assertSuccessful()
        ->assertJsonCount(2, 'data');
});
