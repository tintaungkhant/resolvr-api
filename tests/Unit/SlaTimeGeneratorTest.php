<?php

use App\Utils\SlaTimeGenerator;
use App\Enums\TicketSlaPriority;

it('generates 8 hours for low priority', function () {
    expect(SlaTimeGenerator::generate(TicketSlaPriority::Low))->toBe(8 * 60 * 60);
});

it('generates 6 hours for medium priority', function () {
    expect(SlaTimeGenerator::generate(TicketSlaPriority::Medium))->toBe(6 * 60 * 60);
});

it('generates 4 hours for high priority', function () {
    expect(SlaTimeGenerator::generate(TicketSlaPriority::High))->toBe(4 * 60 * 60);
});

it('generates 2 hours for urgent priority', function () {
    expect(SlaTimeGenerator::generate(TicketSlaPriority::Urgent))->toBe(2 * 60 * 60);
});

it('generates decreasing times as priority increases', function () {
    $low = SlaTimeGenerator::generate(TicketSlaPriority::Low);
    $medium = SlaTimeGenerator::generate(TicketSlaPriority::Medium);
    $high = SlaTimeGenerator::generate(TicketSlaPriority::High);
    $urgent = SlaTimeGenerator::generate(TicketSlaPriority::Urgent);

    expect($low)->toBeGreaterThan($medium)
        ->and($medium)->toBeGreaterThan($high)
        ->and($high)->toBeGreaterThan($urgent);
});
