<?php

namespace App\Services;

use App\Models\User;
use App\Models\Ticket;
use App\Models\TicketMessage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class TicketMessageService
{
    /**
     * @return LengthAwarePaginator<int, TicketMessage>
     */
    public function paginateForAgent(Ticket $ticket): LengthAwarePaginator
    {
        return $ticket->messages()->with(['user' => function ($q) {
            $q->with(['agent', 'client']);
        }])->latest('id')->paginate();
    }

    /**
     * @return LengthAwarePaginator<int, TicketMessage>
     */
    public function paginateForClient(Ticket $ticket): LengthAwarePaginator
    {
        return $ticket->messages()->with(['user' => function ($q) {
            $q->with(['agent', 'client']);
        }])
            ->where('is_internal', false)
            ->latest('id')
            ->paginate();
    }

    public function storeForAgent(Ticket $ticket, User $user, string $content, bool $isInternal): TicketMessage
    {
        /** @var TicketMessage $message */
        $message = $ticket->messages()->create([
            'user_id'     => $user->id,
            'content'     => $content,
            'is_internal' => $isInternal,
        ]);

        $message->load('user');

        return $message;
    }

    public function storeForClient(Ticket $ticket, User $user, string $content): TicketMessage
    {
        /** @var TicketMessage $message */
        $message = $ticket->messages()->create([
            'user_id'     => $user->id,
            'content'     => $content,
            'is_internal' => false,
        ]);

        $message->load('user');

        return $message;
    }
}
