<?php

namespace App\Http\Requests\Api\V1\Agent;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\ValidationRule;

class TicketMessageStoreRequest extends FormRequest
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
            'content'     => ['required', 'string', 'max:3000'],
            'is_internal' => ['nullable', 'boolean'],
        ];
    }
}
