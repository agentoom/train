<?php

namespace App\Policies;

use App\Models\DatasetProject;
use App\Models\User;

class DatasetProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, DatasetProject $datasetProject): bool
    {
        return $user->id === $datasetProject->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, DatasetProject $datasetProject): bool
    {
        return $user->id === $datasetProject->user_id;
    }

    public function delete(User $user, DatasetProject $datasetProject): bool
    {
        return $user->id === $datasetProject->user_id;
    }

    public function restore(User $user, DatasetProject $datasetProject): bool
    {
        return $user->id === $datasetProject->user_id;
    }

    public function forceDelete(User $user, DatasetProject $datasetProject): bool
    {
        return $user->id === $datasetProject->user_id;
    }
}
