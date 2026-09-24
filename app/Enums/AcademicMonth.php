<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum AcademicMonth: int implements HasLabel
{
    case September = 9;
    case October = 10;
    case November = 11;
    case December = 12;
    case January = 1;
    case February = 2;
    case March = 3;
    case April = 4;
    case May = 5;
    case June = 6;

    public function getLabel(): ?string
    {
        return match ($this) {
            self::September => 'September / شتنبر (09)',
            self::October => 'October / أكتوبر (10)',
            self::November => 'November / نونبر (11)',
            self::December => 'December / دجنبر (12)',
            self::January => 'January / يناير (01)',
            self::February => 'February / فبراير (02)',
            self::March => 'March / مارس (03)',
            self::April => 'April / أبريل (04)',
            self::May => 'May / ماي (05)',
            self::June => 'June / يونيو (06)',
        };
    }

    public function getArabicName(): string
    {
        return match ($this) {
            self::September => 'شتنبر',
            self::October => 'أكتوبر',
            self::November => 'نونبر',
            self::December => 'دجنبر',
            self::January => 'يناير',
            self::February => 'فبراير',
            self::March => 'مارس',
            self::April => 'أبريل',
            self::May => 'ماي',
            self::June => 'يونيو',
        };
    }

    public function getEnglishName(): string
    {
        return match ($this) {
            self::September => 'September',
            self::October => 'October',
            self::November => 'November',
            self::December => 'December',
            self::January => 'January',
            self::February => 'February',
            self::March => 'March',
            self::April => 'April',
            self::May => 'May',
            self::June => 'June',
        };
    }

    /**
     * Get the ordered 10-month academic cycle (September to June).
     *
     * @return array<int, AcademicMonth>
     */
    public static function academicCycleMonths(): array
    {
        return [
            self::September,
            self::October,
            self::November,
            self::December,
            self::January,
            self::February,
            self::March,
            self::April,
            self::May,
            self::June,
        ];
    }

    /**
     * Check if a given month number (1-12) belongs to the academic cycle.
     */
    public static function isValidAcademicMonth(int $month): bool
    {
        return in_array($month, [9, 10, 11, 12, 1, 2, 3, 4, 5, 6], true);
    }
}
