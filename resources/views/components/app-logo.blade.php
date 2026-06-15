@props([
    'sidebar' => false,
])

@if($sidebar)
    <flux:sidebar.brand name="Agentoom Train" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-md text-accent-foreground">
            <img src="{{ asset('images/agentoom_logo.svg') }}" alt="Agentoom Train" class="size-5 fill-current text-white dark:text-black" />
        </x-slot>
    </flux:sidebar.brand>
@else
    <flux:brand name="Agentoom Train" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-md text-accent-foreground">
            <img src="{{ asset('images/agentoom_logo.svg') }}" alt="Agentoom Train" class="size-5 fill-current text-white dark:text-black" />
        </x-slot>
    </flux:brand>
@endif
