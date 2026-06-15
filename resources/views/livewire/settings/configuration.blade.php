<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('General Configuration') }}</flux:heading>

    <x-settings.layout :heading="__('LLM Settings')" :subheading="__('Configure global settings for LLM requests')">
        <form wire:submit="save" class="mt-6 space-y-6">
            <flux:input
                wire:model="llm_timeout"
                :label="__('LLM Request Timeout (seconds)')"
                type="number"
                min="1"
                max="600"
                :description="__('Default is 90 seconds. Increase this if you experience frequent timeouts during generation.')"
                required
            />

            <div class="flex items-center gap-4">
                <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </x-settings.layout>
</section>
