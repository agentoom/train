<?php

namespace App\Models;

use App\Enums\DatasetStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DatasetVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'dataset_project_id',
        'ai_provider_id',
        'version_number',
        'model',
        'system_prompt_snapshot',
        'schema_snapshot',
        'record_count',
        'generation_seed',
        'status',
        'cancelled_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => DatasetStatus::class,
            'schema_snapshot' => 'array',
            'metadata' => 'array',
            'cancelled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $version): void {
            if ($version->generation_seed === null) {
                $version->generation_seed = random_int(1, PHP_INT_MAX);
            }
        });
    }

    public function datasetProject(): BelongsTo
    {
        return $this->belongsTo(DatasetProject::class);
    }

    public function aiProvider(): BelongsTo
    {
        return $this->belongsTo(AIProvider::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(GenerationBatch::class);
    }

    public function rows(): HasMany
    {
        return $this->hasMany(DatasetRow::class);
    }

    public function generationUsages(): HasMany
    {
        return $this->hasMany(GenerationUsage::class);
    }
}
