<?php

namespace App\Enums;

enum SignalPriority: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
}
