<?php

use App\Models\User;
use App\Models\Agent;
use App\Models\Ticket;
use App\Enums\UserRole;
use App\Enums\TicketStatus;
use App\Models\Organization;
use App\Enums\TicketSlaStatus;
use Illuminate\Support\Carbon;
use App\Services\TicketService;
use App\Utils\SlaTimeGenerator;
use App\Enums\TicketSlaPriority;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Carbon::setTestNow(Carbon::create(2026, 1, 1, 12, 0, 0));

    $this->organization = Organization::factory()->create();
    $this->user = User::factory()->create();
    $this->service = new TicketService;
});

afterEach(function () {
    Carbon::setTestNow();
});

describe('ticket creation sets SLA fields', function () {
    it('sets sla resolution time based on priority', function (TicketSlaPriority $priority, int $expectedSeconds) {
        $this->user->client()->create([
            'organization_id' => $this->organization->id,
            'name'            => 'Test Client',
            'email'           => 'client@test.com',
            'password'        => 'password',
        ]);

        $ticket = $this->service->createForClient(
            $this->user,
            'Test Ticket',
            'Description',
            $priority,
        );

        $ticket->refresh();

        expect($ticket->sla_resolution_time)->toBe($expectedSeconds)
            ->and($ticket->sla_paused_time)->toBe(0)
            ->and($ticket->status)->toBe(TicketStatus::Open)
            ->and($ticket->sla_status)->toBe(TicketSlaStatus::OnTrack);
    })->with([
        'low'    => [TicketSlaPriority::Low, 8 * 3600],
        'medium' => [TicketSlaPriority::Medium, 6 * 3600],
        'high'   => [TicketSlaPriority::High, 4 * 3600],
        'urgent' => [TicketSlaPriority::Urgent, 2 * 3600],
    ]);

    it('sets due_at to now plus resolution time', function () {
        $this->user->client()->create([
            'organization_id' => $this->organization->id,
            'name'            => 'Test Client',
            'email'           => 'client@test.com',
            'password'        => 'password',
        ]);

        $ticket = $this->service->createForClient(
            $this->user,
            'Test Ticket',
            'Description',
            TicketSlaPriority::High,
        );

        $expectedDue = now()->addSeconds(SlaTimeGenerator::generate(TicketSlaPriority::High));

        expect($ticket->due_at->timestamp)->toBe($expectedDue->timestamp);
    });

    it('creates an initial message with the ticket', function () {
        $this->user->client()->create([
            'organization_id' => $this->organization->id,
            'name'            => 'Test Client',
            'email'           => 'client@test.com',
            'password'        => 'password',
        ]);

        $ticket = $this->service->createForClient(
            $this->user,
            'Test Ticket',
            'My description here',
            TicketSlaPriority::Medium,
        );

        expect($ticket->messages)->toHaveCount(1)
            ->and($ticket->messages->first()->content)->toBe('My description here')
            ->and($ticket->messages->first()->is_internal)->toBeFalse();
    });
});

