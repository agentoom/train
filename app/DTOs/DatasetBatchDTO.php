<?php

namespace App\DTOs;

use App\Models\DatasetVersion;
use Illuminate\Support\Collection;

final class DatasetBatchDTO
{
    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>|null  $schema
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly DatasetVersion $version,
        public readonly Collection $rows,
        public readonly ?array $schema,
        public readonly array $metadata = [],
    ) {}

    public function totalRows(): int
    {
        return $this->rows->count();
    }
}
