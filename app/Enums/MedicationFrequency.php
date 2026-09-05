<?php

namespace App\Enums;

enum MedicationFrequency: string
{
    case AdHoc = 'ad_hoc';
    case OnceDaily = 'once_daily';
    case TwiceDaily = 'twice_daily';
    case ThreeTimesDaily = 'three_times_daily';
    case FourTimesDaily = 'four_times_daily';
    case EveryOtherDay = 'every_other_day';
    case Weekly = 'weekly';
    case Monthly = 'monthly';

    public function label(): string
    {
        return match ($this) {
            self::AdHoc => 'Ad hoc (as needed)',
            self::OnceDaily => 'Once daily',
            self::TwiceDaily => 'Twice daily',
            self::ThreeTimesDaily => 'Three times daily',
            self::FourTimesDaily => 'Four times daily',
            self::EveryOtherDay => 'Every other day',
            self::Weekly => 'Weekly',
            self::Monthly => 'Monthly',
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
            fn (self $frequency): array => ['value' => $frequency->value, 'label' => $frequency->label()],
            self::cases(),
        );
    }
}
