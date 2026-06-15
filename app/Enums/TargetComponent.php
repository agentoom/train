<?php

namespace App\Enums;

enum TargetComponent: string
{
    case Generation = 'generation';
    case Augmentation = 'augmentation';
    case Critic = 'critic';
    case Refiner = 'refiner';
}
