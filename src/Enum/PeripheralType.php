<?php

declare(strict_types=1);

namespace Boavizta\Api\Enum;

enum PeripheralType: string
{
    case Monitor = 'monitor';
    case UsbStick = 'usb_stick';
    case ExternalSsd = 'external_ssd';
    case ExternalHdd = 'external_hdd';
    case VrController = 'vr_controller';
}
