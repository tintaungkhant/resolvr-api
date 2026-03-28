<?php

use App\Models\User;
use App\Models\Agent;
use App\Models\Client;
use App\Models\Ticket;
use App\Enums\UserRole;
use App\Models\Organization;
use Laravel\Sanctum\Sanctum;
use App\Models\TicketMessage;

beforeEach(function () {
    $this->orgA = Organization::factory()->create();
    $this->orgB = Organization::factory()->create();

    $this->issuer = User::factory()->create(['role' => UserRole::Client]);
    Client::factory()->create([
        'user_id'         => $this->issuer->id,
        'organization_id' => $this->orgA->id,
    ]);

    $this->outsider = User::factory()->create(['role' => UserRole::Client]);
    Client::factory()->create([
        'user_id'         => $this->outsider->id,
        'organization_id' => $this->orgB->id,
    ]);

    $this->agent = User::factory()->create(['role' => UserRole::Agent]);
    Agent::factory()->create(['user_id' => $this->agent->id]);

    $this->ticket = Ticket::factory()->create([
        'organization_id' => $this->orgA->id,
        'issuer_id'       => $this->issuer->id,
        'assignee_id'     => $this->agent->id,
    ]);
});

it('forbids a client from viewing a ticket in another organization', function () {
    Sanctum::actingAs($this->outsider, ['role:client']);

    $this->getJson("/api/v1/client/tickets/{$this->ticket->id}")
        ->assertForbidden();
});

it('returns ticket details for a client in the same organization', function () {
    Sanctum::actingAs($this->issuer, ['role:client']);

    $this->getJson("/api/v1/client/tickets/{$this->ticket->id}")
        ->assertSuccessful()
        ->assertJsonPath('data.id', $this->ticket->id)
        ->assertJsonStructure([
            'data' => ['id', 'title', 'status', 'priority', 'sla_status', 'due_at'],
        ]);
});

it('does not expose a status update endpoint for clients', function () {
    Sanctum::actingAs($this->issuer, ['role:client']);

    $this->patchJson("/api/v1/client/tickets/{$this->ticket->id}/status", [
        'status' => 'on-hold',
    ])->assertNotFound();
});

it('never exposes internal notes to a client', function () {
    Sanctum::actingAs($this->issuer, ['role:client']);

    TicketMessage::factory()->create([
        'ticket_id'   => $this->ticket->id,
        'user_id'     => $this->agent->id,
        'content'     => 'Visible reply',
        'is_internal' => false,
    ]);

    TicketMessage::factory()->create([
        'ticket_id'   => $this->ticket->id,
        'user_id'     => $this->agent->id,
        'content'     => 'This is a secret internal note',
        'is_internal' => true,
    ]);

    TicketMessage::factory()->create([
        'ticket_id'   => $this->ticket->id,
        'user_id'     => $this->agent->id,
        'content'     => 'Another internal note',
        'is_internal' => true,
    ]);

    $response = $this->getJson("/api/v1/client/tickets/{$this->ticket->id}/messages")
        ->assertSuccessful()
        ->assertJsonCount(1, 'data');

    $messages = $response->json('data');
    expect($messages[0]['content'])->toBe('Visible reply');

    foreach ($messages as $message) {
        expect($message['is_internal'])->toBeFalse();
    }
});
