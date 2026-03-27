<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Ticket;
use App\Enums\TicketStatus;
use App\Models\Organization;
use App\Enums\TicketSlaStatus;
use App\Utils\SlaTimeGenerator;
use App\Enums\TicketSlaPriority;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $priority = fake()->randomElement(TicketSlaPriority::cases());
        $slaResolutionTime = SlaTimeGenerator::generate($priority);

        return [
            'organization_id'     => Organization::factory(),
            'issuer_id'           => User::factory(),
            'title'               => fake()->sentence(),
            'description'         => fake()->paragraph(),
            'priority'            => $priority,
            'status'              => TicketStatus::Open,
            'sla_status'          => TicketSlaStatus::OnTrack,
            'sla_resolution_time' => $slaResolutionTime,
            'sla_paused_time'     => 0,
            'started_at'          => now(),
            'due_at'              => now()->addSeconds($slaResolutionTime),
        ];
    }

    public function priority(TicketSlaPriority $priority): static
    {
        $slaResolutionTime = SlaTimeGenerator::generate($priority);

        return $this->state(fn () => [
            'priority'            => $priority,
            'sla_resolution_time' => $slaResolutionTime,
            'due_at'              => now()->addSeconds($slaResolutionTime),
        ]);
    }

    public function status(TicketStatus $status): static
    {
        return $this->state(fn () => [
            'status' => $status,
        ]);
    }

    public function onHold(): static
    {
        return $this->state(fn () => [
            'status'             => TicketStatus::OnHold,
            'last_sla_paused_at' => now(),
        ]);
    }

    public function resolved(): static
    {
        return $this->state(fn () => [
            'status'      => TicketStatus::Resolved,
            'resolved_at' => now(),
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn () => [
            'status' => TicketStatus::Archived,
        ]);
    }
}
