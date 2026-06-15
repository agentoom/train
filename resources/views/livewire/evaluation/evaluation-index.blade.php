<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex items-center justify-between">
        <flux:heading size="xl">{{ __('Dataset Evaluation') }}</flux:heading>
    </div>

    {{-- Run Evaluation Panel --}}
    <div class="rounded-xl border border-neutral-200 p-6 dark:border-neutral-700">
        <flux:heading size="lg" class="mb-1">{{ __('Run Evaluation') }}</flux:heading>
        <flux:text class="mb-4 text-neutral-500">{{ __('Select a generated dataset version and run the governance evaluation pipeline manually.') }}</flux:text>

        @if($errorMessage)
            <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-800 dark:bg-red-950 dark:text-red-400">
                {{ $errorMessage }}
            </div>
        @endif

        <div class="flex flex-col gap-4 sm:flex-row sm:items-end">
            <div class="flex-1">
                <flux:label for="version-select">{{ __('Dataset Version') }}</flux:label>
                @if($versions->isEmpty())
                    <flux:text class="mt-1 text-neutral-400">{{ __('No dataset versions available yet. Generate a dataset first.') }}</flux:text>
                @else
                    <flux:select wire:model="selectedVersionId" id="version-select" class="mt-1 w-full">
                        <flux:select.option value="">{{ __('— Select a version —') }}</flux:select.option>
                        @foreach($versions as $version)
                            <flux:select.option value="{{ $version->id }}">
                                {{ $version->datasetProject->name ?? '—' }} — v{{ $version->version_number }}
                                ({{ $version->record_count }} rows, {{ $version->status->value }})
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                @endif
            </div>

            <flux:button
                wire:click="runEvaluation"
                wire:loading.attr="disabled"
                variant="primary"
                icon="beaker"
                :disabled="$versions->isEmpty()"
            >
                <span wire:loading.remove wire:target="runEvaluation">{{ __('Run Evaluation') }}</span>
                <span wire:loading wire:target="runEvaluation">{{ __('Running…') }}</span>
            </flux:button>
        </div>
    </div>

    {{-- Evaluation Reports --}}
    <div>
        <flux:heading size="lg" class="mb-3">{{ __('Evaluation Reports') }}</flux:heading>

        @if($reports->isEmpty())
            <div class="flex flex-col items-center justify-center rounded-xl border border-neutral-200 p-12 text-center dark:border-neutral-700">
                <flux:icon.beaker class="mb-4 size-12 text-neutral-400" />
                <flux:heading size="lg">{{ __('No evaluations yet') }}</flux:heading>
                <flux:text class="mt-2 text-neutral-500">{{ __('Select a dataset version above and run the evaluation pipeline.') }}</flux:text>
            </div>
        @else
            <div class="overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-neutral-200 dark:border-neutral-700">
                            <th class="p-3 text-left text-sm font-medium">{{ __('Dataset') }}</th>
                            <th class="p-3 text-left text-sm font-medium">{{ __('Version') }}</th>
                            <th class="p-3 text-left text-sm font-medium">{{ __('Score') }}</th>
                            <th class="p-3 text-left text-sm font-medium">{{ __('Result') }}</th>
                            <th class="p-3 text-left text-sm font-medium">{{ __('Verdict') }}</th>
                            <th class="p-3 text-left text-sm font-medium">{{ __('Evaluated') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($reports as $report)
                            <tr wire:key="report-{{ $report->id }}" class="border-b border-neutral-100 last:border-0 dark:border-neutral-800">
                                <td class="p-3 font-medium">
                                    {{ $report->datasetVersion->datasetProject->name ?? '—' }}
                                </td>
                                <td class="p-3 text-neutral-500">
                                    v{{ $report->datasetVersion->version_number ?? '—' }}
                                </td>
                                <td class="p-3">
                                    <span class="font-semibold {{ $report->overall_score >= 65 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                        {{ number_format($report->overall_score, 1) }}
                                    </span>
                                    <span class="text-neutral-400">/100</span>
                                </td>
                                <td class="p-3">
                                    @if($report->passed)
                                        <flux:badge color="green" size="sm">{{ __('Passed') }}</flux:badge>
                                    @else
                                        <flux:badge color="red" size="sm">{{ __('Failed') }}</flux:badge>
                                    @endif
                                </td>
                                <td class="p-3 max-w-xs">
                                    <flux:text class="truncate text-sm text-neutral-500">{{ $report->verdict }}</flux:text>
                                </td>
                                <td class="p-3 text-sm text-neutral-400">
                                    {{ $report->created_at->diffForHumans() }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
