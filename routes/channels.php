<?php

use App\Models\DatasetProject;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('dataset-project.{datasetProjectId}', function ($user, int $datasetProjectId) {
    return DatasetProject::where('id', $datasetProjectId)
        ->where('user_id', $user->id)
        ->exists();
});
