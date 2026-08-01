<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Pending = 'pending';       // creado, sin pagar
    case Paid = 'paid';             // capturado en PayPal
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente de pago',
            self::Paid => 'Pagado',
            self::Shipped => 'Enviado',
            self::Delivered => 'Entregado',
            self::Cancelled => 'Cancelado',
            self::Refunded => 'Reembolsado',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Pending => 'bg-secondary',
            self::Paid => 'bg-success',
            self::Shipped, self::Delivered => 'bg-primary',
            self::Cancelled, self::Refunded => 'bg-danger',
        };
    }

    /** Máquina de estados: qué transiciones son legales. */
    public function canTransitionTo(self $next): bool
    {
        return in_array($next, match ($this) {
            self::Pending => [self::Paid, self::Cancelled],
            self::Paid => [self::Shipped, self::Refunded, self::Cancelled],
            self::Shipped => [self::Delivered, self::Refunded],
            self::Delivered => [self::Refunded],
            self::Cancelled, self::Refunded => [],
        }, true);
    }
}
