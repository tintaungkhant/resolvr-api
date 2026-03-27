<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\TicketSlaPriority;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TicketPriorityUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'priority' => ['required', 'string', Rule::enum(TicketSlaPriority::class)],
        ];
    }

    public function ticketSlaPriority(): TicketSlaPriority
    {
        return TicketSlaPriority::tryFrom($this->input('priority'));
    }
}
