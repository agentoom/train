<div>
    <flux:heading size="lg">{{ __('Connection Test') }}</flux:heading>
    <flux:text class="mt-1 text-neutral-500">{{ __('Verify your API key and connection.') }}</flux:text>

    <div class="mt-4">
        <flux:button wire:click="testConnection" wire:loading.attr="disabled" variant="outline" icon="signal">
            <span wire:loading.remove wire:target="testConnection">{{ __('Test Connection') }}</span>
            <span wire:loading wire:target="testConnection">{{ __('Testing...') }}</span>
        </flux:button>
    </div>

    @if($isHealthy === true)
        <div class="mt-4 rounded-lg border border-green-200 bg-green-50 p-4 dark:border-green-800 dark:bg-green-950">
            <div class="flex items-center gap-2">
                <flux:icon.check-circle class="size-5 text-green-600" />
                <flux:text class="font-medium text-green-700 dark:text-green-400">{{ __('Connection successful') }}</flux:text>
            </div>
            @if($latencyMs)
                <flux:text class="mt-1 text-sm text-green-600 dark:text-green-500">{{ __('Latency: :ms ms', ['ms' => $latencyMs]) }}</flux:text>
            @endif
            @if(!empty($availableModels))
                <flux:text class="mt-2 text-sm text-green-600 dark:text-green-500">
                    {{ __('Available models: :models', ['models' => implode(', ', $availableModels)]) }}
                </flux:text>
            @endif
        </div>
    @elseif($isHealthy === false)
        <div class="mt-4 rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-800 dark:bg-red-950">
            <div class="flex items-center gap-2">
                <flux:icon.x-circle class="size-5 text-red-600" />
                <flux:text class="font-medium text-red-700 dark:text-red-400">{{ __('Connection failed') }}</flux:text>
            </div>
            @if($errorMessage)
                <flux:text class="mt-1 text-sm text-red-600 dark:text-red-500">{{ $errorMessage }}</flux:text>
            @endif
        </div>
    @endif
</div>
