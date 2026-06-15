<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex items-center justify-between gap-4">
        <div class="flex items-center gap-4">
            <flux:button :href="route('datasets.index')" wire:navigate variant="ghost" icon="arrow-left" size="sm">
                {{ __('Back') }}
            </flux:button>
            <flux:heading size="xl">{{ $project ? __('Edit Dataset') : __('New Dataset') }}</flux:heading>
        </div>
        @if ($project)
            <flux:button wire:click="exportSettings" variant="ghost" icon="arrow-down-tray" size="sm">
                {{ __('Export Settings') }}
            </flux:button>
        @endif
    </div>

    <div class="max-w-2xl">
        <form wire:submit="save">
            <div x-data="{ tab: 'basic' }">

                {{-- Tab Navigation --}}
                <div class="mb-6 flex gap-1 rounded-lg border border-neutral-200 bg-neutral-100 p-1 dark:border-neutral-700 dark:bg-neutral-800">
                    <button type="button" @click="tab = 'basic'"
                        :class="tab === 'basic' ? 'bg-white shadow-sm dark:bg-neutral-700 text-neutral-900 dark:text-white' : 'text-neutral-500 hover:text-neutral-700 dark:hover:text-neutral-300'"
                        class="flex-1 rounded-md px-3 py-1.5 text-sm font-medium transition-all">
                        {{ __('Basic') }}
                    </button>
                    <button type="button" @click="tab = 'quality'"
                        :class="tab === 'quality' ? 'bg-white shadow-sm dark:bg-neutral-700 text-neutral-900 dark:text-white' : 'text-neutral-500 hover:text-neutral-700 dark:hover:text-neutral-300'"
                        class="flex-1 rounded-md px-3 py-1.5 text-sm font-medium transition-all">
                        {{ __('Quality') }}
                    </button>
                    <button type="button" @click="tab = 'pipeline'"
                        :class="tab === 'pipeline' ? 'bg-white shadow-sm dark:bg-neutral-700 text-neutral-900 dark:text-white' : 'text-neutral-500 hover:text-neutral-700 dark:hover:text-neutral-300'"
                        class="flex-1 rounded-md px-3 py-1.5 text-sm font-medium transition-all">
                        {{ __('Pipeline') }}
                    </button>
                    <button type="button" @click="tab = 'conversations'"
                        :class="tab === 'conversations' ? 'bg-white shadow-sm dark:bg-neutral-700 text-neutral-900 dark:text-white' : 'text-neutral-500 hover:text-neutral-700 dark:hover:text-neutral-300'"
                        class="flex-1 rounded-md px-3 py-1.5 text-sm font-medium transition-all">
                        {{ __('Conversations') }}
                    </button>
                    <button type="button" @click="tab = 'augmentation'"
                        :class="tab === 'augmentation' ? 'bg-white shadow-sm dark:bg-neutral-700 text-neutral-900 dark:text-white' : 'text-neutral-500 hover:text-neutral-700 dark:hover:text-neutral-300'"
                        class="flex-1 rounded-md px-3 py-1.5 text-sm font-medium transition-all">
                        {{ __('Augmentation') }}
                    </button>
                </div>

                {{-- Tab: Basic --}}
                <div x-show="tab === 'basic'" class="space-y-8">

                    {{-- Basic Info --}}
                    <div class="space-y-4">
                        <flux:heading size="sm" class="text-neutral-500 uppercase tracking-wide">{{ __('Basic Information') }}</flux:heading>

                        <flux:input wire:model="name" :label="__('Name')" :placeholder="__('e.g. Customer Support QA Dataset')" required />

                        <flux:textarea wire:model="description" :label="__('Description')" :placeholder="__('Optional description')" rows="2" />

                        <flux:select wire:model.live="aiProviderId" :label="__('AI Provider')">
                            <flux:select.option value="">{{ __('No provider selected') }}</flux:select.option>
                            @foreach($providers as $provider)
                                <flux:select.option :value="$provider->id">{{ $provider->label }}</flux:select.option>
                            @endforeach
                        </flux:select>

                        @if(!empty($capabilities))
                            <div class="rounded-lg border border-blue-200 bg-blue-50 p-3 text-sm dark:border-blue-800 dark:bg-blue-950">
                                <flux:text class="font-medium text-blue-700 dark:text-blue-400">{{ __('Provider Capabilities') }}</flux:text>
                                <ul class="mt-1 space-y-1 text-blue-600 dark:text-blue-500">
                                    @if($capabilities['supportsJsonMode'] ?? false)
                                        <li>✓ {{ __('JSON Mode supported') }}</li>
                                    @endif
                                    @if($capabilities['supportsStructuredOutput'] ?? false)
                                        <li>✓ {{ __('Structured Output supported') }}</li>
                                    @endif
                                    @if(isset($capabilities['maxContextWindow']))
                                        <li>{{ __('Max context: :n tokens', ['n' => number_format($capabilities['maxContextWindow'])]) }}</li>
                                    @endif
                                </ul>
                            </div>
                        @endif

                        <flux:input wire:model="model" :label="__('Model')" :placeholder="__('e.g. gpt-4o-mini')" />
                    </div>

                    {{-- Prompt Configuration --}}
                    <div class="space-y-4">
                        <flux:heading size="sm" class="text-neutral-500 uppercase tracking-wide">{{ __('Prompt Configuration') }}</flux:heading>

                        <div>
                            <flux:textarea wire:model="systemPrompt" :label="__('System Prompt')" rows="4"
                                :placeholder="__('e.g. Generate realistic customer support conversations for an e-commerce company.')" />
                            <flux:text class="mt-1 text-sm text-neutral-500">
                                {{ __('Describe what the AI should generate, the tone, context, constraints, and quality expectations. Be specific — better prompts produce better datasets.') }}
                            </flux:text>
                        </div>

                        <div>
                            <flux:textarea wire:model.live="schema" :label="__('JSON Schema')" rows="6"
                                :placeholder="__('Optional JSON schema for structured output')" />
                            <flux:text class="mt-1 text-sm text-neutral-500">
                                {{ __('Define the expected JSON structure for each row. Nested objects and arrays are supported. When provided, the AI will be instructed to match this schema exactly.') }}
                            </flux:text>
                            @if($schema)
                                <div class="mt-2">
                                    <livewire:components.json-preview :json="$schema" label="Schema Preview" />
                                </div>
                            @endif
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <flux:input wire:model="recordCount" :label="__('Record Count')" type="number" min="1" max="100000" required />
                                <flux:text class="mt-1 text-sm text-neutral-500">
                                    {{ __('Total number of dataset rows to generate.') }}
                                </flux:text>
                            </div>
                            <div>
                                <flux:input wire:model="chunkSize" :label="__('Chunk Size')" type="number" min="1" max="1000" required />
                                <flux:text class="mt-1 text-sm text-neutral-500">
                                    {{ __('Number of rows per generation batch. Default is 10.') }}
                                </flux:text>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <flux:input wire:model="temperature" :label="__('Temperature')" type="number" min="0" max="2" step="0.1" />
                            <flux:input wire:model="maxTokens" :label="__('Max Tokens')" type="number" min="1" max="32768" />
                        </div>

                        <flux:input wire:model="strategy" :label="__('Strategy')" :placeholder="__('Optional generation strategy hint')" />
                    </div>

                </div>

                {{-- Tab: Quality --}}
                <div x-show="tab === 'quality'" class="space-y-8">

                    {{-- Diversity & Quality --}}
                    <div class="space-y-4">
                        <flux:heading size="sm" class="text-neutral-500 uppercase tracking-wide">{{ __('Diversity & Quality') }}</flux:heading>

                        <div>
                            <flux:textarea wire:model="diversityDimensions" :label="__('Diversity Dimensions')" rows="5"
                                placeholder='{&#10;  "topic": ["shipping", "returns", "payment"],&#10;  "tone": ["friendly", "angry", "urgent"],&#10;  "complexity": ["simple", "edge_case"]&#10;}' />
                            <flux:text class="mt-1 text-sm text-neutral-500">
                                {{ __('Optional JSON object defining variation axes. The system distributes these across batches to avoid overrepresentation. Example dimensions: topic, tone, complexity, language, user_type.') }}
                            </flux:text>
                        </div>

                        <div>
                            <flux:select wire:model.live="uniquenessLevel" :label="__('Uniqueness Level')">
                                <flux:select.option value="conservative">{{ __('Conservative') }}</flux:select.option>
                                <flux:select.option value="balanced">{{ __('Balanced (Recommended)') }}</flux:select.option>
                                <flux:select.option value="aggressive">{{ __('Aggressive') }}</flux:select.option>
                            </flux:select>
                            <flux:text class="mt-1 text-sm text-neutral-500">
                                @if($uniquenessLevel === 'conservative')
                                    {{ __('Allows some similarity. Faster and cheaper. Good for large factual datasets.') }}
                                @elseif($uniquenessLevel === 'aggressive')
                                    {{ __('Strong duplicate prevention and higher novelty. Best for training data that requires maximum diversity.') }}
                                @else
                                    {{ __('Recommended. Good balance between diversity and realism. Works well for most use cases.') }}
                                @endif
                            </flux:text>
                        </div>

                        <div>
                            <flux:input wire:model="generationSeed" :label="__('Generation Seed')" :placeholder="__('e.g. my-dataset-v1')" />
                            <flux:text class="mt-1 text-sm text-neutral-500">
                                {{ __('Optional fixed seed for reproducible generations. Using the same seed with the same settings improves consistency across runs and enables benchmarking.') }}
                            </flux:text>
                        </div>
                    </div>

                    {{-- Deduplication --}}
                    <div class="space-y-4">
                        <flux:heading size="sm" class="text-neutral-500 uppercase tracking-wide">{{ __('Deduplication') }}</flux:heading>

                        <div>
                            <div class="flex items-center justify-between">
                                <flux:label>{{ __('Semantic Deduplication') }}</flux:label>
                                <flux:switch wire:model.live="semanticDeduplicationEnabled" />
                            </div>
                            <flux:text class="mt-1 text-sm text-neutral-500">
                                {{ __('Detect and reject rows that are semantically similar to existing rows, even if they use different wording.') }}
                            </flux:text>
                        </div>

                        @if($semanticDeduplicationEnabled)
                            <div>
                                <flux:input wire:model="semanticSimilarityThreshold" :label="__('Similarity Threshold')" type="number" min="0.75" max="0.99" step="0.01" />
                                <flux:text class="mt-1 text-sm text-neutral-500">
                                    {{ __('Between 0.75 and 0.99. Higher values are stricter and reject more rows as duplicates. 0.92 is a good default.') }}
                                </flux:text>
                            </div>
                        @endif

                        <div>
                            <div class="flex items-center justify-between">
                                <flux:label>{{ __('Replacement Generation') }}</flux:label>
                                <flux:switch wire:model="replacementGenerationEnabled" />
                            </div>
                            <flux:text class="mt-1 text-sm text-neutral-500">
                                {{ __('When duplicates are detected and rejected, automatically generate replacement rows to reach the target count. Up to 5 replacement attempts per batch.') }}
                            </flux:text>
                        </div>
                    </div>

                    {{-- Quality Evaluation --}}
                    <div class="space-y-4">
                        <flux:heading size="sm" class="text-neutral-500 uppercase tracking-wide">{{ __('Quality Evaluation') }}</flux:heading>

                        <div>
                            <div class="flex items-center justify-between">
                                <flux:label>{{ __('Enable Quality Evaluation') }}</flux:label>
                                <flux:switch wire:model.live="evaluationEnabled" />
                            </div>
                            <flux:text class="mt-1 text-sm text-neutral-500">
                                {{ __('After generation, each row is scored by an LLM judge on schema correctness, realism, diversity, instruction compliance, and training usefulness. Rows below the minimum score are rejected and replaced.') }}
                            </flux:text>
                        </div>

                        @if($evaluationEnabled)
                            <flux:select wire:model="evaluationAiProviderId" :label="__('Evaluator Provider')">
                                <flux:select.option value="">{{ __('Same as generation provider') }}</flux:select.option>
                                @foreach($providers as $provider)
                                    <flux:select.option :value="$provider->id">{{ $provider->label }}</flux:select.option>
                                @endforeach
                            </flux:select>

                            <div>
                                <flux:input wire:model="evaluationModel" :label="__('Evaluator Model')" :placeholder="__('e.g. gpt-4o-mini (defaults to generation model)')" />
                                <flux:text class="mt-1 text-sm text-neutral-500">
                                    {{ __('Model used for evaluation. Can be a cheaper/faster model than the generation model.') }}
                                </flux:text>
                            </div>

                            <div>
                                <flux:input wire:model="minimumQualityScore" :label="__('Minimum Quality Score')" type="number" min="0" max="100" />
                                <flux:text class="mt-1 text-sm text-neutral-500">
                                    {{ __('Rows scoring below this threshold (0–100) are rejected. Default is 75.') }}
                                </flux:text>
                            </div>
                        @endif
                    </div>

                </div>

                {{-- Tab: Pipeline --}}
                <div x-show="tab === 'pipeline'" class="space-y-8">

                    {{-- Generation Pipeline --}}
                    <div class="space-y-4">
                        <flux:heading size="sm" class="text-neutral-500 uppercase tracking-wide">{{ __('Generation Pipeline') }}</flux:heading>

                        <flux:text class="text-sm text-neutral-500">
                            {{ __('Optionally add a Critic and Refiner stage after generation. The Critic identifies weaknesses in each row; the Refiner rewrites rows flagged for improvement. Each stage can use a different provider and model.') }}
                        </flux:text>

                        <div>
                            <div class="flex items-center justify-between">
                                <flux:label>{{ __('Enable Critic Stage') }}</flux:label>
                                <flux:switch wire:model.live="criticEnabled" />
                            </div>
                            <flux:text class="mt-1 text-sm text-neutral-500">
                                {{ __('After generation, a critic model reviews each row and flags weak rows for refinement.') }}
                            </flux:text>
                        </div>

                        @if($criticEnabled)
                            <flux:select wire:model="criticAiProviderId" :label="__('Critic Provider')">
                                <flux:select.option value="">{{ __('Same as generation provider') }}</flux:select.option>
                                @foreach($providers as $provider)
                                    <flux:select.option :value="$provider->id">{{ $provider->label }}</flux:select.option>
                                @endforeach
                            </flux:select>

                            <div>
                                <flux:input wire:model="criticModel" :label="__('Critic Model')" :placeholder="__('e.g. gpt-4o-mini (defaults to generation model)')" />
                                <flux:text class="mt-1 text-sm text-neutral-500">
                                    {{ __('Model used for critique. Can be a cheaper/faster model.') }}
                                </flux:text>
                            </div>
                        @endif

                        <div>
                            <div class="flex items-center justify-between">
                                <flux:label>{{ __('Enable Refiner Stage') }}</flux:label>
                                <flux:switch wire:model.live="refinerEnabled" />
                            </div>
                            <flux:text class="mt-1 text-sm text-neutral-500">
                                {{ __('Rows flagged by the Critic are rewritten by the Refiner to address identified weaknesses. Requires Critic to be enabled.') }}
                            </flux:text>
                        </div>

                        @if($refinerEnabled)
                            <flux:select wire:model="refinerAiProviderId" :label="__('Refiner Provider')">
                                <flux:select.option value="">{{ __('Same as generation provider') }}</flux:select.option>
                                @foreach($providers as $provider)
                                    <flux:select.option :value="$provider->id">{{ $provider->label }}</flux:select.option>
                                @endforeach
                            </flux:select>

                            <div>
                                <flux:input wire:model="refinerModel" :label="__('Refiner Model')" :placeholder="__('e.g. gpt-4o (defaults to generation model)')" />
                                <flux:text class="mt-1 text-sm text-neutral-500">
                                    {{ __('Model used for refinement. Can be a more capable model than the generation model.') }}
                                </flux:text>
                            </div>
                        @endif
                    </div>

                    {{-- Negative Examples --}}
                    <div class="space-y-4">
                        <flux:heading size="sm" class="text-neutral-500 uppercase tracking-wide">{{ __('Negative Examples') }}</flux:heading>

                        <flux:text class="text-sm text-neutral-500">
                            {{ __('Intentionally incorrect examples improve agent robustness by teaching the model to recognise failure modes such as wrong tool selection, malformed arguments, hallucinated tools, and refusal scenarios.') }}
                        </flux:text>

                        <div>
                            <flux:input wire:model="negativeExampleRatio" :label="__('Negative Example Ratio (%)')" type="number" min="0" max="30" />
                            <flux:text class="mt-1 text-sm text-neutral-500">
                                {{ __('Percentage of each batch to generate as negative examples (0–30%). Set to 0 to disable. These rows are saved with expected_behavior=incorrect and a failure_reason label.') }}
                            </flux:text>
                        </div>
                    </div>

                </div>

                {{-- Tab: Conversations --}}
                <div x-show="tab === 'conversations'" class="space-y-4">
                    <flux:heading size="sm" class="text-neutral-500 uppercase tracking-wide">{{ __('Multi-Turn Conversations') }}</flux:heading>

                    <flux:text class="text-sm text-neutral-500">
                        {{ __('Generate conversational training datasets with multiple turns. Supports assistant chat, tool usage, support workflows, and multi-step reasoning. Each row will contain a "messages" array with role/content pairs.') }}
                    </flux:text>

                    <div>
                        <div class="flex items-center justify-between">
                            <flux:label>{{ __('Enable Conversation Mode') }}</flux:label>
                            <flux:switch wire:model.live="conversationEnabled" />
                        </div>
                        <flux:text class="mt-1 text-sm text-neutral-500">
                            {{ __('When enabled, the LLM generates multi-turn conversations instead of single structured rows.') }}
                        </flux:text>
                    </div>

                    @if($conversationEnabled)
                        <flux:select wire:model="conversationType" :label="__('Conversation Type')">
                            <flux:select.option value="assistant_chat">{{ __('Assistant Chat') }}</flux:select.option>
                            <flux:select.option value="tool_usage">{{ __('Tool Usage') }}</flux:select.option>
                            <flux:select.option value="support_workflow">{{ __('Support Workflow') }}</flux:select.option>
                            <flux:select.option value="multi_step_reasoning">{{ __('Multi-Step Reasoning') }}</flux:select.option>
                        </flux:select>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <flux:input wire:model="minTurns" :label="__('Min Turns')" type="number" min="1" max="20" />
                                <flux:text class="mt-1 text-sm text-neutral-500">{{ __('Minimum number of user/assistant turn pairs.') }}</flux:text>
                            </div>
                            <div>
                                <flux:input wire:model="maxTurns" :label="__('Max Turns')" type="number" min="1" max="20" />
                                <flux:text class="mt-1 text-sm text-neutral-500">{{ __('Maximum number of user/assistant turn pairs.') }}</flux:text>
                            </div>
                        </div>

                        <div>
                            <div class="flex items-center justify-between">
                                <flux:label>{{ __('Enable Branching') }}</flux:label>
                                <flux:switch wire:model="branchingEnabled" />
                            </div>
                            <flux:text class="mt-1 text-sm text-neutral-500">
                                {{ __('Vary conversation paths with clarifications, corrections, and topic shifts for more diverse training data.') }}
                            </flux:text>
                        </div>
                    @endif
                </div>

                {{-- Tab: Augmentation --}}
                <div x-show="tab === 'augmentation'" class="space-y-4">
                    <flux:heading size="sm" class="text-neutral-500 uppercase tracking-wide">{{ __('Source Dataset Augmentation') }}</flux:heading>

                    <flux:text class="text-sm text-neutral-500">{{ __('Upload real examples and generate high-quality synthetic variations.') }}</flux:text>

                    {{-- Source file upload --}}
                    <div class="rounded-lg border border-neutral-200 p-4 dark:border-neutral-700">
                        <flux:heading size="xs" class="mb-3">{{ __('Source Files') }}</flux:heading>

                        @if ($sources->isNotEmpty())
                            <ul class="mb-4 divide-y divide-neutral-100 dark:divide-neutral-800">
                                @foreach ($sources as $source)
                                    <li class="flex items-center justify-between py-2 text-sm">
                                        <div class="flex items-center gap-2 min-w-0">
                                            <flux:badge size="sm" color="zinc">{{ strtoupper($source->source_type) }}</flux:badge>
                                            <span class="truncate text-neutral-700 dark:text-neutral-300">{{ $source->original_filename }}</span>
                                            <span class="shrink-0 text-neutral-400">{{ number_format($source->row_count) }} {{ __('rows') }}</span>
                                        </div>
                                        <flux:button wire:click="deleteSource({{ $source->id }})" wire:confirm="{{ __('Remove this source file?') }}" size="sm" variant="ghost" icon="trash" class="text-red-500 hover:text-red-600" />
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <flux:text class="mb-4 text-sm text-neutral-400">{{ __('No source files uploaded yet.') }}</flux:text>
                        @endif

                        @if ($project)
                            <div class="flex items-end gap-3">
                                <div class="flex-1">
                                    <flux:input wire:model="sourceFile" type="file" accept=".json,.jsonl,.csv,.txt,.md,.xlsx" :label="__('Upload Source File')" />
                                    <flux:text class="mt-1 text-xs text-neutral-400">{{ __('Supported: JSON, JSONL, CSV, TXT, Markdown, Excel. Max 50 MB.') }}</flux:text>
                                </div>
                                <flux:button wire:click="uploadSource" variant="filled" size="sm" wire:loading.attr="disabled">
                                    {{ __('Upload') }}
                                </flux:button>
                            </div>
                        @else
                            <flux:text class="text-sm text-amber-600 dark:text-amber-400">{{ __('Save the dataset project first to enable file uploads.') }}</flux:text>
                        @endif
                    </div>

                    <div>
                        <div class="flex items-center justify-between">
                            <flux:label>{{ __('Enable Augmentation') }}</flux:label>
                            <flux:switch wire:model.live="augmentationEnabled" />
                        </div>
                        <flux:text class="mt-1 text-sm text-neutral-500">
                            {{ __('When enabled, generation will use uploaded source examples as grounding for synthetic variations.') }}
                        </flux:text>
                    </div>

                    @if ($augmentationEnabled)
                        <div>
                            <flux:select wire:model="augmentationMode" :label="__('Augmentation Mode')">
                                <flux:select.option value="similar">{{ __('Similar — Realistic variants close to originals') }}</flux:select.option>
                                <flux:select.option value="diverse">{{ __('Diverse — Broader scenarios inspired by originals') }}</flux:select.option>
                                <flux:select.option value="edge_cases">{{ __('Edge Cases — Difficult and unusual scenarios') }}</flux:select.option>
                                <flux:select.option value="adversarial">{{ __('Adversarial — Failure and attack scenarios') }}</flux:select.option>
                            </flux:select>
                        </div>

                        <div>
                            <flux:input wire:model="augmentationStrength" :label="__('Augmentation Strength')" type="number" min="0.1" max="1.0" step="0.1" />
                            <flux:text class="mt-1 text-sm text-neutral-500">{{ __('Controls how much generated examples differ from uploaded data. 0.1 = very close, 1.0 = strong novelty.') }}</flux:text>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <flux:input wire:model="augmentationTargetCount" :label="__('Target Count')" type="number" min="1" placeholder="{{ __('e.g. 10000') }}" />
                                <flux:text class="mt-1 text-sm text-neutral-500">{{ __('Total rows to generate (overrides record count).') }}</flux:text>
                            </div>
                            <div>
                                <flux:input wire:model="augmentationExpandPercent" :label="__('Expand By %')" type="number" min="1" max="10000" placeholder="{{ __('e.g. 500') }}" />
                                <flux:text class="mt-1 text-sm text-neutral-500">{{ __('Generate X% more rows than source examples (e.g. 500 = 5×).') }}</flux:text>
                            </div>
                        </div>
                    @endif
                </div>

                {{-- Submit --}}
                <div class="mt-8 flex items-center gap-4">
                    <flux:button type="submit" variant="primary">
                        {{ $project ? __('Update Dataset') : __('Create Dataset') }}
                    </flux:button>
                    <flux:button :href="route('datasets.index')" wire:navigate variant="ghost">
                        {{ __('Cancel') }}
                    </flux:button>
                </div>

            </div>
        </form>
    </div>
</div>
