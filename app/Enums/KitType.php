<?php

namespace App\Enums;

enum KitType: string
{
    case Home = 'home';
    case Away = 'away';
    case Third = 'third';
    case Goalkeeper = 'goalkeeper';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Home => 'Primera equipación',
            self::Away => 'Segunda equipación',
            self::Third => 'Tercera equipación',
            self::Goalkeeper => 'Portero',
            self::Other => 'Otra equipación',
        };
    }
}
