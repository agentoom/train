<?php

namespace App\Services\Dataset;

class GenerationBatchingService
{
    public function __construct(private readonly int $defaultBatchSize = 10) {}

    /**
     * Compute batch ranges for a given record count.
     *
     * @return array<int, array{batch_number: int, offset: int, limit: int}>
     */
    public function computeBatches(int $recordCount, ?int $batchSize = null, int $startOffset = 0, int $startBatchNumber = 1): array
    {
        $size = $batchSize ?? $this->defaultBatchSize;
        $size = max(1, $size);
        $batches = [];
        $batchNumber = $startBatchNumber;

        for ($offset = $startOffset; $offset < $recordCount; $offset += $size) {
            $limit = min($size, $recordCount - $offset);
            $batches[] = [
                'batch_number' => $batchNumber++,
                'offset' => $offset,
                'limit' => $limit,
            ];
        }

        return $batches;
    }

    public function getBatchSize(): int
    {
        return $this->defaultBatchSize;
    }
}
