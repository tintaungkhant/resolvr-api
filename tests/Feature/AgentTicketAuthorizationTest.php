<?php

use App\Models\User;
use App\Models\Agent;
use App\Models\Ticket;
use App\Enums\UserRole;
use App\Enums\TicketStatus;
use App\Models\Organization;
use Laravel\Sanctum\Sanctum;
use App\Enums\TicketSlaPriority;

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

    $this->ticketUnassigned = Ticket::factory()->create([
        'organization_id' => $this->organization->id,
        'assignee_id'     => null,
    ]);
});

it('allows an agent to view any ticket', function () {
    Sanctum::actingAs($this->agent, ['role:agent']);

    $this->getJson("/api/v1/agent/tickets/{$this->ticketAssignedToPeer->id}")
        ->assertSuccessful();
});

it('allows an agent to list messages on any ticket', function () {
    Sanctum::actingAs($this->agent, ['role:agent']);

    $this->getJson("/api/v1/agent/tickets/{$this->ticketAssignedToPeer->id}/messages")
        ->assertSuccessful();
});

it('forbids priority updates when not the assignee', function () {
    Sanctum::actingAs($this->agent, ['role:agent']);

    $this->patchJson("/api/v1/agent/tickets/{$this->ticketAssignedToPeer->id}/priority", [
        'priority' => TicketSlaPriority::Urgent->value,
    ])->assertForbidden();
});

it('forbids priority updates when the ticket has no assignee', function () {
    Sanctum::actingAs($this->agent, ['role:agent']);

    $this->patchJson("/api/v1/agent/tickets/{$this->ticketUnassigned->id}/priority", [
        'priority' => TicketSlaPriority::Urgent->value,
    ])->assertForbidden();
});

it('allows priority updates when assigned to the agent', function () {
    Sanctum::actingAs($this->agent, ['role:agent']);

    $this->patchJson("/api/v1/agent/tickets/{$this->ticketAssignedToMe->id}/priority", [
        'priority' => TicketSlaPriority::Urgent->value,
    ])->assertSuccessful();
});

it('forbids status updates when not the assignee', function () {
    Sanctum::actingAs($this->agent, ['role:agent']);

    $this->patchJson("/api/v1/agent/tickets/{$this->ticketAssignedToPeer->id}/status", [
        'status' => TicketStatus::OnHold->value,
    ])->assertForbidden();
});

it('allows status updates when assigned to the agent', function () {
    Sanctum::actingAs($this->agent, ['role:agent']);

    $this->patchJson("/api/v1/agent/tickets/{$this->ticketAssignedToMe->id}/status", [
        'status' => TicketStatus::OnHold->value,
    ])->assertSuccessful();
});

it('forbids creating a message when not the assignee', function () {
    Sanctum::actingAs($this->agent, ['role:agent']);

    $this->postJson("/api/v1/agent/tickets/{$this->ticketAssignedToPeer->id}/messages", [
        'content'     => 'Note',
        'is_internal' => true,
    ])->assertForbidden();
});

it('allows creating a message when assigned to the agent', function () {
    Sanctum::actingAs($this->agent, ['role:agent']);

    $this->postJson("/api/v1/agent/tickets/{$this->ticketAssignedToMe->id}/messages", [
        'content'     => 'Reply',
        'is_internal' => true,
    ])->assertSuccessful();
});
