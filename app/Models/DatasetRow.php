<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Scout\Searchable;

class DatasetRow extends Model
{
    use HasFactory, Searchable;

    public $timestamps = false;

    protected $fillable = [
        'dataset_version_id',
        'generation_batch_id',
        'row_index',
        'payload',
        'is_valid',
        'content_hash',
        'is_duplicate',
        'quality_score',
        'quality_reasoning',
        'quality_issues',
        'evaluation_failed',
        'generated_by',
        'critic_feedback',
        'refined_by',
        'expected_behavior',
        'failure_reason',
        'messages',
        'turn_count',
        'source_similarity_score',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'is_valid' => 'boolean',
            'is_duplicate' => 'boolean',
            'quality_score' => 'integer',
            'quality_issues' => 'array',
            'evaluation_failed' => 'boolean',
            'messages' => 'array',
            'turn_count' => 'integer',
            'source_similarity_score' => 'float',
            'created_at' => 'datetime',
        ];
    }

    public function datasetVersion(): BelongsTo
    {
        return $this->belongsTo(DatasetVersion::class);
    }

    public function generationBatch(): BelongsTo
    {
        return $this->belongsTo(GenerationBatch::class);
    }

    public function searchableAs(): string
    {
        return 'dataset_rows_v' . ($this->dataset_version_id ?? 'unknown');
    }

    /**
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => (string) $this->id,
            'dataset_version_id' => (string) $this->dataset_version_id,
            'payload_text' => $this->extractPayloadText(),
            'is_duplicate' => (int) $this->is_duplicate,
            'is_valid' => (int) $this->is_valid,
            'created_at' => $this->created_at?->timestamp ?? 0,
        ];
    }

    /** Index all rows; duplicates are filtered at search time via filter_by. */
    public function shouldBeSearchable(): bool
    {
        return true;
    }

    /** Flatten payload values into a single searchable string, truncated for Typesense limits. */
    private function extractPayloadText(): string
    {
        if (empty($this->payload)) {
            return '';
        }

        $values = array_map(
            fn ($v) => is_array($v) ? json_encode($v) : (string) $v,
            array_values($this->payload),
        );

        $text = implode(' ', $values);

        return mb_substr($text, 0, 1000);
    }
}
