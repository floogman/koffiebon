<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case Pin = 'pin';
    case Mollie = 'mollie';
}
