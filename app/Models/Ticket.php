<?php

namespace App\Models;

use App\Enums\TicketStatus;
use App\Enums\TicketSlaStatus;
use App\Enums\TicketSlaPriority;
use Database\Factories\TicketFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Support\LogOptions;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
    use HasFactory, LogsActivity;

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

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable();
    }
}
