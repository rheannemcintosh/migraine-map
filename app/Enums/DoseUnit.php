<?php

namespace App\Enums;

enum DoseUnit: string
{
    case Milligram = 'mg';
    case Microgram = 'mcg';
    case Gram = 'g';
    case Millilitre = 'ml';
    case InternationalUnit = 'IU';
    case Tablet = 'tablet';
    case Capsule = 'capsule';
    case Puff = 'puff';
    case Drop = 'drop';
    case Spray = 'spray';
    case Patch = 'patch';
    case Injection = 'injection';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
