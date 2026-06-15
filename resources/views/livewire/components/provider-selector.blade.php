<div>
    <flux:select wire:model="{{ $fieldName }}" label="AI Provider" placeholder="Select a provider...">
        @foreach($providers as $provider)
            <flux:select.option :value="$provider['id']">{{ $provider['label'] }}</flux:select.option>
        @endforeach
    </flux:select>
</div>
