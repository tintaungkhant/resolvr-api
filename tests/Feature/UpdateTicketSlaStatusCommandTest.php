<?php

use App\Models\Ticket;
use App\Enums\TicketStatus;
use App\Enums\TicketSlaStatus;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow(Carbon::create(2026, 6, 1, 12, 0, 0));
});

afterEach(function () {
    Carbon::setTestNow();
});

it('transitions an open ticket from on-track to due-soon', function () {
    $ticket = Ticket::factory()->create([
        'status'              => TicketStatus::Open,
        'sla_status'          => TicketSlaStatus::OnTrack,
        'sla_resolution_time' => 3600,
        'sla_paused_time'     => 0,
        'created_at'          => now()->subSeconds(2900), // ~81% consumed
    ]);

    $this->artisan('app:update-ticket-sla-status')->assertSuccessful();

    expect($ticket->fresh()->sla_status)->toBe(TicketSlaStatus::DueSoon);
});

it('transitions an open ticket from due-soon to overdue and sets overdue_at', function () {
    $ticket = Ticket::factory()->create([
        'status'              => TicketStatus::Open,
        'sla_status'          => TicketSlaStatus::DueSoon,
        'sla_resolution_time' => 3600,
        'sla_paused_time'     => 0,
        'overdue_at'          => null,
        'created_at'          => now()->subSeconds(3700), // past deadline
    ]);

    $this->artisan('app:update-ticket-sla-status')->assertSuccessful();

    $ticket->refresh();
    expect($ticket->sla_status)->toBe(TicketSlaStatus::Overdue)
        ->and($ticket->overdue_at)->not->toBeNull();
});

it('does not update on-hold tickets', function () {
    $ticket = Ticket::factory()->create([
        'status'              => TicketStatus::OnHold,
        'sla_status'          => TicketSlaStatus::OnTrack,
        'sla_resolution_time' => 3600,
        'sla_paused_time'     => 0,
        'created_at'          => now()->subSeconds(7200), // would be overdue if open
    ]);

    $this->artisan('app:update-ticket-sla-status')->assertSuccessful();

    expect($ticket->fresh()->sla_status)->toBe(TicketSlaStatus::OnTrack);
});

it('does not update resolved or archived tickets', function () {
    $resolved = Ticket::factory()->create([
        'status'              => TicketStatus::Resolved,
        'sla_status'          => TicketSlaStatus::OnTrack,
        'sla_resolution_time' => 3600,
        'sla_paused_time'     => 0,
        'created_at'          => now()->subSeconds(7200),
    ]);

    $archived = Ticket::factory()->create([
        'status'              => TicketStatus::Archived,
        'sla_status'          => TicketSlaStatus::OnTrack,
        'sla_resolution_time' => 3600,
        'sla_paused_time'     => 0,
        'created_at'          => now()->subSeconds(7200),
    ]);

    $this->artisan('app:update-ticket-sla-status')->assertSuccessful();

    expect($resolved->fresh()->sla_status)->toBe(TicketSlaStatus::OnTrack)
        ->and($archived->fresh()->sla_status)->toBe(TicketSlaStatus::OnTrack);
});

it('skips tickets whose sla status has not changed', function () {
    $ticket = Ticket::factory()->create([
        'status'              => TicketStatus::Open,
        'sla_status'          => TicketSlaStatus::OnTrack,
        'sla_resolution_time' => 3600,
        'sla_paused_time'     => 0,
        'created_at'          => now()->subSeconds(600), // 17% — still on-track
    ]);

    $this->artisan('app:update-ticket-sla-status')
        ->assertSuccessful()
        ->expectsOutputToContain('Updated 0 tickets');

    expect($ticket->fresh()->sla_status)->toBe(TicketSlaStatus::OnTrack);
});
