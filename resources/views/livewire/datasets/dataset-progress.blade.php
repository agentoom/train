<div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
    <div class="mb-3 flex items-center justify-between">
        <flux:heading size="sm">{{ __('Live Progress') }}</flux:heading>
        <flux:text class="text-sm text-neutral-500">
            {{ $completed }} / {{ $total }} {{ __('batches') }}
            @if($running > 0)
                &bull; {{ $running }} {{ __('running') }}
            @endif
            @if($failed > 0)
                &bull; <span class="text-red-500">{{ $failed }} {{ __('failed') }}</span>
            @endif
        </flux:text>
    </div>
    <livewire:components.progress-bar :total="$total" :completed="$completed" label="" />
    <flux:text class="mt-2 text-right text-sm font-medium">{{ $percentage }}%</flux:text>
</div>
