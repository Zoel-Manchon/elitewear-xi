<?php

namespace App\Enums;

enum ShirtPattern: string
{
    case Solid = 'solid';
    case Stripes = 'stripes';       // rayas verticales
    case Hoops = 'hoops';           // aros horizontales
    case Halves = 'halves';         // mitades verticales
    case Sash = 'sash';             // banda cruzada
    case Quarters = 'quarters';

    public function label(): string
    {
        return match ($this) {
            self::Solid => 'Liso',
            self::Stripes => 'Rayas verticales',
            self::Hoops => 'Aros horizontales',
            self::Halves => 'Mitades',
            self::Sash => 'Banda cruzada',
            self::Quarters => 'Cuartos',
        };
    }
}
