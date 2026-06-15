<?php

namespace App\Models;

use App\Enums\BatchStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class GenerationBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'dataset_version_id',
        'batch_number',
        'offset',
        'limit',
        'status',
        'retry_count',
        'started_at',
        'completed_at',
        'cancelled_at',
        'tokens_used',
        'estimated_cost',
        'error_message',
        'metadata',
        'provider_snapshot',
        'model_snapshot',
        'prompt_snapshot',
        'duplicates_detected',
        'regenerated',
        'partially_completed',
    ];

    protected function casts(): array
    {
        return [
            'status' => BatchStatus::class,
            'metadata' => 'array',
            'provider_snapshot' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'estimated_cost' => 'float',
        ];
    }

    public function datasetVersion(): BelongsTo
    {
        return $this->belongsTo(DatasetVersion::class);
    }

    public function rows(): HasMany
    {
        return $this->hasMany(DatasetRow::class);
    }

    public function generationUsage(): HasOne
    {
        return $this->hasOne(GenerationUsage::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(DatasetProjectLog::class);
    }
}
