<?php

namespace App\Enums;

enum CoffeeType: string
{
    case Regular = 'regular';
    case Cappuccino = 'cappuccino';
    case FlatWhite = 'flat_white';
    case Espresso = 'espresso';

    public function label(): string
    {
        return match ($this) {
            self::Regular => 'Regular',
            self::Cappuccino => 'Cappuccino',
            self::FlatWhite => 'Flat White',
            self::Espresso => 'Espresso',
        };
    }
}
