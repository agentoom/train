<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDatasetProjectRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'ai_provider_id' => ['nullable', 'integer', 'exists:ai_providers,id'],
            'system_prompt' => ['nullable', 'string'],
            'schema' => ['nullable', 'string'],
            'record_count' => ['required', 'integer', 'min:1', 'max:100000'],
            'model' => ['nullable', 'string', 'max:255'],
            'temperature' => ['nullable', 'numeric', 'min:0', 'max:2'],
            'max_tokens' => ['nullable', 'integer', 'min:1', 'max:32768'],
            'strategy' => ['nullable', 'string', 'max:255'],
        ];
    }
}
