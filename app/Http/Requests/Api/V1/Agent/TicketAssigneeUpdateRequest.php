<?php

namespace App\Http\Requests\Api\V1\Agent;

use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\ValidationRule;

class TicketAssigneeUpdateRequest extends FormRequest
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
            'assignee_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where('role', UserRole::Agent->value),
            ],
        ];
    }

    public function assigneeId(): int
    {
        return (int) $this->input('assignee_id');
    }
}
