<?php

namespace App\Enums;

enum UiLocale: string
{
    case ENGLISH = 'en';
    case POLISH = 'pl';

    public function label(): string
    {
        return match ($this) {
            self::ENGLISH => 'English',
            self::POLISH => 'Polski',
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $locale): array => [
                'value' => $locale->value,
                'label' => $locale->label(),
            ],
            self::cases(),
        );
    }
}
