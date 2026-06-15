<div>
    @if($label !== '')
        <div class="mb-1 flex items-center justify-between">
            <flux:text class="text-sm text-neutral-600 dark:text-neutral-400">{{ $label }}</flux:text>
            <flux:text class="text-sm font-medium">{{ $percentage }}%</flux:text>
        </div>
    @endif
    <div class="h-2 w-full overflow-hidden rounded-full bg-neutral-200 dark:bg-neutral-700">
        <div
            class="h-2 rounded-full bg-blue-500 transition-all duration-300"
            style="width: {{ $percentage }}%"
        ></div>
    </div>
</div>
