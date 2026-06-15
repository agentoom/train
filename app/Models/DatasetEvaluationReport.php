<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DatasetEvaluationReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'dataset_version_id',
        'overall_score',
        'passed',
        'verdict',
        'evaluator_scores',
        'metadata',
        'duplicate_rate',
    ];

    protected function casts(): array
    {
        return [
            'overall_score' => 'float',
            'passed' => 'boolean',
            'evaluator_scores' => 'array',
            'metadata' => 'array',
            'duplicate_rate' => 'float',
        ];
    }

    public function datasetVersion(): BelongsTo
    {
        return $this->belongsTo(DatasetVersion::class);
    }

    public function releaseVersion(): HasOne
    {
        return $this->hasOne(DatasetReleaseVersion::class);
    }
}
