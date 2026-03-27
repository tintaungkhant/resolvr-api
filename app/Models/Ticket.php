<?php

namespace App\Models;

use App\Enums\TicketStatus;
use App\Enums\TicketSlaStatus;
use App\Enums\TicketSlaPriority;
use Database\Factories\TicketFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

#[Fillable([
    'organization_id',
    'issuer_id',
    'assignee_id',
    'title',
    'description',
    'priority',
    'status',
    'sla_status',
    'sla_resolution_time',
    'sla_paused_time',
    'started_at',
    'due_at',
    'resolved_at',
    'overdue_at',
    'last_sla_paused_at',
])]
class Ticket extends Model
{
    /** @use HasFactory<TicketFactory> */
    use HasFactory;

    protected $casts = [
        'priority'           => TicketSlaPriority::class,
        'status'             => TicketStatus::class,
        'sla_status'         => TicketSlaStatus::class,
        'last_sla_paused_at' => 'datetime',
        'due_at'             => 'datetime',
        'resolved_at'        => 'datetime',
        'overdue_at'         => 'datetime',
    ];

    /** @return HasMany<TicketMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(TicketMessage::class);
    }
}
