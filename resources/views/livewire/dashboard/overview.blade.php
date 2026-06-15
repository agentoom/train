<div class="flex h-full w-full flex-1 flex-col gap-6">
    <flux:heading size="xl">{{ __('Dashboard') }}</flux:heading>

    {{-- Stats grid --}}
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <div class="rounded-xl border border-neutral-200 bg-white p-5 dark:border-neutral-700 dark:bg-neutral-900">
            <flux:text class="text-sm text-neutral-500">{{ __('Running Jobs') }}</flux:text>
            <p class="mt-1 text-3xl font-bold text-neutral-900 dark:text-white">{{ $runningJobs }}</p>
        </div>
        <div class="rounded-xl border border-neutral-200 bg-white p-5 dark:border-neutral-700 dark:bg-neutral-900">
            <flux:text class="text-sm text-neutral-500">{{ __('Completed Datasets') }}</flux:text>
            <p class="mt-1 text-3xl font-bold text-neutral-900 dark:text-white">{{ $completedDatasets }}</p>
        </div>
        <div class="rounded-xl border border-neutral-200 bg-white p-5 dark:border-neutral-700 dark:bg-neutral-900">
            <flux:text class="text-sm text-neutral-500">{{ __("Today's Tokens") }}</flux:text>
            <p class="mt-1 text-3xl font-bold text-neutral-900 dark:text-white">{{ number_format($todayTokens) }}</p>
        </div>
        <div class="rounded-xl border border-neutral-200 bg-white p-5 dark:border-neutral-700 dark:bg-neutral-900">
            <flux:text class="text-sm text-neutral-500">{{ __("Today's Est. Cost") }}</flux:text>
            <p class="mt-1 text-3xl font-bold text-neutral-900 dark:text-white">${{ number_format($todayCost, 4) }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        {{-- Provider health --}}
        <div class="rounded-xl border border-neutral-200 bg-white p-5 dark:border-neutral-700 dark:bg-neutral-900">
            <flux:heading size="lg" class="mb-4">{{ __('Active Providers') }}</flux:heading>
            @if($providerHealth->isEmpty())
                <flux:text class="text-neutral-400">{{ __('No active providers configured.') }}</flux:text>
            @else
                <ul class="divide-y divide-neutral-100 dark:divide-neutral-800">
                    @foreach($providerHealth as $ph)
                        <li class="flex items-center justify-between py-2">
                            <div>
                                <flux:text class="font-medium">{{ $ph['label'] }}</flux:text>
                                <flux:text class="text-xs text-neutral-400">{{ $ph['type'] }}</flux:text>
                            </div>
                            <flux:badge variant="success" size="sm">{{ __('Enabled') }}</flux:badge>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        {{-- Recent activity --}}
        <div class="rounded-xl border border-neutral-200 bg-white p-5 dark:border-neutral-700 dark:bg-neutral-900">
            <flux:heading size="lg" class="mb-4">{{ __('Recent Projects') }}</flux:heading>
            @if($recentProjects->isEmpty())
                <flux:text class="text-neutral-400">{{ __('No projects yet.') }}</flux:text>
            @else
                <ul class="divide-y divide-neutral-100 dark:divide-neutral-800">
                    @foreach($recentProjects as $project)
                        <li class="flex items-center justify-between py-2">
                            <div>
                                <flux:text class="font-medium">
                                    <a href="{{ route('datasets.show', $project) }}" wire:navigate class="hover:underline">
                                        {{ $project->name }}
                                    </a>
                                </flux:text>
                                <flux:text class="text-xs text-neutral-400">{{ $project->created_at->diffForHumans() }}</flux:text>
                            </div>
                            <livewire:components.status-badge :status="$project->status->value" :key="'proj-'.$project->id" />
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    {{-- Quick links --}}
    <div class="flex gap-3">
        <flux:button :href="route('datasets.create')" wire:navigate variant="primary" icon="plus">
            {{ __('New Dataset') }}
        </flux:button>
        <flux:button :href="route('providers.create')" wire:navigate variant="outline" icon="plus">
            {{ __('Add Provider') }}
        </flux:button>
        <flux:button href="/horizon" target="_blank" variant="ghost" icon="queue-list">
            {{ __('Horizon') }}
        </flux:button>
    </div>
</div>
