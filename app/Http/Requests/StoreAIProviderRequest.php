<?php

namespace App\Http\Requests;

use App\Enums\AIProviderType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAIProviderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $isUpdate = $this->route('provider') !== null;

        return [
            'label' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(AIProviderType::class)],
            'api_key' => [$isUpdate ? 'nullable' : 'required', 'string', 'min:1'],
            'base_url' => ['nullable', 'url', 'max:500'],
            'default_model' => ['nullable', 'string', 'max:255'],
            'is_enabled' => ['boolean'],
        ];
    }
}
