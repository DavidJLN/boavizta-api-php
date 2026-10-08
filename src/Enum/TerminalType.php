<?php

declare(strict_types=1);

namespace Boavizta\Api\Enum;

enum TerminalType: string
{
    case Laptop = 'laptop';
    case Desktop = 'desktop';
    case Smartphone = 'smartphone';
    case Tablet = 'tablet';
    case Television = 'television';
    case Box = 'box';
    case VrHeadset = 'vr_headset';
}
