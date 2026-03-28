<?php

namespace App\Console\Commands;

use App\Models\Ticket;
use App\Enums\TicketStatus;
use App\Enums\TicketSlaStatus;
use Illuminate\Console\Command;
use App\Utils\SlaTimeCalculator;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Attributes\Description;

#[Signature('app:update-ticket-sla-status')]
#[Description('Recalculate and update SLA status for all active tickets')]
class UpdateTicketSlaStatus extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $updated = 0;

        Ticket::query()
            ->where('status', TicketStatus::Open)
            ->chunkById(1000, function ($tickets) use (&$updated) {
                $groupedByStatus = [];
                $newlyOverdueIds = [];

                foreach ($tickets as $ticket) {
                    $newSlaStatus = SlaTimeCalculator::calcSlaStatus($ticket);

                    if ($ticket->sla_status === $newSlaStatus) {
                        continue;
                    }

                    $groupedByStatus[$newSlaStatus->value][] = $ticket->id;

                    if ($newSlaStatus === TicketSlaStatus::Overdue && $ticket->overdue_at === null) {
                        $newlyOverdueIds[] = $ticket->id;
                    }
                }

                foreach ($groupedByStatus as $status => $ids) {
                    Ticket::whereIn('id', $ids)->update(['sla_status' => $status]);
                    $updated += count($ids);
                }

                if ($newlyOverdueIds !== []) {
                    Ticket::whereIn('id', $newlyOverdueIds)->update(['overdue_at' => now()]);
                }
            });

        $this->info("Updated {$updated} tickets.");

        return self::SUCCESS;
    }
}
