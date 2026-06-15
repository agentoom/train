<?php

namespace App\Policies;

use App\Models\AIProvider;
use App\Models\User;

class AIProviderPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, AIProvider $aIProvider): bool
    {
        return $user->id === $aIProvider->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, AIProvider $aIProvider): bool
    {
        return $user->id === $aIProvider->user_id;
    }

    public function delete(User $user, AIProvider $aIProvider): bool
    {
        return $user->id === $aIProvider->user_id;
    }

    public function restore(User $user, AIProvider $aIProvider): bool
    {
        return $user->id === $aIProvider->user_id;
    }

    public function forceDelete(User $user, AIProvider $aIProvider): bool
    {
        return $user->id === $aIProvider->user_id;
    }
}
