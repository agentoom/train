<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ $project->name }}</flux:heading>
            <flux:text class="mt-1 text-neutral-500">{{ $project->description }}</flux:text>
        </div>
        <div class="flex items-center gap-2">
            <livewire:components.status-badge :status="$project->status->value" />
            @if($isCompleted)
                <flux:dropdown>
                    <flux:button variant="primary" icon="arrow-down-tray" icon-trailing="chevron-down">
                        {{ __('Export') }}
                    </flux:button>
                    <flux:menu>
                        <flux:menu.group heading="{{ __('Raw Formats') }}">
                            <flux:menu.item :href="route('datasets.export', [$project, 'format' => 'json'])" icon="document-text">
                                {{ __('JSON') }}
                            </flux:menu.item>
                            <flux:menu.item :href="route('datasets.export', [$project, 'format' => 'jsonl'])" icon="document-text">
                                {{ __('JSONL') }}
                            </flux:menu.item>
                            <flux:menu.item :href="route('datasets.export', [$project, 'format' => 'csv'])" icon="table-cells">
                                {{ __('CSV') }}
                            </flux:menu.item>
                        </flux:menu.group>
                        <flux:menu.group heading="{{ __('Fine-Tuning Presets') }}">
                            <flux:menu.item :href="route('datasets.export', [$project, 'format' => 'openai'])" icon="sparkles">
                                {{ __('OpenAI Fine-Tuning') }}
                            </flux:menu.item>
                            <flux:menu.item :href="route('datasets.export', [$project, 'format' => 'anthropic'])" icon="sparkles">
                                {{ __('Anthropic') }}
                            </flux:menu.item>
                            <flux:menu.item :href="route('datasets.export', [$project, 'format' => 'huggingface'])" icon="sparkles">
                                {{ __('HuggingFace') }}
                            </flux:menu.item>
                            <flux:menu.item :href="route('datasets.export', [$project, 'format' => 'axolotl'])" icon="sparkles">
                                {{ __('Axolotl') }}
                            </flux:menu.item>
                            <flux:menu.item :href="route('datasets.export', [$project, 'format' => 'unsloth'])" icon="sparkles">
                                {{ __('Unsloth') }}
                            </flux:menu.item>
                            <flux:menu.item :href="route('datasets.export', [$project, 'format' => 'llamafactory'])" icon="sparkles">
                                {{ __('LlamaFactory') }}
                            </flux:menu.item>
                            <flux:menu.item :href="route('datasets.export', [$project, 'format' => 'tool-calling'])" icon="wrench">
                                {{ __('Generic Tool Calling') }}
                            </flux:menu.item>
                        </flux:menu.group>
                    </flux:menu>
                </flux:dropdown>
            @endif
            @if(in_array($project->status->value, ['draft', 'completed', 'failed', 'cancelled']))
                @if($canContinue)
                    <flux:button wire:click="continueGeneration" variant="primary" icon="play">
                        {{ __('Continue Generation') }}
                    </flux:button>
                @endif

                <flux:button wire:click="startGeneration" variant="{{ $canContinue ? 'outline' : 'primary' }}" icon="arrow-path">
                    {{ $latestVersion ? __('Start New Generation') : __('Start Generation') }}
                </flux:button>

                <flux:dropdown>
                    <flux:button variant="ghost" icon="ellipsis-horizontal" inset="top bottom" />
                    <flux:menu>
                        <flux:modal.trigger name="erase-history-modal">
                            <flux:menu.item variant="danger" icon="trash">
                                {{ __('Erase History') }}
                            </flux:menu.item>
                        </flux:modal.trigger>
                    </flux:menu>
                </flux:dropdown>
            @endif
            @if(in_array($project->status->value, ['queued', 'running']))
                <flux:button wire:click="cancelGeneration" variant="danger" icon="x-mark">
                    {{ __('Cancel') }}
                </flux:button>
            @endif
            <flux:button :href="route('datasets.edit', $project)" wire:navigate variant="ghost" icon="pencil">
                {{ __('Edit') }}
            </flux:button>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
        <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
            <flux:text class="text-sm text-neutral-500">{{ __('Records') }}</flux:text>
            <flux:heading size="lg">{{ number_format($project->record_count) }}</flux:heading>
        </div>
        <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
            <flux:text class="text-sm text-neutral-500">{{ __('Model') }}</flux:text>
            <flux:heading size="lg">{{ $project->model ?? '—' }}</flux:heading>
        </div>
        <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
            <flux:text class="text-sm text-neutral-500">{{ __('Batches') }}</flux:text>
            <flux:heading size="lg">{{ $completedBatches }} / {{ $totalBatches }}</flux:heading>
        </div>
        <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
            <flux:text class="text-sm text-neutral-500">{{ __('Failed') }}</flux:text>
            <flux:heading size="lg" class="{{ $failedBatches > 0 ? 'text-red-500' : '' }}">{{ $failedBatches }}</flux:heading>
        </div>
    </div>

    @if($latestVersion && $totalBatches > 0)
        <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
            <flux:heading size="sm" class="mb-3">{{ __('Generation Progress') }}</flux:heading>
            <livewire:components.progress-bar :total="$totalBatches" :completed="$completedBatches" label="Batches completed" />
        </div>

        <div class="flex items-center justify-end">
            <flux:field variant="inline">
                <flux:label size="sm">{{ __('Jobs per card') }}</flux:label>
                <flux:select wire:model.live="perPage" size="sm" class="w-20">
                    <option value="5">5</option>
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                </flux:select>
            </flux:field>
        </div>

        <div class="grid grid-cols-1 gap-6 md:grid-cols-4">
            {{-- Pending Jobs --}}
            <div class="flex flex-col gap-4 rounded-xl border border-neutral-200 bg-neutral-50/30 p-4 dark:border-neutral-700 dark:bg-neutral-900/10">
                <div class="flex items-center justify-between">
                    <flux:heading size="sm" class="flex items-center gap-2">
                        <div class="h-2 w-2 rounded-full bg-neutral-400"></div>
                        {{ __('Pending') }}
                    </flux:heading>
                    <flux:badge size="sm" variant="outline" color="neutral">{{ $pendingBatches->total() }}</flux:badge>
                </div>

                <div class="flex flex-col gap-3">
                    @forelse($pendingBatches as $batch)
                        <div class="rounded-lg border border-neutral-200 p-3 dark:border-neutral-700 bg-white dark:bg-neutral-900/50">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm font-medium">#{{ $batch->batch_number }}</span>
                                <span class="text-xs text-neutral-500">{{ $batch->limit }} {{ __('rows') }}</span>
                            </div>
                            <div class="text-xs text-neutral-500">
                                {{ __('Offset') }}: {{ $batch->offset }}
                            </div>
                        </div>
                    @empty
                        <div class="flex flex-col items-center justify-center rounded-lg border border-dashed border-neutral-200 p-6 text-center dark:border-neutral-800">
                            <flux:text class="text-xs text-neutral-500">{{ __('No pending jobs') }}</flux:text>
                        </div>
                    @endforelse
                </div>

                @if($pendingBatches->hasPages())
                    <div class="mt-auto pt-4">
                        {{ $pendingBatches->links('livewire::simple-tailwind', ['pageName' => 'pendingPage']) }}
                    </div>
                @endif
            </div>

            {{-- Running Jobs --}}
            <div class="flex flex-col gap-4 rounded-xl border border-neutral-200 bg-neutral-50/30 p-4 dark:border-neutral-700 dark:bg-neutral-900/10">
                <div class="flex items-center justify-between">
                    <flux:heading size="sm" class="flex items-center gap-2">
                        <div class="h-2 w-2 rounded-full bg-blue-500 animate-pulse"></div>
                        {{ __('Running') }}
                    </flux:heading>
                    <flux:badge size="sm" variant="outline" color="blue">{{ $runningBatches->total() }}</flux:badge>
                </div>

                <div class="flex flex-col gap-3">
                    @forelse($runningBatches as $batch)
                        <div class="rounded-lg border border-blue-200 p-3 dark:border-blue-900/30 bg-blue-50/30 dark:bg-blue-900/10">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm font-medium">#{{ $batch->batch_number }}</span>
                                <flux:icon.arrow-path class="size-3 animate-spin text-blue-500" />
                            </div>
                            <div class="text-xs text-neutral-500">
                                {{ __('Offset') }}: {{ $batch->offset }} • {{ $batch->limit }} {{ __('rows') }}
                            </div>
                        </div>
                    @empty
                        <div class="flex flex-col items-center justify-center rounded-lg border border-dashed border-neutral-200 p-6 text-center dark:border-neutral-800">
                            <flux:text class="text-xs text-neutral-500">{{ __('No running jobs') }}</flux:text>
                        </div>
                    @endforelse
                </div>

                @if($runningBatches->hasPages())
                    <div class="mt-auto pt-4">
                        {{ $runningBatches->links('livewire::simple-tailwind', ['pageName' => 'runningPage']) }}
                    </div>
                @endif
            </div>

            {{-- Failed Jobs --}}
            <div class="flex flex-col gap-4 rounded-xl border border-neutral-200 bg-neutral-50/30 p-4 dark:border-neutral-700 dark:bg-neutral-900/10">
                <div class="flex items-center justify-between">
                    <flux:heading size="sm" class="flex items-center gap-2">
                        <div class="h-2 w-2 rounded-full bg-red-500"></div>
                        {{ __('Failed') }}
                    </flux:heading>
                    <flux:badge size="sm" variant="outline" color="red">{{ $failedBatchesList->total() }}</flux:badge>
                </div>

                <div class="flex flex-col gap-3">
                    @forelse($failedBatchesList as $batch)
                        <div class="rounded-lg border border-red-200 p-3 dark:border-red-900/30 bg-red-50/30 dark:bg-red-900/10">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm font-medium">#{{ $batch->batch_number }}</span>
                                <div class="flex items-center gap-1">
                                    @if($batch->logs_count > 0)
                                        <flux:button wire:click="showLog({{ $batch->id }})" size="xs" variant="ghost" icon="eye" />
                                    @endif
                                    <flux:button wire:click="retryBatch({{ $batch->id }})" size="xs" variant="ghost" icon="arrow-path" />
                                </div>
                            </div>
                            <div class="text-xs text-red-600 dark:text-red-400 truncate mb-1">
                                {{ $batch->error_message ?? __('Unknown error') }}
                            </div>
                            <div class="text-xs text-neutral-500">
                                {{ __('Offset') }}: {{ $batch->offset }}
                            </div>
                        </div>
                    @empty
                        <div class="flex flex-col items-center justify-center rounded-lg border border-dashed border-neutral-200 p-6 text-center dark:border-neutral-800">
                            <flux:text class="text-xs text-neutral-500">{{ __('No failed jobs') }}</flux:text>
                        </div>
                    @endforelse
                </div>

                @if($failedBatchesList->hasPages())
                    <div class="mt-auto pt-4">
                        {{ $failedBatchesList->links('livewire::simple-tailwind', ['pageName' => 'failedPage']) }}
                    </div>
                @endif
            </div>

            {{-- Completed Jobs --}}
            <div class="flex flex-col gap-4 rounded-xl border border-neutral-200 bg-neutral-50/30 p-4 dark:border-neutral-700 dark:bg-neutral-900/10">
                <div class="flex items-center justify-between">
                    <flux:heading size="sm" class="flex items-center gap-2">
                        <div class="h-2 w-2 rounded-full bg-green-500"></div>
                        {{ __('Completed') }}
                    </flux:heading>
                    <div class="flex items-center gap-2">
                        <select wire:model.live="completedFilter" class="bg-transparent text-xs border-none focus:ring-0 p-0 text-neutral-500 dark:text-neutral-400">
                            <option value="all">{{ __('All') }}</option>
                            <option value="full">{{ __('100%') }}</option>
                            <option value="partial">{{ __('Partial') }}</option>
                        </select>
                        <flux:badge size="sm" variant="outline" color="green">{{ $completedBatchesList->total() }}</flux:badge>
                    </div>
                </div>

                <div class="flex flex-col gap-3">
                    @forelse($completedBatchesList as $batch)
                        <div class="rounded-lg border border-neutral-200 p-3 dark:border-neutral-700 bg-white dark:bg-neutral-900/50">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm font-medium">#{{ $batch->batch_number }}</span>
                                <div class="flex items-center gap-1">
                                    @if($batch->partially_completed)
                                        <flux:badge size="xs" color="amber">{{ __('Partial') }}</flux:badge>
                                    @endif
                                    @if($batch->logs_count > 0)
                                        <flux:button wire:click="showLog({{ $batch->id }})" size="xs" variant="ghost" icon="eye" />
                                    @endif
                                </div>
                            </div>
                            <div class="flex items-center justify-between text-xs text-neutral-500">
                                <span>{{ number_format($batch->tokens_used) }} {{ __('tokens') }}</span>
                                @if($batch->duplicates_detected > 0 || $batch->regenerated > 0)
                                    <span class="text-amber-500">
                                        @if($batch->duplicates_detected > 0)
                                            {{ $batch->duplicates_detected }} {{ __('dupes skipped') }}
                                        @endif
                                        @if($batch->regenerated > 0)
                                    &bull; {{ $batch->regenerated }} {{ __('regenerated') }}
                                        @endif
                                    </span>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="flex flex-col items-center justify-center rounded-lg border border-dashed border-neutral-200 p-6 text-center dark:border-neutral-800">
                            <flux:text class="text-xs text-neutral-500">{{ __('No completed jobs') }}</flux:text>
                        </div>
                    @endforelse
                </div>

                @if($completedBatchesList->hasPages())
                    <div class="mt-auto pt-4">
                        {{ $completedBatchesList->links('livewire::simple-tailwind', ['pageName' => 'completedPage']) }}
                    </div>
                @endif
            </div>
        </div>
    @else
        <div class="flex flex-col items-center justify-center rounded-xl border border-dashed border-neutral-200 p-12 text-center dark:border-neutral-700">
            <flux:icon.circle-stack class="mb-4 size-12 text-neutral-400" />
            <flux:heading size="lg">{{ __('No generation started yet') }}</flux:heading>
            <flux:text class="mt-2 text-neutral-500">{{ __('Click "Start Generation" to begin generating dataset rows.') }}</flux:text>
        </div>
    @endif

    @if(!empty($qualityMetrics))
        <div class="rounded-xl border border-neutral-200 dark:border-neutral-700">
            <div class="border-b border-neutral-200 p-4 dark:border-neutral-700">
                <flux:heading size="sm">{{ __('Quality Metrics') }}</flux:heading>
            </div>
            <div class="grid grid-cols-2 gap-0 sm:grid-cols-3 lg:grid-cols-6">
                <div class="border-b border-r border-neutral-100 p-4 dark:border-neutral-800">
                    <flux:text class="text-xs text-neutral-500">{{ __('Unique Rows') }}</flux:text>
                    <flux:heading size="lg">{{ number_format($qualityMetrics['unique_rows']) }}</flux:heading>
                </div>
                <div class="border-b border-r border-neutral-100 p-4 dark:border-neutral-800">
                    <flux:text class="text-xs text-neutral-500" title="{{ __('Rows flagged as hash or semantic duplicates and discarded') }}">{{ __('Duplicates Skipped') }}</flux:text>
                    <flux:heading size="lg" class="{{ $qualityMetrics['duplicate_count'] > 0 ? 'text-amber-500' : '' }}">
                        {{ number_format($qualityMetrics['duplicate_count']) }}
                    </flux:heading>
                </div>
                <div class="border-b border-r border-neutral-100 p-4 dark:border-neutral-800">
                    <flux:text class="text-xs text-neutral-500" title="{{ __('Rows saved during extra generation attempts to fill gaps left by duplicates or short responses') }}">{{ __('Regenerated') }}</flux:text>
                    <flux:heading size="lg">{{ number_format($qualityMetrics['regenerated']) }}</flux:heading>
                </div>
                <div class="border-b border-r border-neutral-100 p-4 dark:border-neutral-800">
                    <flux:text class="text-xs text-neutral-500">{{ __('Uniqueness') }}</flux:text>
                    <flux:heading size="lg" class="{{ $qualityMetrics['uniqueness_score'] >= 90 ? 'text-green-500' : 'text-amber-500' }}">
                        {{ $qualityMetrics['uniqueness_score'] }}%
                    </flux:heading>
                </div>
                <div class="border-b border-r border-neutral-100 p-4 dark:border-neutral-800">
                    <flux:text class="text-xs text-neutral-500">{{ __('Validity') }}</flux:text>
                    <flux:heading size="lg" class="{{ $qualityMetrics['validity_score'] >= 90 ? 'text-green-500' : 'text-amber-500' }}">
                        {{ $qualityMetrics['validity_score'] }}%
                    </flux:heading>
                </div>
                <div class="border-b border-neutral-100 p-4 dark:border-neutral-800">
                    <flux:text class="text-xs text-neutral-500">{{ __('Coverage') }}</flux:text>
                    <flux:heading size="lg" class="{{ $qualityMetrics['coverage_score'] >= 90 ? 'text-green-500' : 'text-amber-500' }}">
                        {{ $qualityMetrics['coverage_score'] }}%
                    </flux:heading>
                </div>
            </div>

            @if(($qualityMetrics['criticised_rows'] ?? 0) > 0)
                <div class="border-t border-neutral-100 dark:border-neutral-800">
                    <div class="border-b border-neutral-100 p-4 dark:border-neutral-800">
                        <flux:heading size="sm" class="text-neutral-500">{{ __('Generation Pipeline') }}</flux:heading>
                    </div>
                    <div class="grid grid-cols-2 gap-0">
                        <div class="border-r border-neutral-100 p-4 dark:border-neutral-800">
                            <flux:text class="text-xs text-neutral-500" title="{{ __('Rows reviewed by the Critic stage') }}">{{ __('Critiqued Rows') }}</flux:text>
                            <flux:heading size="lg">{{ number_format($qualityMetrics['criticised_rows']) }}</flux:heading>
                        </div>
                        <div class="p-4">
                            <flux:text class="text-xs text-neutral-500" title="{{ __('Rows rewritten by the Refiner stage after Critic flagged them') }}">{{ __('Refined Rows') }}</flux:text>
                            <flux:heading size="lg" class="{{ $qualityMetrics['refined_rows'] > 0 ? 'text-violet-500' : '' }}">
                                {{ number_format($qualityMetrics['refined_rows']) }}
                            </flux:heading>
                        </div>
                    </div>
                </div>
            @endif

            @if($qualityMetrics['evaluated_rows'] > 0)
                <div class="border-t border-neutral-100 dark:border-neutral-800">
                    <div class="border-b border-neutral-100 p-4 dark:border-neutral-800">
                        <flux:heading size="sm" class="text-neutral-500">{{ __('LLM Evaluation') }}</flux:heading>
                    </div>
                    <div class="grid grid-cols-2 gap-0 sm:grid-cols-4">
                        <div class="border-r border-neutral-100 p-4 dark:border-neutral-800">
                            <flux:text class="text-xs text-neutral-500" title="{{ __('Average quality score across all evaluated rows (0–100)') }}">{{ __('Avg Quality Score') }}</flux:text>
                            <flux:heading size="lg" class="{{ ($qualityMetrics['avg_quality_score'] ?? 0) >= 75 ? 'text-green-500' : 'text-amber-500' }}">
                                {{ $qualityMetrics['avg_quality_score'] !== null ? $qualityMetrics['avg_quality_score'] : '—' }}
                            </flux:heading>
                        </div>
                        <div class="border-r border-neutral-100 p-4 dark:border-neutral-800">
                            <flux:text class="text-xs text-neutral-500">{{ __('Evaluated Rows') }}</flux:text>
                            <flux:heading size="lg">{{ number_format($qualityMetrics['evaluated_rows']) }}</flux:heading>
                        </div>
                        <div class="border-r border-neutral-100 p-4 dark:border-neutral-800">
                            <flux:text class="text-xs text-neutral-500" title="{{ __('Rows rejected because their quality score was below the minimum threshold') }}">{{ __('Rejected') }}</flux:text>
                            <flux:heading size="lg" class="{{ $qualityMetrics['evaluation_rejected'] > 0 ? 'text-amber-500' : '' }}">
                                {{ number_format($qualityMetrics['evaluation_rejected']) }}
                            </flux:heading>
                        </div>
                        <div class="p-4">
                            <flux:text class="text-xs text-neutral-500" title="{{ __('Rows where the evaluator itself failed (e.g. API error or parse failure)') }}">{{ __('Eval Failures') }}</flux:text>
                            <flux:heading size="lg" class="{{ $qualityMetrics['evaluation_errors'] > 0 ? 'text-red-500' : '' }}">
                                {{ number_format($qualityMetrics['evaluation_errors']) }}
                            </flux:heading>
                        </div>
                    </div>
                </div>
            @endif

            @if(($qualityMetrics['negative_examples'] ?? 0) > 0)
                <div class="border-t border-neutral-100 dark:border-neutral-800">
                    <div class="border-b border-neutral-100 p-4 dark:border-neutral-800">
                        <flux:heading size="sm" class="text-neutral-500">{{ __('Negative Examples') }}</flux:heading>
                    </div>
                    <div class="grid grid-cols-2 gap-0 sm:grid-cols-3">
                        <div class="border-r border-neutral-100 p-4 dark:border-neutral-800">
                            <flux:text class="text-xs text-neutral-500" title="{{ __('Total intentionally incorrect rows generated for robustness training') }}">{{ __('Total Negatives') }}</flux:text>
                            <flux:heading size="lg" class="text-orange-500">{{ number_format($qualityMetrics['negative_examples']) }}</flux:heading>
                        </div>
                        <div class="border-r border-neutral-100 p-4 dark:border-neutral-800 col-span-2">
                            <flux:text class="text-xs text-neutral-500 mb-2">{{ __('By Failure Type') }}</flux:text>
                            <div class="flex flex-wrap gap-1">
                                @foreach($qualityMetrics['negative_by_type'] as $type => $count)
                                    <flux:badge size="sm" color="orange">{{ str_replace('_', ' ', $type) }}: {{ $count }}</flux:badge>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            @if(($qualityMetrics['conversation_rows'] ?? 0) > 0)
                <div class="border-t border-neutral-100 dark:border-neutral-800">
                    <div class="border-b border-neutral-100 p-4 dark:border-neutral-800">
                        <flux:heading size="sm" class="text-neutral-500">{{ __('Conversations') }}</flux:heading>
                    </div>
                    <div class="grid grid-cols-2 gap-0">
                        <div class="border-r border-neutral-100 p-4 dark:border-neutral-800">
                            <flux:text class="text-xs text-neutral-500" title="{{ __('Total conversation rows generated') }}">{{ __('Conversations') }}</flux:text>
                            <flux:heading size="lg" class="text-sky-500">{{ number_format($qualityMetrics['conversation_rows']) }}</flux:heading>
                        </div>
                        <div class="p-4">
                            <flux:text class="text-xs text-neutral-500" title="{{ __('Average number of user turns per conversation') }}">{{ __('Avg Turns') }}</flux:text>
                            <flux:heading size="lg">{{ $qualityMetrics['avg_turn_count'] ?? '—' }}</flux:heading>
                        </div>
                    </div>
                </div>
            @endif

            @if(($qualityMetrics['source_count'] ?? 0) > 0)
                <div class="border-t border-neutral-100 dark:border-neutral-800">
                    <div class="border-b border-neutral-100 p-4 dark:border-neutral-800">
                        <flux:heading size="sm" class="text-neutral-500">{{ __('Augmentation') }}</flux:heading>
                    </div>
                    <div class="grid grid-cols-2 gap-0">
                        <div class="border-r border-neutral-100 p-4 dark:border-neutral-800">
                            <flux:text class="text-xs text-neutral-500" title="{{ __('Number of uploaded source files') }}">{{ __('Source Files') }}</flux:text>
                            <flux:heading size="lg" class="text-teal-500">{{ number_format($qualityMetrics['source_count']) }}</flux:heading>
                        </div>
                        <div class="p-4">
                            <flux:text class="text-xs text-neutral-500" title="{{ __('Valid rows generated from source augmentation') }}">{{ __('Augmented Rows') }}</flux:text>
                            <flux:heading size="lg" class="text-teal-500">{{ number_format($qualityMetrics['augmented_rows']) }}</flux:heading>
                        </div>
                    </div>
                    <div class="border-t border-neutral-100 p-4 dark:border-neutral-800">
                        <flux:text class="text-xs text-neutral-500">{{ __('Mode') }}: <span class="font-medium text-neutral-700 dark:text-neutral-300">{{ ucwords(str_replace('_', ' ', $project->augmentation_mode ?? 'similar')) }}</span></flux:text>
                        <flux:text class="text-xs text-neutral-500 mt-1">{{ __('Strength') }}: <span class="font-medium text-neutral-700 dark:text-neutral-300">{{ $project->augmentation_strength ?? 0.5 }}</span></flux:text>
                    </div>
                </div>
            @endif
        </div>
    @endif

    @if($previewRows->isNotEmpty())
        <div class="rounded-xl border border-neutral-200 dark:border-neutral-700">
            <div class="flex items-center justify-between border-b border-neutral-200 p-4 dark:border-neutral-700">
                <div>
                    <flux:heading size="sm">{{ __('Data Preview') }}</flux:heading>
                    <flux:text class="text-sm text-neutral-500">
                        {{ __('Showing :from–:to of :total rows (newest first)', ['from' => $previewRows->firstItem() ?? 0, 'to' => $previewRows->lastItem() ?? 0, 'total' => number_format($totalRows)]) }}
                    </flux:text>
                </div>
            </div>
            <div class="divide-y divide-neutral-100 dark:divide-neutral-800">
                @foreach($previewRows as $row)
                    <div class="p-4">
                        <div class="mb-1 flex items-center gap-2 flex-wrap">
                            <flux:badge size="sm" variant="outline">#{{ $row->row_index + 1 }}</flux:badge>
                            @if(!$row->is_valid)
                                <flux:badge size="sm" color="red">{{ __('Invalid') }}</flux:badge>
                            @endif
                            @if($row->refined_by)
                                <flux:badge size="sm" color="violet" :title="__('Refined by :model', ['model' => $row->refined_by])">{{ __('Refined') }}</flux:badge>
                            @elseif($row->critic_feedback)
                                <flux:badge size="sm" color="blue" :title="$row->critic_feedback">{{ __('Critiqued') }}</flux:badge>
                            @endif
                            @if($row->quality_score !== null)
                                <flux:badge size="sm" :color="$row->quality_score >= 75 ? 'green' : 'amber'" :title="$row->quality_reasoning">{{ __('Score: :score', ['score' => $row->quality_score]) }}</flux:badge>
                            @endif
                            @if($row->expected_behavior === 'incorrect')
                                <flux:badge size="sm" color="orange" :title="$row->failure_reason ? str_replace('_', ' ', $row->failure_reason) : ''">
                                    {{ __('Negative') }}{{ $row->failure_reason ? ': '.str_replace('_', ' ', $row->failure_reason) : '' }}
                                </flux:badge>
                            @endif
                        </div>
                        @if($row->turn_count !== null)
                            <flux:badge size="sm" color="sky" class="mb-1">{{ __(':n turns', ['n' => $row->turn_count]) }}</flux:badge>
                        @endif
                        @if($row->critic_feedback)
                            <flux:text class="mb-2 text-xs text-neutral-500 italic">{{ __('Critic: ') }}{{ $row->critic_feedback }}</flux:text>
                        @endif
                        @if(!empty($row->messages))
                            <div class="mt-2 space-y-2">
                                @foreach($row->messages as $message)
                                    @php $role = $message['role'] ?? 'unknown'; @endphp
                                    <div @class([
                                        'flex',
                                        'justify-end' => $role === 'user',
                                        'justify-start' => $role !== 'user',
                                    ])>
                                        <div @class([
                                            'max-w-[85%] rounded-lg px-3 py-2 text-sm',
                                            'bg-blue-500 text-white' => $role === 'user',
                                            'bg-neutral-100 text-neutral-900 dark:bg-neutral-800 dark:text-neutral-100' => $role === 'assistant',
                                            'bg-amber-50 text-amber-900 dark:bg-amber-900/20 dark:text-amber-200 font-mono text-xs' => $role === 'tool_result',
                                            'bg-violet-50 text-violet-900 dark:bg-violet-900/20 dark:text-violet-200' => $role === 'tool',
                                        ])>
                                            <div class="mb-1 text-[10px] font-semibold uppercase opacity-60">{{ $role }}</div>
                                            @if(isset($message['tool_call']))
                                                <div class="font-mono text-xs opacity-80">{{ json_encode($message['tool_call']) }}</div>
                                            @endif
                                            <div class="whitespace-pre-wrap">{{ $message['content'] ?? '' }}</div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <livewire:components.json-preview :json="json_encode($row->payload)" :key="'row-'.$row->row_index" />
                        @endif
                    </div>
                @endforeach
            </div>
            @if($previewRows->hasPages())
                <div class="border-t border-neutral-200 px-4 py-3 dark:border-neutral-700">
                    {{ $previewRows->links() }}
                </div>
            @endif
        </div>
    @endif

    <flux:modal name="batch-log-modal" wire:model="showLogModal" class="md:max-w-2xl">
        <div class="space-y-6">
            @if($selectedBatchForLog)
                <div>
                    <flux:heading size="lg">{{ __('Batch Logs') }} #{{ $selectedBatchForLog->batch_number }}</flux:heading>
                    <flux:text>{{ __('Detailed information about this generation batch.') }}</flux:text>
                </div>

                <div class="space-y-4">
                    @foreach($selectedBatchForLog->logs->sortByDesc('created_at') as $log)
                        <div class="rounded-lg border border-neutral-200 p-4 dark:border-neutral-700">
                            <div class="mb-2 flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    @if($log->level === 'error')
                                        <flux:badge size="sm" color="red">{{ __('Error') }}</flux:badge>
                                    @elseif($log->level === 'warning')
                                        <flux:badge size="sm" color="amber">{{ __('Warning') }}</flux:badge>
                                    @else
                                        <flux:badge size="sm" color="blue">{{ __('Info') }}</flux:badge>
                                    @endif
                                    <flux:text class="text-sm font-medium">{{ $log->message }}</flux:text>
                                </div>
                                <flux:text class="text-xs text-neutral-500">{{ $log->created_at->diffForHumans() }}</flux:text>
                            </div>

                            @if($log->details)
                                <div class="mt-4 rounded bg-neutral-50 p-3 dark:bg-neutral-800">
                                    <div class="grid grid-cols-2 gap-4 text-sm">
                                        @foreach($log->details as $key => $value)
                                            @if($key !== 'trace')
                                                <div>
                                                    <span class="font-medium text-neutral-500">{{ ucfirst(str_replace('_', ' ', $key)) }}:</span>
                                                    <span class="text-neutral-900 dark:text-neutral-100">{{ is_array($value) ? json_encode($value) : $value }}</span>
                                                </div>
                                            @endif
                                        @endforeach
                                    </div>
                                    @if(isset($log->details['error']))
                                        <div class="mt-3">
                                            <span class="font-medium text-neutral-500">{{ __('Error Message') }}:</span>
                                            <div class="mt-1 text-red-600 dark:text-red-400 font-mono text-xs break-all">
                                                {{ $log->details['error'] }}
                                            </div>
                                        </div>
                                    @endif
                                    @if(isset($log->details['trace']))
                                        <div x-data="{ open: false }" class="mt-3">
                                            <button @click="open = ! open" type="button" class="flex w-full items-center justify-between text-left text-sm font-medium text-neutral-500 hover:text-neutral-700 dark:hover:text-neutral-300">
                                                <span>{{ __('Stack Trace') }}</span>
                                                <flux:icon.chevron-down class="size-4 transition-transform" ::class="{ 'rotate-180': open }" />
                                            </button>

                                            <div x-show="open" x-collapse x-cloak>
                                                <pre class="mt-2 max-h-48 overflow-auto rounded bg-neutral-900 p-3 text-[10px] text-neutral-300 font-mono">{{ $log->details['trace'] }}</pre>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="flex justify-end">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Close') }}</flux:button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>

    <flux:modal name="erase-history-modal" variant="danger" class="min-w-[22rem]">
        <form wire:submit="eraseHistory" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Erase generation history?') }}</flux:heading>
                <flux:subheading>
                    {{ __('This will permanently delete all generation logs, batches, and rows created for this dataset. This action cannot be undone.') }}
                </flux:subheading>
            </div>

            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="danger">{{ __('Erase History') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
