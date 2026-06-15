<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DatasetFeedbackReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'evaluation_run_id',
        'failure_analysis_json',
        'improvement_signals_json',
        'system_health_score',
    ];

    protected $casts = [
        'failure_analysis_json' => 'array',
        'improvement_signals_json' => 'array',
        'system_health_score' => 'float',
    ];

    public function evaluationReport(): BelongsTo
    {
        return $this->belongsTo(DatasetEvaluationReport::class, 'evaluation_run_id');
    }
}
