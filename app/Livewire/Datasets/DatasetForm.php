<?php

namespace App\Livewire\Datasets;

use App\Actions\Datasets\CreateDatasetProjectAction;
use App\Actions\Datasets\UpdateDatasetProjectAction;
use App\Models\AIProvider;
use App\Models\DatasetProject;
use App\Models\DatasetSource;
use App\Services\Dataset\DatasetSettingsExportService;
use App\Services\Dataset\DatasetSourceParsingService;
use App\Services\AI\ProviderResolverService;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Title('Dataset Project')]
class DatasetForm extends Component
{
    use WithFileUploads;

    public ?DatasetProject $project = null;

    public string $name = '';

    public string $description = '';

    public ?int $aiProviderId = null;

    public string $systemPrompt = '';

    public string $schema = '';

    public int $recordCount = 10;

    public int $chunkSize = 10;

    public ?string $model = null;

    public float $temperature = 0.7;

    public int $maxTokens = 2048;

    public ?string $strategy = null;

    public string $diversityDimensions = '';

    public ?string $generationSeed = null;

    public string $uniquenessLevel = 'balanced';

    public bool $semanticDeduplicationEnabled = true;

    public float $semanticSimilarityThreshold = 0.92;

    public bool $replacementGenerationEnabled = true;

    public bool $evaluationEnabled = false;

    public ?int $evaluationAiProviderId = null;

    public ?string $evaluationModel = null;

    public int $minimumQualityScore = 75;

    public bool $criticEnabled = false;

    public ?int $criticAiProviderId = null;

    public ?string $criticModel = null;

    public bool $refinerEnabled = false;

    public ?int $refinerAiProviderId = null;

    public ?string $refinerModel = null;

    public int $negativeExampleRatio = 0;

    public bool $conversationEnabled = false;

    public int $minTurns = 2;

    public int $maxTurns = 6;

    public bool $branchingEnabled = false;

    public string $conversationType = 'assistant_chat';

    public bool $augmentationEnabled = false;

    public string $augmentationMode = 'similar';

    public float $augmentationStrength = 0.5;

    public ?int $augmentationTargetCount = null;

    public ?int $augmentationExpandPercent = null;

    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null */
    public $sourceFile = null;

    public function mount(?DatasetProject $project = null): void
    {
        if ($project && $project->exists) {
            $this->authorize('update', $project);
            $this->project = $project;
            $this->name = $project->name;
            $this->description = $project->description ?? '';
            $this->aiProviderId = $project->ai_provider_id;
            $this->systemPrompt = $project->system_prompt ?? '';
            $this->schema = $project->schema ? json_encode($project->schema, JSON_PRETTY_PRINT) : '';
            $this->recordCount = $project->record_count;
            $this->chunkSize = $project->chunk_size ?? 10;
            $this->model = $project->model;
            $this->temperature = $project->temperature ?? 0.7;
            $this->maxTokens = $project->max_tokens ?? 2048;
            $this->strategy = $project->strategy;
            $this->diversityDimensions = $project->diversity_dimensions
                ? json_encode($project->diversity_dimensions, JSON_PRETTY_PRINT)
                : '';
            $this->generationSeed = $project->generation_seed;
            $this->uniquenessLevel = $project->uniqueness_level ?? 'balanced';
            $this->semanticDeduplicationEnabled = (bool) $project->semantic_deduplication_enabled;
            $this->semanticSimilarityThreshold = (float) ($project->semantic_similarity_threshold ?? 0.92);
            $this->replacementGenerationEnabled = (bool) $project->replacement_generation_enabled;
            $this->evaluationEnabled = (bool) $project->evaluation_enabled;
            $this->evaluationAiProviderId = $project->evaluation_ai_provider_id;
            $this->evaluationModel = $project->evaluation_model;
            $this->minimumQualityScore = $project->minimum_quality_score ?? 75;
            $this->criticEnabled = (bool) $project->critic_enabled;
            $this->criticAiProviderId = $project->critic_ai_provider_id;
            $this->criticModel = $project->critic_model;
            $this->refinerEnabled = (bool) $project->refiner_enabled;
            $this->refinerAiProviderId = $project->refiner_ai_provider_id;
            $this->refinerModel = $project->refiner_model;
            $this->negativeExampleRatio = $project->negative_example_ratio ?? 0;
            $this->conversationEnabled = (bool) $project->conversation_enabled;
            $this->minTurns = $project->min_turns ?? 2;
            $this->maxTurns = $project->max_turns ?? 6;
            $this->branchingEnabled = (bool) $project->branching_enabled;
            $this->conversationType = $project->conversation_type ?? 'assistant_chat';
            $this->augmentationEnabled = (bool) $project->augmentation_enabled;
            $this->augmentationMode = $project->augmentation_mode ?? 'similar';
            $this->augmentationStrength = (float) ($project->augmentation_strength ?? 0.5);
            $this->augmentationTargetCount = $project->augmentation_target_count;
            $this->augmentationExpandPercent = $project->augmentation_expand_percent;
        }
    }

