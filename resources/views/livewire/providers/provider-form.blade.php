<div class="flex h-full w-full flex-1 flex-col gap-6">
        <div class="flex items-center gap-4">
            <flux:button :href="route('providers.index')" wire:navigate variant="ghost" icon="arrow-left" size="sm">
                {{ __('Back') }}
            </flux:button>
            <flux:heading size="xl">{{ $provider ? __('Edit Provider') : __('Add Provider') }}</flux:heading>
        </div>

        <div class="max-w-2xl">
            <form wire:submit="save" class="space-y-6">
                <flux:input wire:model="label" :label="__('Label')" :placeholder="__('e.g. My OpenAI Account')" required />

                <flux:select wire:model="type" :label="__('Provider Type')" required>
                    <flux:select.option value="">{{ __('Select type...') }}</flux:select.option>
                    @foreach($providerTypes as $providerType)
                        <flux:select.option :value="$providerType->value">{{ $providerType->label() }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input
                    wire:model="apiKey"
                    :label="__('API Key')"
                    type="password"
                    :placeholder="$provider ? __('Leave blank to keep existing key') : __('Enter API key')"
                    :required="!$provider"
                />

                <flux:input wire:model="baseUrl" :label="__('Base URL')" type="url" :placeholder="__('Optional — for custom endpoints')" />

                <flux:input wire:model="defaultModel" :label="__('Default Model')" :placeholder="__('e.g. gpt-4o-mini')" />

                <flux:checkbox wire:model="isEnabled" :label="__('Enabled')" />

                <div class="flex items-center gap-4">
                    <flux:button type="submit" variant="primary">
                        {{ $provider ? __('Update Provider') : __('Create Provider') }}
                    </flux:button>
                    <flux:button :href="route('providers.index')" wire:navigate variant="ghost">
                        {{ __('Cancel') }}
                    </flux:button>
                </div>
            </form>

            @if($provider)
                <div class="mt-8 border-t border-neutral-200 pt-8 dark:border-neutral-700">
                    <livewire:providers.provider-connection-test :provider-id="$provider->id" />
                </div>
            @endif
        </div>
</div>
