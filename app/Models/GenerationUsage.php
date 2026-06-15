<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GenerationUsage extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'dataset_project_id',
        'dataset_version_id',
        'generation_batch_id',
        'ai_provider_id',
        'model',
        'prompt_tokens',
        'completion_tokens',
        'total_tokens',
        'estimated_cost',
        'latency_ms',
        'metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'estimated_cost' => 'float',
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function datasetProject(): BelongsTo
    {
        return $this->belongsTo(DatasetProject::class);
    }

    public function datasetVersion(): BelongsTo
    {
        return $this->belongsTo(DatasetVersion::class);
    }

    public function generationBatch(): BelongsTo
    {
        return $this->belongsTo(GenerationBatch::class);
    }

    public function aiProvider(): BelongsTo
    {
        return $this->belongsTo(AIProvider::class);
    }
}