    public function save(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'aiProviderId' => ['nullable', 'integer', 'exists:ai_providers,id'],
            'systemPrompt' => ['nullable', 'string'],
            'schema' => ['nullable', 'string'],
            'recordCount' => ['required', 'integer', 'min:1', 'max:100000'],
            'chunkSize' => ['required', 'integer', 'min:1', 'max:1000'],
            'model' => ['nullable', 'string', 'max:255'],
            'temperature' => ['nullable', 'numeric', 'min:0', 'max:2'],
            'maxTokens' => ['nullable', 'integer', 'min:1', 'max:32768'],
            'strategy' => ['nullable', 'string', 'max:255'],
            'diversityDimensions' => ['nullable', 'string'],
            'generationSeed' => ['nullable', 'string', 'max:255'],
            'uniquenessLevel' => ['required', 'string', 'in:conservative,balanced,aggressive'],
            'semanticDeduplicationEnabled' => ['boolean'],
            'semanticSimilarityThreshold' => ['required', 'numeric', 'min:0.75', 'max:0.99'],
            'replacementGenerationEnabled' => ['boolean'],
            'evaluationEnabled' => ['boolean'],
            'evaluationAiProviderId' => ['nullable', 'integer', 'exists:ai_providers,id'],
            'evaluationModel' => ['nullable', 'string', 'max:255'],
            'minimumQualityScore' => ['required', 'integer', 'min:0', 'max:100'],
            'criticEnabled' => ['boolean'],
            'criticAiProviderId' => ['nullable', 'integer', 'exists:ai_providers,id'],
            'criticModel' => ['nullable', 'string', 'max:255'],
            'refinerEnabled' => ['boolean'],
            'refinerAiProviderId' => ['nullable', 'integer', 'exists:ai_providers,id'],
            'refinerModel' => ['nullable', 'string', 'max:255'],
            'negativeExampleRatio' => ['required', 'integer', 'min:0', 'max:30'],
            'conversationEnabled' => ['boolean'],
            'minTurns' => ['required', 'integer', 'min:1', 'max:20'],
            'maxTurns' => ['required', 'integer', 'min:1', 'max:20'],
            'branchingEnabled' => ['boolean'],
            'conversationType' => ['required', 'string', 'in:assistant_chat,tool_usage,support_workflow,multi_step_reasoning'],
            'augmentationEnabled' => ['boolean'],
            'augmentationMode' => ['required', 'string', 'in:similar,diverse,edge_cases,adversarial'],
            'augmentationStrength' => ['required', 'numeric', 'min:0.1', 'max:1.0'],
            'augmentationTargetCount' => ['nullable', 'integer', 'min:1'],
            'augmentationExpandPercent' => ['nullable', 'integer', 'min:1', 'max:10000'],
        ]);

        $data = [
            'name' => $this->name,
            'description' => $this->description ?: null,
            'ai_provider_id' => $this->aiProviderId,
            'system_prompt' => $this->systemPrompt ?: null,
            'schema' => $this->schema ?: null,
            'record_count' => $this->recordCount,
            'chunk_size' => $this->chunkSize,
            'model' => $this->model,
            'temperature' => $this->temperature,
            'max_tokens' => $this->maxTokens,
            'strategy' => $this->strategy,
            'diversity_dimensions' => $this->diversityDimensions ?: null,
            'generation_seed' => $this->generationSeed ?: null,
            'uniqueness_level' => $this->uniquenessLevel,
            'semantic_deduplication_enabled' => $this->semanticDeduplicationEnabled,
            'semantic_similarity_threshold' => $this->semanticSimilarityThreshold,
            'replacement_generation_enabled' => $this->replacementGenerationEnabled,
            'evaluation_enabled' => $this->evaluationEnabled,
            'evaluation_ai_provider_id' => $this->evaluationAiProviderId,
            'evaluation_model' => $this->evaluationModel,
            'minimum_quality_score' => $this->minimumQualityScore,
            'critic_enabled' => $this->criticEnabled,
            'critic_ai_provider_id' => $this->criticAiProviderId,
            'critic_model' => $this->criticModel,
            'refiner_enabled' => $this->refinerEnabled,
            'refiner_ai_provider_id' => $this->refinerAiProviderId,
            'refiner_model' => $this->refinerModel,
            'negative_example_ratio' => $this->negativeExampleRatio,
            'conversation_enabled' => $this->conversationEnabled,
            'min_turns' => $this->minTurns,
            'max_turns' => $this->maxTurns,
            'branching_enabled' => $this->branchingEnabled,
            'conversation_type' => $this->conversationType,
            'augmentation_enabled' => $this->augmentationEnabled,
            'augmentation_mode' => $this->augmentationMode,
            'augmentation_strength' => $this->augmentationStrength,
            'augmentation_target_count' => $this->augmentationTargetCount,
            'augmentation_expand_percent' => $this->augmentationExpandPercent,
        ];

        if ($this->project) {
            $this->authorize('update', $this->project);
            app(UpdateDatasetProjectAction::class)->execute($this->project, $data);
            Flux::toast(variant: 'success', text: 'Dataset project updated.');
            $this->redirect(route('datasets.index'), navigate: true);
        } else {
            $this->authorize('create', DatasetProject::class);
            $project = app(CreateDatasetProjectAction::class)->execute(Auth::user(), $data);
            Flux::toast(variant: 'success', text: 'Dataset project created.');
            $this->redirect(route('datasets.show', $project), navigate: true);
        }
    }

    public function uploadSource(DatasetSourceParsingService $parsingService): void
    {
        $this->validate(['sourceFile' => ['required', 'file', 'max:51200', 'mimes:json,jsonl,csv,txt,md,xlsx']]);

        if (! $this->project) {
            Flux::toast(variant: 'warning', text: 'Save the dataset project first before uploading source files.');

            return;
        }

        $this->authorize('update', $this->project);

        $parsed = $parsingService->parse($this->sourceFile);
        $path = $parsingService->store($this->sourceFile);

        DatasetSource::create([
            'dataset_project_id' => $this->project->id,
            'original_filename' => $this->sourceFile->getClientOriginalName(),
            'file_path' => $path,
            'source_type' => strtolower($this->sourceFile->getClientOriginalExtension()),
            'parsed_schema' => $parsed['schema'],
            'row_count' => count($parsed['rows']),
            'metadata' => $parsed['metadata'],
        ]);

        $this->sourceFile = null;
        Flux::toast(variant: 'success', text: 'Source file uploaded and parsed.');
    }

    public function deleteSource(int $sourceId): void
    {
        $source = DatasetSource::findOrFail($sourceId);
        $this->authorize('update', $source->datasetProject);
        $source->delete();
        Flux::toast(variant: 'success', text: 'Source file removed.');
    }

    public function exportSettings(DatasetSettingsExportService $exportService): StreamedResponse
    {
        if (! $this->project) {
            Flux::toast(variant: 'warning', text: 'Save the dataset project first before exporting.');

            return response()->streamDownload(fn () => print(''), 'dataset.json');
        }

        $this->authorize('view', $this->project);

        $json = $exportService->exportMany(collect([$this->project]));
        $filename = str($this->project->name)->slug()->append('-settings.json')->toString();

        return response()->streamDownload(function () use ($json) {
            echo $json;
        }, $filename, ['Content-Type' => 'application/json']);
    }

    /** @return array<string, mixed> */
    public function getSelectedProviderCapabilities(): array
    {
        if (! $this->aiProviderId) {
            return [];
        }

        $provider = AIProvider::find($this->aiProviderId);

        if (! $provider) {
            return [];
        }

        $instance = app(ProviderResolverService::class)->resolve($provider);
        $caps = $instance->getCapabilities();

        return [
            'supportsJsonMode' => $caps->supportsJsonMode,
            'supportsStructuredOutput' => $caps->supportsStructuredOutput,
            'maxContextWindow' => $caps->maxContextWindow,
        ];
    }

    public function render(): View
    {
        $providers = AIProvider::where('user_id', Auth::id())
            ->where('is_enabled', true)
            ->orderBy('label')
            ->get();

        $sources = $this->project
            ? $this->project->sources()->orderBy('created_at')->get()
            : collect();

        return view('livewire.datasets.dataset-form', [
            'providers' => $providers,
            'capabilities' => $this->getSelectedProviderCapabilities(),
            'sources' => $sources,
        ])->layout('layouts.app', ['title' => $this->project ? __('Edit Dataset') : __('New Dataset')]);
    }
}
