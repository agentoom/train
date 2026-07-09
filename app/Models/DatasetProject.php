<?php

namespace App\Models;

use App\Enums\DatasetStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DatasetProject extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'ai_provider_id',
        'name',
        'description',
        'system_prompt',
        'schema',
        'record_count',
        'chunk_size',
        'model',
        'temperature',
        'max_tokens',
        'strategy',
        'status',
        'completed_at',
        'failed_at',
        'metadata',
        'diversity_dimensions',
        'generation_seed',
        'uniqueness_level',
        'semantic_deduplication_enabled',
        'semantic_similarity_threshold',
        'replacement_generation_enabled',
        'evaluation_enabled',
        'evaluation_ai_provider_id',
        'evaluation_model',
        'minimum_quality_score',
        'critic_enabled',
        'critic_ai_provider_id',
        'critic_model',
        'refiner_enabled',
        'refiner_ai_provider_id',
        'refiner_model',
        'negative_example_ratio',
        'conversation_enabled',
        'min_turns',
        'max_turns',
        'branching_enabled',
        'conversation_type',
        'augmentation_enabled',
        'augmentation_mode',
        'augmentation_strength',
        'augmentation_target_count',
        'augmentation_expand_percent',
    ];

    protected function casts(): array
    {
        return [
            'status' => DatasetStatus::class,
            'schema' => 'array',
            'temperature' => 'float',
            'metadata' => 'array',
            'diversity_dimensions' => 'array',
            'semantic_deduplication_enabled' => 'boolean',
            'semantic_similarity_threshold' => 'float',
            'replacement_generation_enabled' => 'boolean',
            'evaluation_enabled' => 'boolean',
            'minimum_quality_score' => 'integer',
            'critic_enabled' => 'boolean',
            'refiner_enabled' => 'boolean',
            'negative_example_ratio' => 'integer',
            'conversation_enabled' => 'boolean',
            'min_turns' => 'integer',
            'max_turns' => 'integer',
            'branching_enabled' => 'boolean',
            'augmentation_enabled' => 'boolean',
            'augmentation_strength' => 'float',
            'augmentation_target_count' => 'integer',
            'augmentation_expand_percent' => 'integer',
            'completed_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function aiProvider(): BelongsTo
    {
        return $this->belongsTo(AIProvider::class);
    }

    public function evaluationAiProvider(): BelongsTo
    {
        return $this->belongsTo(AIProvider::class, 'evaluation_ai_provider_id');
    }

    public function criticAiProvider(): BelongsTo
    {
        return $this->belongsTo(AIProvider::class, 'critic_ai_provider_id');
    }

    public function refinerAiProvider(): BelongsTo
    {
        return $this->belongsTo(AIProvider::class, 'refiner_ai_provider_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(DatasetVersion::class);
    }

    public function generationUsages(): HasMany
    {
        return $this->hasMany(GenerationUsage::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(DatasetProjectLog::class);
    }

    public function sources(): HasMany
    {
        return $this->hasMany(DatasetSource::class);
    }
}
