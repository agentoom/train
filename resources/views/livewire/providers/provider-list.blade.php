<div class="flex h-full w-full flex-1 flex-col gap-6">
        <div class="flex items-center justify-between">
            <flux:heading size="xl">{{ __('AI Providers') }}</flux:heading>
            <flux:button :href="route('providers.create')" wire:navigate variant="primary" icon="plus">
                {{ __('Add Provider') }}
            </flux:button>
        </div>

        @if($providers->isEmpty())
            <div class="flex flex-col items-center justify-center rounded-xl border border-neutral-200 p-12 text-center dark:border-neutral-700">
                <flux:icon.cpu-chip class="mb-4 size-12 text-neutral-400" />
                <flux:heading size="lg">{{ __('No providers yet') }}</flux:heading>
                <flux:text class="mt-2 text-neutral-500">{{ __('Add an AI provider to get started.') }}</flux:text>
                <flux:button :href="route('providers.create')" wire:navigate variant="primary" class="mt-6" icon="plus">
                    {{ __('Add Provider') }}
                </flux:button>
            </div>
        @else
            <div class="overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700">
                <table class="w-full">
                    <thead>
                        <tr>
                            <th class="text-left p-3">{{ __('Label') }}</th>
                            <th class="text-left p-3">{{ __('Type') }}</th>
                            <th class="text-left p-3">{{ __('Default Model') }}</th>
                            <th class="text-left p-3">{{ __('Status') }}</th>
                            <th class="text-left p-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($providers as $provider)
                            <tr wire:key="provider-{{ $provider->id }}">
                                <td class="p-3">{{ $provider->label }}</td>
                                <td class="p-3">{{ $provider->type->label() }}</td>
                                <td class="p-3">{{ $provider->default_model ?? '—' }}</td>
                                <td class="p-3">{{ $provider->is_enabled ? __('Enabled') : __('Disabled') }}</td>
                                <td class="p-3">
                                    <div class="flex items-center gap-2">
                                        <a href="{{ route('providers.edit', $provider) }}" wire:navigate>{{ __('Edit') }}</a>
                                        <button wire:click="toggleEnabled({{ $provider->id }})">
                                            {{ $provider->is_enabled ? __('Disable') : __('Enable') }}
                                        </button>
                                        <button wire:click="deleteProvider({{ $provider->id }})" wire:confirm="Are you sure you want to delete this provider?">
                                            {{ __('Delete') }}
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
</div>
