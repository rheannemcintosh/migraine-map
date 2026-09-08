<?php

namespace App\Enums;

enum TimeOfDay: string
{
    case Waking = 'waking';
    case Morning = 'morning';
    case Midday = 'midday';
    case Afternoon = 'afternoon';
    case Evening = 'evening';
    case Bedtime = 'bedtime';
    case Night = 'night';

    public function label(): string
    {
        return match ($this) {
            self::Waking => 'Upon waking',
            self::Morning => 'Morning',
            self::Midday => 'Midday',
            self::Afternoon => 'Afternoon',
            self::Evening => 'Evening',
            self::Bedtime => 'Before sleeping',
            self::Night => 'Night',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $period): array => ['value' => $period->value, 'label' => $period->label()],
            self::cases(),
        );
    }
}
