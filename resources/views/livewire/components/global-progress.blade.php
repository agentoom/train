<div>
    <div class="bg-blue-50 dark:bg-blue-900/10 border-b border-blue-100 dark:border-blue-900/20 px-4 py-2">
        <div class="flex items-center justify-between mb-2">
            <div class="flex items-center gap-2">
                <flux:icon.arrow-path class="size-4 animate-spin text-blue-500" />
                <flux:text class="text-sm font-medium text-blue-700 dark:text-blue-400">
                    {{ __('Generating :count datasets...', ['count' => $activeCount]) }}
                </flux:text>
            </div>
            <flux:text class="text-xs font-medium text-blue-600 dark:text-blue-500">
                {{ $completed }} / {{ $total }} {{ __('batches') }} ({{ $percentage }}%)
            </flux:text>
        </div>
        <div class="h-1.5 w-full overflow-hidden rounded-full bg-blue-200 dark:bg-blue-900/40">
            <div
                class="h-1.5 rounded-full bg-blue-500 transition-all duration-500"
                style="width: {{ $percentage }}%"
            ></div>
        </div>
    </div>
</div>
