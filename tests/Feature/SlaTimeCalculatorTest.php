<?php

use App\Models\Ticket;
use App\Enums\TicketStatus;
use App\Enums\TicketSlaStatus;
use Illuminate\Support\Carbon;
use App\Utils\SlaTimeCalculator;

beforeEach(function () {
    Carbon::setTestNow(Carbon::create(2026, 1, 1, 12, 0, 0));
});

afterEach(function () {
    Carbon::setTestNow();
});

describe('calcConsumedTime', function () {
    it('calculates consumed time for an open ticket', function () {
        $ticket = new Ticket([
            'status'              => TicketStatus::Open,
            'sla_paused_time'     => 0,
            'sla_resolution_time' => 3600,
        ]);
        $ticket->created_at = now()->subSeconds(1800);

        expect(SlaTimeCalculator::calcConsumedTime($ticket))->toBe(1800);
    });

    it('subtracts paused time from consumed time', function () {
        $ticket = new Ticket([
            'status'              => TicketStatus::Open,
            'sla_paused_time'     => 600,
            'sla_resolution_time' => 3600,
        ]);
        $ticket->created_at = now()->subSeconds(1800);

        expect(SlaTimeCalculator::calcConsumedTime($ticket))->toBe(1200);
    });

    it('includes current hold duration for on-hold tickets', function () {
        $ticket = new Ticket([
            'status'              => TicketStatus::OnHold,
            'sla_paused_time'     => 0,
            'last_sla_paused_at'  => now()->subSeconds(600),
            'sla_resolution_time' => 3600,
        ]);
        $ticket->created_at = now()->subSeconds(1800);

        // 1800s total - 600s currently paused = 1200s consumed
        expect(SlaTimeCalculator::calcConsumedTime($ticket))->toBe(1200);
    });

    it('combines previous paused time with current hold duration', function () {
        $ticket = new Ticket([
            'status'              => TicketStatus::OnHold,
            'sla_paused_time'     => 300,
            'last_sla_paused_at'  => now()->subSeconds(300),
            'sla_resolution_time' => 3600,
        ]);
        $ticket->created_at = now()->subSeconds(1800);

        // 1800s total - 300s previous - 300s current = 1200s consumed
        expect(SlaTimeCalculator::calcConsumedTime($ticket))->toBe(1200);
    });

    it('never returns negative consumed time', function () {
        $ticket = new Ticket([
            'status'              => TicketStatus::Open,
            'sla_paused_time'     => 99999,
            'sla_resolution_time' => 3600,
        ]);
        $ticket->created_at = now()->subSeconds(60);

        expect(SlaTimeCalculator::calcConsumedTime($ticket))->toBe(0);
    });
});

describe('calcSlaPercentage', function () {
    it('returns 0 when resolution time is zero', function () {
        $ticket = new Ticket([
            'status'              => TicketStatus::Open,
            'sla_paused_time'     => 0,
            'sla_resolution_time' => 0,
        ]);
        $ticket->created_at = now();

        expect(SlaTimeCalculator::calcSlaPercentage($ticket))->toBe(0);
    });

    it('calculates 50% when half the resolution time is consumed', function () {
        $ticket = new Ticket([
            'status'              => TicketStatus::Open,
            'sla_paused_time'     => 0,
            'sla_resolution_time' => 3600,
        ]);
        $ticket->created_at = now()->subSeconds(1800);

        expect(SlaTimeCalculator::calcSlaPercentage($ticket))->toBe(50);
    });

    it('calculates 100% when full resolution time is consumed', function () {
        $ticket = new Ticket([
            'status'              => TicketStatus::Open,
            'sla_paused_time'     => 0,
            'sla_resolution_time' => 3600,
        ]);
        $ticket->created_at = now()->subSeconds(3600);

        expect(SlaTimeCalculator::calcSlaPercentage($ticket))->toBe(100);
    });

    it('exceeds 100% when overdue', function () {
        $ticket = new Ticket([
            'status'              => TicketStatus::Open,
            'sla_paused_time'     => 0,
            'sla_resolution_time' => 3600,
        ]);
        $ticket->created_at = now()->subSeconds(7200);

        expect(SlaTimeCalculator::calcSlaPercentage($ticket))->toBe(200);
    });
});

