<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex items-center justify-between">
        <flux:heading size="xl">{{ __('Dataset Projects') }}</flux:heading>
        <div class="flex items-center gap-2">
            <flux:button wire:click="openImportModal" variant="ghost" icon="arrow-up-tray">
                {{ __('Import') }}
            </flux:button>
            @if (!$projects->isEmpty())
                <flux:button wire:click="exportSelected" variant="ghost" icon="arrow-down-tray" :disabled="empty($selectedIds)">
                    {{ __('Export Selected') }}
                    @if (!empty($selectedIds))
                        <flux:badge size="sm" class="ml-1">{{ count($selectedIds) }}</flux:badge>
                    @endif
                </flux:button>
            @endif
            <flux:button :href="route('datasets.create')" wire:navigate variant="primary" icon="plus">
                {{ __('New Dataset') }}
            </flux:button>
        </div>
    </div>

    @if ($projects->isEmpty())
        <div class="flex flex-col items-center justify-center rounded-xl border border-neutral-200 p-12 text-center dark:border-neutral-700">
            <flux:icon.circle-stack class="mb-4 size-12 text-neutral-400" />
            <flux:heading size="lg">{{ __('No datasets yet') }}</flux:heading>
            <flux:text class="mt-2 text-neutral-500">{{ __('Create a dataset project to get started.') }}</flux:text>
            <flux:button :href="route('datasets.create')" wire:navigate variant="primary" class="mt-6" icon="plus">
                {{ __('New Dataset') }}
            </flux:button>
        </div>
    @else
        <div class="overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-neutral-200 dark:border-neutral-700">
                        <th class="w-10 p-3 text-left">
                            <flux:checkbox
                                :checked="count($selectedIds) === $projects->count()"
                                wire:click="$set('selectedIds', count($selectedIds) === {{ $projects->count() }} ? [] : {{ $projects->pluck('id') }})"
                            />
                        </th>
                        <th class="p-3 text-left">{{ __('Name') }}</th>
                        <th class="p-3 text-left">{{ __('Provider') }}</th>
                        <th class="p-3 text-left">{{ __('Records') }}</th>
                        <th class="p-3 text-left">{{ __('Status') }}</th>
                        <th class="p-3 text-left"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($projects as $project)
                        <tr wire:key="project-{{ $project->id }}" class="border-b border-neutral-100 last:border-0 dark:border-neutral-800">
                            <td class="p-3">
                                <flux:checkbox wire:model.live="selectedIds" value="{{ $project->id }}" />
                            </td>
                            <td class="p-3 font-medium">
                                <a href="{{ route('datasets.show', $project) }}" wire:navigate class="hover:underline">{{ $project->name }}</a>
                            </td>
                            <td class="p-3 text-neutral-500">{{ $project->aiProvider?->label ?? '—' }}</td>
                            <td class="p-3 text-neutral-500">{{ number_format($project->record_count) }}</td>
                            <td class="p-3">
                                <livewire:components.status-badge :status="$project->status->value" :wire:key="'status-'.$project->id" />
                            </td>
                            <td class="p-3">
                                <div class="flex items-center gap-2">
                                    <flux:button :href="route('datasets.show', $project)" wire:navigate variant="ghost" size="sm" icon="eye">
                                        {{ __('View') }}
                                    </flux:button>
                                    <flux:button :href="route('datasets.edit', $project)" wire:navigate variant="ghost" size="sm">
                                        {{ __('Edit') }}
                                    </flux:button>
                                    <flux:button wire:click="deleteProject({{ $project->id }})" wire:confirm="{{ __('Are you sure you want to delete this dataset?') }}" variant="ghost" size="sm">
                                        {{ __('Delete') }}
                                    </flux:button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- Import Modal --}}
    <flux:modal wire:model="showImportModal" class="w-full max-w-md">
        <flux:heading size="lg">{{ __('Import Dataset Settings') }}</flux:heading>
        <flux:text class="mt-1 text-neutral-500">{{ __('Upload a JSON file exported from this application. New dataset projects will be created from the imported settings.') }}</flux:text>

        <div class="mt-6 flex flex-col gap-4">
            @if ($importError)
                <flux:callout variant="danger" icon="exclamation-triangle">
                    {{ $importError }}
                </flux:callout>
            @endif

            <flux:field>
                <flux:label>{{ __('JSON File') }}</flux:label>
                <flux:input type="file" wire:model="importFile" accept=".json" />
                <flux:error name="importFile" />
            </flux:field>
        </div>

        <div class="mt-6 flex justify-end gap-2">
            <flux:button wire:click="$set('showImportModal', false)" variant="ghost">
                {{ __('Cancel') }}
            </flux:button>
            <flux:button wire:click="importSettings" variant="primary" wire:loading.attr="disabled" icon="arrow-up-tray">
                <span wire:loading.remove wire:target="importSettings">{{ __('Import') }}</span>
                <span wire:loading wire:target="importSettings">{{ __('Importing…') }}</span>
            </flux:button>
        </div>
    </flux:modal>
</div>
