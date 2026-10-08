<?php

declare(strict_types=1);

namespace Boavizta\Api\Enum;

enum ComponentType: string
{
    case Cpu = 'cpu';
    case Gpu = 'gpu';
    case Ram = 'ram';
    case Ssd = 'ssd';
    case Hdd = 'hdd';
    case Motherboard = 'motherboard';
    case PowerSupply = 'power_supply';
    case Case = 'case';

    /**
     * The archetype list endpoint is singular for components (`/cpu/archetype`).
     */
    public function archetypesPath(): string
    {
        return $this->value . '/archetype';
    }
}
