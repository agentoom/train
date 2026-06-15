@php
    $color = match($status) {
        'enabled', 'completed', 'healthy' => 'green',
        'disabled', 'failed', 'cancelled' => 'red',
        'running', 'queued' => 'blue',
        'draft' => 'zinc',
        default => 'zinc',
    };
@endphp

<flux:badge :color="$color" :size="$size">{{ ucfirst($status) }}</flux:badge>