describe('ticket creation assigns an agent', function () {
    beforeEach(function () {
        $this->user->client()->create([
            'organization_id' => $this->organization->id,
            'name'            => 'Test Client',
            'email'           => 'client@test.com',
            'password'        => 'password',
        ]);
    });

    it('leaves assignee null when there are no agents', function () {
        $ticket = $this->service->createForClient(
            $this->user,
            'Test Ticket',
            'Description',
            TicketSlaPriority::Low,
        );

        expect($ticket->assignee_id)->toBeNull();
    });

    it('assigns a free agent when one exists', function () {
        $agentUser = User::factory()->create(['role' => UserRole::Agent]);
        Agent::factory()->create(['user_id' => $agentUser->id]);

        $ticket = $this->service->createForClient(
            $this->user,
            'Test Ticket',
            'Description',
            TicketSlaPriority::Low,
        );

        expect($ticket->assignee_id)->toBe($agentUser->id);
    });

    it('prefers an agent with no active tickets over a busy one', function () {
        $busyUser = User::factory()->create(['role' => UserRole::Agent]);
        Agent::factory()->create(['user_id' => $busyUser->id]);
        $freeUser = User::factory()->create(['role' => UserRole::Agent]);
        Agent::factory()->create(['user_id' => $freeUser->id]);

        Ticket::factory()->create([
            'organization_id' => $this->organization->id,
            'assignee_id'     => $busyUser->id,
            'status'          => TicketStatus::Open,
        ]);

        $ticket = $this->service->createForClient(
            $this->user,
            'Test Ticket',
            'Description',
            TicketSlaPriority::Low,
        );

        expect($ticket->assignee_id)->toBe($freeUser->id);
    });

    it('assigns to the agent with the smallest active load when all are busy', function () {
        $userA = User::factory()->create(['role' => UserRole::Agent]);
        Agent::factory()->create(['user_id' => $userA->id]);
        $userB = User::factory()->create(['role' => UserRole::Agent]);
        Agent::factory()->create(['user_id' => $userB->id]);

        Ticket::factory()->count(2)->create([
            'organization_id' => $this->organization->id,
            'assignee_id'     => $userA->id,
            'status'          => TicketStatus::Open,
        ]);
        Ticket::factory()->create([
            'organization_id' => $this->organization->id,
            'assignee_id'     => $userB->id,
            'status'          => TicketStatus::Open,
        ]);

        $ticket = $this->service->createForClient(
            $this->user,
            'Test Ticket',
            'Description',
            TicketSlaPriority::Low,
        );

        expect($ticket->assignee_id)->toBe($userB->id);
    });

    it('ignores resolved tickets when measuring load', function () {
        $quietUser = User::factory()->create(['role' => UserRole::Agent]);
        Agent::factory()->create(['user_id' => $quietUser->id]);
        $busyUser = User::factory()->create(['role' => UserRole::Agent]);
        Agent::factory()->create(['user_id' => $busyUser->id]);

        Ticket::factory()->resolved()->create([
            'organization_id' => $this->organization->id,
            'assignee_id'     => $quietUser->id,
        ]);
        Ticket::factory()->create([
            'organization_id' => $this->organization->id,
            'assignee_id'     => $busyUser->id,
            'status'          => TicketStatus::Open,
        ]);

        $ticket = $this->service->createForClient(
            $this->user,
            'Test Ticket',
            'Description',
            TicketSlaPriority::Low,
        );

        expect($ticket->assignee_id)->toBe($quietUser->id);
    });

    it('breaks ties between equally loaded agents by lowest user id', function () {
        $first = User::factory()->create(['role' => UserRole::Agent]);
        Agent::factory()->create(['user_id' => $first->id]);
        $second = User::factory()->create(['role' => UserRole::Agent]);
        Agent::factory()->create(['user_id' => $second->id]);

        $ticket = $this->service->createForClient(
            $this->user,
            'Test Ticket',
            'Description',
            TicketSlaPriority::Low,
        );

        expect($ticket->assignee_id)->toBe(min($first->id, $second->id));
    });
});

describe('priority update recalculates SLA', function () {
    it('updates resolution time when priority changes', function () {
        $ticket = Ticket::factory()
            ->priority(TicketSlaPriority::Low)
            ->create(['organization_id' => $this->organization->id]);

        $updated = $this->service->updatePriority($ticket, TicketSlaPriority::Urgent);

        expect($updated->sla_resolution_time)->toBe(SlaTimeGenerator::generate(TicketSlaPriority::Urgent))
            ->and($updated->priority)->toBe(TicketSlaPriority::Urgent);
    });

    it('recalculates due_at when priority changes', function () {
        $ticket = Ticket::factory()
            ->priority(TicketSlaPriority::Low)
            ->create(['organization_id' => $this->organization->id]);

        $originalDueAt = $ticket->due_at->timestamp;

        $this->service->updatePriority($ticket, TicketSlaPriority::Urgent);
        $ticket->refresh();

        expect($ticket->due_at->timestamp)->not->toBe($originalDueAt);
    });

    it('recalculates sla_status when priority changes', function () {
        // Ticket open for 3 hours with low priority (8h SLA) => OnTrack
        $ticket = Ticket::factory()
            ->priority(TicketSlaPriority::Low)
            ->create([
                'organization_id' => $this->organization->id,
                'created_at'      => now()->subSeconds(10800),
            ]);

        // Change to urgent (2h SLA) => 3h consumed out of 2h = Overdue
        $updated = $this->service->updatePriority($ticket, TicketSlaPriority::Urgent);

        expect($updated->sla_status)->toBe(TicketSlaStatus::Overdue);
    });

    it('does not recalculate when priority stays the same', function () {
        $ticket = Ticket::factory()
            ->priority(TicketSlaPriority::High)
            ->create(['organization_id' => $this->organization->id]);

        $originalDueAt = $ticket->due_at->timestamp;

        $this->service->updatePriority($ticket, TicketSlaPriority::High);
        $ticket->refresh();

        expect($ticket->due_at->timestamp)->toBe($originalDueAt);
    });
});

