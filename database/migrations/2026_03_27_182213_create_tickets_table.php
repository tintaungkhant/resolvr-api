<?php

use App\Enums\TicketStatus;
use App\Enums\TicketSlaStatus;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations');
            $table->foreignId('issuer_id')->constrained('users');
            $table->foreignId('assignee_id')->nullable()->constrained('users');
            $table->string('title');
            $table->text('description');
            $table->string('priority')->index();
            $table->string('status')->index()->default(TicketStatus::Open->value);
            $table->string('sla_status')->index()->default(TicketSlaStatus::OnTrack->value);
            $table->integer('sla_resolution_time')->comment('resolution time in seconds');
            $table->integer('sla_paused_time')->default(0)->comment('total paused time in seconds');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('due_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('overdue_at')->nullable();
            $table->timestamp('last_sla_paused_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
