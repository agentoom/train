<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DatasetReleaseVersion extends Model
{
    protected $fillable = [
        'dataset_evaluation_report_id',
        'dataset_version_id',
        'dataset_project_id',
        'release_tag',
        'overall_score',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'overall_score' => 'float',
        ];
    }

    public function evaluationReport(): BelongsTo
    {
        return $this->belongsTo(DatasetEvaluationReport::class, 'dataset_evaluation_report_id');
    }

    public function datasetVersion(): BelongsTo
    {
        return $this->belongsTo(DatasetVersion::class);
    }

    public function datasetProject(): BelongsTo
    {
        return $this->belongsTo(DatasetProject::class);
    }
}