describe('status update affects SLA tracking', function () {
    it('sets last_sla_paused_at when moving to on-hold', function () {
        $ticket = Ticket::factory()
            ->status(TicketStatus::Open)
            ->create(['organization_id' => $this->organization->id]);

        $this->service->updateStatus($ticket, TicketStatus::OnHold);
        $ticket->refresh();

        expect($ticket->status)->toBe(TicketStatus::OnHold)
            ->and($ticket->last_sla_paused_at)->not->toBeNull();
    });

    it('accumulates paused time when resuming from on-hold', function () {
        $ticket = Ticket::factory()->create([
            'organization_id'    => $this->organization->id,
            'status'             => TicketStatus::OnHold,
            'sla_paused_time'    => 300,
            'last_sla_paused_at' => now()->subSeconds(900),
        ]);

        $this->service->updateStatus($ticket, TicketStatus::Open);
        $ticket->refresh();

        // 300 previous + 900 current hold = 1200
        expect($ticket->status)->toBe(TicketStatus::Open)
            ->and($ticket->sla_paused_time)->toBe(1200);
    });

    it('rejects status update on resolved tickets', function () {
        $ticket = Ticket::factory()
            ->resolved()
            ->create(['organization_id' => $this->organization->id]);

        $this->service->updateStatus($ticket, TicketStatus::Open);
    })->throws(ValidationException::class);

    it('rejects status update on archived tickets', function () {
        $ticket = Ticket::factory()
            ->archived()
            ->create(['organization_id' => $this->organization->id]);

        $this->service->updateStatus($ticket, TicketStatus::Open);
    })->throws(ValidationException::class);

    it('allows toggling between open and on-hold', function () {
        $ticket = Ticket::factory()
            ->status(TicketStatus::Open)
            ->create(['organization_id' => $this->organization->id]);

        $this->service->updateStatus($ticket, TicketStatus::OnHold);
        $ticket->refresh();
        expect($ticket->status)->toBe(TicketStatus::OnHold);

        $this->service->updateStatus($ticket, TicketStatus::Open);
        $ticket->refresh();
        expect($ticket->status)->toBe(TicketStatus::Open);
    });

    it('tracks multiple pause/resume cycles correctly', function () {
        $ticket = Ticket::factory()
            ->status(TicketStatus::Open)
            ->create([
                'organization_id' => $this->organization->id,
                'sla_paused_time' => 0,
            ]);

        // First pause: 600 seconds
        $this->service->updateStatus($ticket, TicketStatus::OnHold);
        $ticket->refresh();

        Carbon::setTestNow(now()->addSeconds(600));
        $this->service->updateStatus($ticket, TicketStatus::Open);
        $ticket->refresh();

        expect($ticket->sla_paused_time)->toBe(600);

        // Second pause: 300 seconds
        $this->service->updateStatus($ticket, TicketStatus::OnHold);
        $ticket->refresh();

        Carbon::setTestNow(now()->addSeconds(300));
        $this->service->updateStatus($ticket, TicketStatus::Open);
        $ticket->refresh();

        expect($ticket->sla_paused_time)->toBe(900);
    });
});
