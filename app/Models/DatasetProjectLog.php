<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DatasetProjectLog extends Model
{
    protected $fillable = [
        'dataset_project_id',
        'generation_batch_id',
        'level',
        'message',
        'details',
    ];

    protected $casts = [
        'details' => 'array',
    ];

    public function datasetProject()
    {
        return $this->belongsTo(DatasetProject::class);
    }

    public function generationBatch()
    {
        return $this->belongsTo(GenerationBatch::class);
    }
}
