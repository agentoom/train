<?php

namespace App\Models;

use App\Enums\AIProviderType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AIProvider extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'ai_providers';

    protected $fillable = [
        'user_id',
        'label',
        'type',
        'api_key',
        'base_url',
        'default_model',
        'is_enabled',
    ];

    protected function casts(): array
    {
        return [
            'type' => AIProviderType::class,
            'api_key' => 'encrypted',
            'is_enabled' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function datasetProjects(): HasMany
    {
        return $this->hasMany(DatasetProject::class);
    }

    public function datasetVersions(): HasMany
    {
        return $this->hasMany(DatasetVersion::class);
    }

    public function generationUsages(): HasMany
    {
        return $this->hasMany(GenerationUsage::class);
    }
}
