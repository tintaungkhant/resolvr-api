<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Validation\Rule;
use App\Enums\TicketSlaPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\ValidationRule;

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
        return TicketSlaPriority::from($this->input('priority'));
    }
}
