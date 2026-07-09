<?php

namespace App\Models;

use Database\Factories\DatasetSourceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DatasetSource extends Model
{
    /** @use HasFactory<DatasetSourceFactory> */
    use HasFactory;

    protected $fillable = [
        'dataset_project_id',
        'original_filename',
        'file_path',
        'source_type',
        'parsed_schema',
        'row_count',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'parsed_schema' => 'array',
            'metadata' => 'array',
            'row_count' => 'integer',
        ];
    }

    public function datasetProject(): BelongsTo
    {
        return $this->belongsTo(DatasetProject::class);
    }
}
