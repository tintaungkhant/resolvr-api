<?php

namespace Database\Seeders;

use App\Models\Agent;
use App\Models\Client;
use App\Models\Ticket;
use App\Enums\TicketStatus;
use App\Models\TicketMessage;
use App\Enums\TicketSlaStatus;
use App\Utils\SlaTimeGenerator;
use Illuminate\Database\Seeder;
use App\Enums\TicketSlaPriority;

class TicketSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $clients = Client::with('user')->get();
        $agents = Agent::with('user')->get();

        if ($clients->isEmpty() || $agents->isEmpty()) {
            $this->command->warn('Skipping TicketSeeder: no clients or agents found. Run UserSeeder first.');

            return;
        }

        $scenarios = $this->buildScenarios();

        foreach ($scenarios as $scenario) {
            $client = $clients->random();
            $agent = $agents->random();
            $priority = $scenario['priority'];
            $slaResolutionTime = SlaTimeGenerator::generate($priority);
            $startedAt = $scenario['started_at'];
            $dueAt = $startedAt->copy()->addSeconds($slaResolutionTime + ($scenario['sla_paused_time'] ?? 0));

            $ticket = Ticket::create([
                'organization_id'     => $client->organization_id,
                'issuer_id'           => $client->user_id,
                'assignee_id'         => $scenario['unassigned'] ?? false ? null : $agent->user_id,
                'title'               => $scenario['title'],
                'description'         => $scenario['description'],
                'priority'            => $priority,
                'status'              => $scenario['status'],
                'sla_status'          => $scenario['sla_status'],
                'sla_resolution_time' => $slaResolutionTime,
                'sla_paused_time'     => $scenario['sla_paused_time'] ?? 0,
                'started_at'          => $startedAt,
                'due_at'              => $dueAt,
                'resolved_at'         => $scenario['resolved_at'] ?? null,
                'overdue_at'          => $scenario['overdue_at'] ?? null,
                'last_sla_paused_at'  => $scenario['last_sla_paused_at'] ?? null,
            ]);

            // Initial message from client
            TicketMessage::create([
                'ticket_id'   => $ticket->id,
                'user_id'     => $client->user_id,
                'content'     => $scenario['description'],
                'is_internal' => false,
                'created_at'  => $startedAt,
            ]);

            // Agent reply
            TicketMessage::create([
                'ticket_id'   => $ticket->id,
                'user_id'     => $agent->user_id,
                'content'     => fake()->randomElement([
                    'Thank you for reaching out. I am looking into this now.',
                    'I have received your ticket and will investigate shortly.',
                    'Thanks for reporting this. Let me check our systems.',
                ]),
                'is_internal' => false,
                'created_at'  => $startedAt->copy()->addMinutes(rand(5, 30)),
            ]);

            // Internal note on some tickets
            if (rand(0, 1)) {
                TicketMessage::create([
                    'ticket_id'   => $ticket->id,
                    'user_id'     => $agent->user_id,
                    'content'     => fake()->randomElement([
                        'Checked logs — this is a known issue from the last deploy.',
                        'Customer is on the enterprise plan, prioritize accordingly.',
                        'Escalating to the infrastructure team.',
                        'Waiting for response from the client before proceeding.',
                    ]),
                    'is_internal' => true,
                    'created_at'  => $startedAt->copy()->addMinutes(rand(10, 60)),
                ]);
            }

            // Client follow-up on some tickets
            if (rand(0, 1)) {
                TicketMessage::create([
                    'ticket_id'   => $ticket->id,
                    'user_id'     => $client->user_id,
                    'content'     => fake()->randomElement([
                        'Any update on this?',
                        'This is still happening on our end.',
                        'Thanks for looking into it!',
                        'We have additional details to share — the issue also affects our reporting module.',
                    ]),
                    'is_internal' => false,
                    'created_at'  => $startedAt->copy()->addHours(rand(1, 3)),
                ]);
            }
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildScenarios(): array
    {
        return [
            // On-track tickets (recently created)
            [
                'title'       => 'Cannot access dashboard after login',
                'description' => 'After signing in, the dashboard shows a blank page. Tried clearing cache and different browsers.',
                'priority'    => TicketSlaPriority::High,
                'status'      => TicketStatus::Open,
                'sla_status'  => TicketSlaStatus::OnTrack,
                'started_at'  => now()->subMinutes(30),
            ],
            [
                'title'       => 'Need to update billing information',
                'description' => 'Our company credit card has changed. Please update the payment method on file.',
                'priority'    => TicketSlaPriority::Low,
                'status'      => TicketStatus::Open,
                'sla_status'  => TicketSlaStatus::OnTrack,
                'started_at'  => now()->subHour(),
            ],
            [
                'title'       => 'Feature request: export to CSV',
                'description' => 'It would be great to have an export button on the reports page to download data as CSV.',
                'priority'    => TicketSlaPriority::Low,
                'status'      => TicketStatus::Open,
                'sla_status'  => TicketSlaStatus::OnTrack,
                'started_at'  => now()->subMinutes(45),
            ],
            [
                'title'       => 'API rate limit errors on integration',
                'description' => 'Our integration is getting 429 errors during peak hours. We need the rate limit increased.',
                'priority'    => TicketSlaPriority::Medium,
                'status'      => TicketStatus::Open,
                'sla_status'  => TicketSlaStatus::OnTrack,
                'started_at'  => now()->subHours(2),
            ],

            // Due-soon tickets
            [
                'title'       => 'Email notifications not being delivered',
                'description' => 'Users in our organization are not receiving email notifications for the past 2 days.',
                'priority'    => TicketSlaPriority::High,
                'status'      => TicketStatus::Open,
                'sla_status'  => TicketSlaStatus::DueSoon,
                'started_at'  => now()->subHours(3)->subMinutes(30),
            ],
            [
                'title'       => 'Slow page load on user management',
                'description' => 'The user management page takes over 10 seconds to load when we have 500+ users.',
                'priority'    => TicketSlaPriority::Medium,
                'status'      => TicketStatus::Open,
                'sla_status'  => TicketSlaStatus::DueSoon,
                'started_at'  => now()->subHours(5),
            ],

            // Overdue tickets
            [
                'title'       => 'Production database connection timeouts',
                'description' => 'We are experiencing intermittent database connection timeouts in production. This is affecting all users.',
                'priority'    => TicketSlaPriority::Urgent,
                'status'      => TicketStatus::Open,
                'sla_status'  => TicketSlaStatus::Overdue,
                'started_at'  => now()->subHours(3),
                'overdue_at'  => now()->subHour(),
            ],
            [
                'title'       => 'SSO login broken after identity provider update',
                'description' => 'None of our users can log in via SSO since the identity provider pushed an update yesterday.',
                'priority'    => TicketSlaPriority::High,
                'status'      => TicketStatus::Open,
                'sla_status'  => TicketSlaStatus::Overdue,
                'started_at'  => now()->subHours(6),
                'overdue_at'  => now()->subHours(2),
            ],

            // On-hold tickets
            [
                'title'              => 'Data migration from legacy system',
                'description'        => 'We need to migrate historical data from our old system. Waiting on the client to provide the export file.',
                'priority'           => TicketSlaPriority::Medium,
                'status'             => TicketStatus::OnHold,
                'sla_status'         => TicketSlaStatus::OnTrack,
                'started_at'         => now()->subHours(4),
                'sla_paused_time'    => 3600,
                'last_sla_paused_at' => now()->subHour(),
            ],
            [
                'title'              => 'Custom report template not rendering',
                'description'        => 'The custom report template we uploaded shows a blank output. Waiting for the design team to provide a corrected file.',
                'priority'           => TicketSlaPriority::Low,
                'status'             => TicketStatus::OnHold,
                'sla_status'         => TicketSlaStatus::OnTrack,
                'started_at'         => now()->subHours(5),
                'sla_paused_time'    => 7200,
                'last_sla_paused_at' => now()->subHours(2),
            ],

            // Resolved tickets
            [
                'title'       => 'Two-factor authentication setup failing',
                'description' => 'Getting an error when trying to enable 2FA on my account.',
                'priority'    => TicketSlaPriority::Medium,
                'status'      => TicketStatus::Resolved,
                'sla_status'  => TicketSlaStatus::OnTrack,
                'started_at'  => now()->subDay(),
                'resolved_at' => now()->subHours(20),
            ],
            [
                'title'       => 'Incorrect timezone on scheduled reports',
                'description' => 'All scheduled reports show timestamps in UTC instead of our configured timezone (EST).',
                'priority'    => TicketSlaPriority::Low,
                'status'      => TicketStatus::Resolved,
                'sla_status'  => TicketSlaStatus::OnTrack,
                'started_at'  => now()->subDays(2),
                'resolved_at' => now()->subDay(),
            ],

            // Archived ticket
            [
                'title'       => 'Initial onboarding setup assistance',
                'description' => 'Need help setting up our organization account, creating teams, and configuring permissions.',
                'priority'    => TicketSlaPriority::Low,
                'status'      => TicketStatus::Archived,
                'sla_status'  => TicketSlaStatus::OnTrack,
                'started_at'  => now()->subWeek(),
                'resolved_at' => now()->subDays(5),
            ],

            // Unassigned ticket
            [
                'title'       => 'Webhook delivery failures to our endpoint',
                'description' => 'Webhooks stopped being delivered to our endpoint about an hour ago. Our server is up and accepting connections.',
                'priority'    => TicketSlaPriority::High,
                'status'      => TicketStatus::Open,
                'sla_status'  => TicketSlaStatus::OnTrack,
                'started_at'  => now()->subMinutes(20),
                'unassigned'  => true,
            ],
        ];
    }
}
