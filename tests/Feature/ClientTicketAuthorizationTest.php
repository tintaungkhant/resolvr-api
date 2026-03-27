<?php

use App\Models\User;
use App\Models\Client;
use App\Models\Ticket;
use App\Enums\UserRole;
use App\Enums\TicketStatus;
use App\Models\Organization;
use Laravel\Sanctum\Sanctum;
use App\Enums\TicketSlaPriority;

beforeEach(function () {
    $this->orgA = Organization::factory()->create();
    $this->orgB = Organization::factory()->create();

    $this->issuer = User::factory()->create(['role' => UserRole::Client]);
    Client::factory()->create([
        'user_id'         => $this->issuer->id,
        'organization_id' => $this->orgA->id,
    ]);

    $this->colleague = User::factory()->create(['role' => UserRole::Client]);
    Client::factory()->create([
        'user_id'         => $this->colleague->id,
        'organization_id' => $this->orgA->id,
    ]);

    $this->outsider = User::factory()->create(['role' => UserRole::Client]);
    Client::factory()->create([
        'user_id'         => $this->outsider->id,
        'organization_id' => $this->orgB->id,
    ]);

    $this->ticket = Ticket::factory()->create([
        'organization_id' => $this->orgA->id,
        'issuer_id'       => $this->issuer->id,
    ]);
});

it('forbids viewing a ticket from another organization', function () {
    Sanctum::actingAs($this->outsider, ['role:client']);

    $this->getJson("/api/v1/client/tickets/{$this->ticket->id}")
        ->assertForbidden();
});

it('allows viewing a ticket from the same organization when not the issuer', function () {
    Sanctum::actingAs($this->colleague, ['role:client']);

    $this->getJson("/api/v1/client/tickets/{$this->ticket->id}")
        ->assertSuccessful();
});

it('forbids priority updates from a non-issuer in the same organization', function () {
    Sanctum::actingAs($this->colleague, ['role:client']);

    $this->patchJson("/api/v1/client/tickets/{$this->ticket->id}/priority", [
        'priority' => TicketSlaPriority::Urgent->value,
    ])->assertForbidden();
});

it('allows priority updates from the issuer', function () {
    Sanctum::actingAs($this->issuer, ['role:client']);

    $this->patchJson("/api/v1/client/tickets/{$this->ticket->id}/priority", [
        'priority' => TicketSlaPriority::Urgent->value,
    ])->assertSuccessful();
});

it('forbids status updates from a non-issuer in the same organization', function () {
    Sanctum::actingAs($this->colleague, ['role:client']);

    $this->patchJson("/api/v1/client/tickets/{$this->ticket->id}/status", [
        'status' => TicketStatus::OnHold->value,
    ])->assertForbidden();
});

it('allows status updates from the issuer', function () {
    Sanctum::actingAs($this->issuer, ['role:client']);

    $this->patchJson("/api/v1/client/tickets/{$this->ticket->id}/status", [
        'status' => TicketStatus::OnHold->value,
    ])->assertSuccessful();
});

it('forbids listing messages for a ticket from another organization', function () {
    Sanctum::actingAs($this->outsider, ['role:client']);

    $this->getJson("/api/v1/client/tickets/{$this->ticket->id}/messages")
        ->assertForbidden();
});

it('allows listing messages for a ticket in the same organization', function () {
    Sanctum::actingAs($this->colleague, ['role:client']);

    $this->getJson("/api/v1/client/tickets/{$this->ticket->id}/messages")
        ->assertSuccessful();
});

it('forbids creating a message when not the issuer', function () {
    Sanctum::actingAs($this->colleague, ['role:client']);

    $this->postJson("/api/v1/client/tickets/{$this->ticket->id}/messages", [
        'content' => 'Hello from colleague',
    ])->assertForbidden();
});

it('allows creating a message when the issuer', function () {
    Sanctum::actingAs($this->issuer, ['role:client']);

    $this->postJson("/api/v1/client/tickets/{$this->ticket->id}/messages", [
        'content' => 'Hello from issuer',
    ])->assertSuccessful();
});
