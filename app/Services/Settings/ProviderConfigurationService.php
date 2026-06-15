<?php

namespace App\Services\Settings;

use App\Models\AIProvider;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class ProviderConfigurationService
{
    /**
     * @return Collection<int, AIProvider>
     */
    public function listForUser(User $user): Collection
    {
        return AIProvider::where('user_id', $user->id)
            ->orderBy('label')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $user, array $data): AIProvider
    {
        return AIProvider::create([
            'user_id' => $user->id,
            'label' => $data['label'],
            'type' => $data['type'],
            'api_key' => $data['api_key'],
            'base_url' => $data['base_url'] ?? null,
            'default_model' => $data['default_model'] ?? null,
            'is_enabled' => $data['is_enabled'] ?? true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(AIProvider $provider, array $data): AIProvider
    {
        $provider->update([
            'label' => $data['label'],
            'type' => $data['type'],
            'base_url' => $data['base_url'] ?? null,
            'default_model' => $data['default_model'] ?? null,
            'is_enabled' => $data['is_enabled'] ?? $provider->is_enabled,
        ]);

        if (! empty($data['api_key'])) {
            $provider->update(['api_key' => $data['api_key']]);
        }

        return $provider->fresh();
    }

    public function delete(AIProvider $provider): void
    {
        $provider->delete();
    }

    public function toggleEnabled(AIProvider $provider): AIProvider
    {
        $provider->update(['is_enabled' => ! $provider->is_enabled]);

        return $provider->fresh();
    }
}
