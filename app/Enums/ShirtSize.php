<?php

namespace App\Enums;

enum ShirtSize: string
{
    case S = 'S';
    case M = 'M';
    case L = 'L';
    case XL = 'XL';
    case XXL = 'XXL';

    /** Orden de presentación, para no ordenar tallas alfabéticamente. */
    public function position(): int
    {
        return match ($this) {
            self::S => 1,
            self::M => 2,
            self::L => 3,
            self::XL => 4,
            self::XXL => 5,
        };
    }
}
