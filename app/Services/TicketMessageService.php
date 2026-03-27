<?php

namespace App\Services;

use App\Models\User;
use App\Models\Ticket;
use App\Models\TicketMessage;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class TicketMessageService
{
    /**
     * @return LengthAwarePaginator<int, TicketMessage>
     */
    public function paginateForAgent(Ticket $ticket): LengthAwarePaginator
    {
        $relation = $ticket->messages()->with(['user' => function ($q) {
            $q->with(['agent', 'client']);
        }]);

        return $this->paginateLatestReversed($relation);
    }

    /**
     * @return LengthAwarePaginator<int, TicketMessage>
     */
    public function paginateForClient(Ticket $ticket): LengthAwarePaginator
    {
        $relation = $ticket->messages()->with(['user' => function ($q) {
            $q->with(['agent', 'client']);
        }])->where('is_internal', false);

        return $this->paginateLatestReversed($relation);
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

    private function paginateLatestReversed(HasMany $relation): LengthAwarePaginator
    {
        $paginator = $relation->latest('id')->paginate();
        $paginator->setCollection($paginator->getCollection()->reverse()->values());

        return $paginator;
    }
}
