<?php

namespace App\Enums;

enum CupSize: string
{
    case Small = 'small';
    case Medium = 'medium';
    case Large = 'large';

    public function label(): string
    {
        return match ($this) {
            self::Small => 'Klein',
            self::Medium => 'Medium',
            self::Large => 'Groot',
        };
    }
}