describe('calcSlaStatus', function () {
    it('returns OnTrack when under 80%', function () {
        $ticket = new Ticket([
            'status'              => TicketStatus::Open,
            'sla_paused_time'     => 0,
            'sla_resolution_time' => 3600,
        ]);
        $ticket->created_at = now()->subSeconds(600);

        expect(SlaTimeCalculator::calcSlaStatus($ticket))->toBe(TicketSlaStatus::OnTrack);
    });

    it('returns DueSoon when between 80% and 100%', function () {
        $ticket = new Ticket([
            'status'              => TicketStatus::Open,
            'sla_paused_time'     => 0,
            'sla_resolution_time' => 3600,
        ]);
        $ticket->created_at = now()->subSeconds(3000);

        expect(SlaTimeCalculator::calcSlaStatus($ticket))->toBe(TicketSlaStatus::DueSoon);
    });

    it('returns Overdue when at 100%', function () {
        $ticket = new Ticket([
            'status'              => TicketStatus::Open,
            'sla_paused_time'     => 0,
            'sla_resolution_time' => 3600,
        ]);
        $ticket->created_at = now()->subSeconds(3600);

        expect(SlaTimeCalculator::calcSlaStatus($ticket))->toBe(TicketSlaStatus::Overdue);
    });

    it('returns Overdue when beyond 100%', function () {
        $ticket = new Ticket([
            'status'              => TicketStatus::Open,
            'sla_paused_time'     => 0,
            'sla_resolution_time' => 3600,
        ]);
        $ticket->created_at = now()->subSeconds(7200);

        expect(SlaTimeCalculator::calcSlaStatus($ticket))->toBe(TicketSlaStatus::Overdue);
    });

    it('accounts for paused time in status calculation', function () {
        // 3000s elapsed, 2400s paused => 600s consumed out of 3600s = ~17%
        $ticket = new Ticket([
            'status'              => TicketStatus::Open,
            'sla_paused_time'     => 2400,
            'sla_resolution_time' => 3600,
        ]);
        $ticket->created_at = now()->subSeconds(3000);

        expect(SlaTimeCalculator::calcSlaStatus($ticket))->toBe(TicketSlaStatus::OnTrack);
    });
});

describe('calcDueAt', function () {
    it('calculates due at based on created_at and resolution time', function () {
        $ticket = new Ticket([
            'status'              => TicketStatus::Open,
            'sla_paused_time'     => 0,
            'sla_resolution_time' => 3600,
        ]);
        $ticket->created_at = now()->subSeconds(600);

        $dueAt = SlaTimeCalculator::calcDueAt($ticket);

        expect($dueAt->equalTo($ticket->created_at->copy()->addSeconds(3600)))->toBeTrue();
    });

    it('extends due at by paused time', function () {
        $ticket = new Ticket([
            'status'              => TicketStatus::Open,
            'sla_paused_time'     => 600,
            'sla_resolution_time' => 3600,
        ]);
        $ticket->created_at = now()->subSeconds(1800);

        $dueAt = SlaTimeCalculator::calcDueAt($ticket);

        expect($dueAt->equalTo($ticket->created_at->copy()->addSeconds(3600 + 600)))->toBeTrue();
    });

    it('extends due at by current hold duration for on-hold tickets', function () {
        $ticket = new Ticket([
            'status'              => TicketStatus::OnHold,
            'sla_paused_time'     => 0,
            'sla_resolution_time' => 3600,
            'last_sla_paused_at'  => now()->subSeconds(600),
        ]);
        $ticket->created_at = now()->subSeconds(1800);

        $dueAt = SlaTimeCalculator::calcDueAt($ticket);

        // due = created_at + resolution_time + current_hold_duration
        expect($dueAt->equalTo($ticket->created_at->copy()->addSeconds(3600 + 600)))->toBeTrue();
    });
});
