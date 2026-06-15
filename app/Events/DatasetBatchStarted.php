<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DatasetBatchStarted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $datasetProjectId,
        public readonly int $datasetVersionId,
        public readonly int $generationBatchId,
        public readonly int $userId = 0,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('dataset-project.'.$this->datasetProjectId),
            new PrivateChannel('App.Models.User.'.$this->userId),
        ];
    }
}
