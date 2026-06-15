<div>
    <div class="flex items-center justify-between">
        <flux:text class="text-sm font-medium text-neutral-700 dark:text-neutral-300">{{ $label }}</flux:text>
        @if($json !== '')
            <flux:button wire:click="toggle" variant="ghost" size="sm">
                {{ $collapsed ? __('Expand') : __('Collapse') }}
            </flux:button>
        @endif
    </div>

    @if($json !== '' && !$collapsed)
        <div class="mt-2 overflow-auto rounded-lg border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-900">
            <pre class="text-xs text-neutral-800 dark:text-neutral-200">{{ $prettyJson }}</pre>
        </div>
    @elseif($json === '')
        <div class="mt-2 rounded-lg border border-dashed border-neutral-200 p-4 text-center dark:border-neutral-700">
            <flux:text class="text-sm text-neutral-400">{{ __('No JSON to preview') }}</flux:text>
        </div>
    @endif
</div>
