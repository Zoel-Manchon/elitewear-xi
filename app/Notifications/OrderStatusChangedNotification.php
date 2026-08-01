<?php

namespace App\Notifications;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Support\OrderTrackingUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderStatusChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Order $order,
        private readonly OrderStatus $previous,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $order = $this->order->fresh();
        $mail = (new MailMessage)
            ->subject($this->subject($order))
            ->greeting($this->greeting($order))
            ->line($this->message($order));

        if ($order->status === OrderStatus::Shipped && $order->tracking_number) {
            $mail->line('Transportista: '.($order->carrier ?: 'No especificado'))
                ->line('Localizador: '.$order->tracking_number);
        }

        return $mail
            ->action('Ver seguimiento', app(OrderTrackingUrl::class)->for($order))
            ->line("Estado anterior: {$this->previous->label()}.")
            ->salutation('Elitewear XI');
    }

    private function subject(Order $order): string
    {
        return match ($order->status) {
            OrderStatus::Shipped => "Tu pedido {$order->number} está en camino",
            OrderStatus::Delivered => "Pedido {$order->number} entregado",
            OrderStatus::Cancelled => "Pedido {$order->number} cancelado",
            OrderStatus::Refunded => "Reembolso de {$order->number} procesado",
            default => "Actualización del pedido {$order->number}",
        };
    }

    private function greeting(Order $order): string
    {
        return match ($order->status) {
            OrderStatus::Shipped => 'Ya está en camino',
            OrderStatus::Delivered => 'Pedido entregado',
            OrderStatus::Cancelled => 'Pedido cancelado',
            OrderStatus::Refunded => 'Reembolso procesado',
            default => 'Tu pedido se ha actualizado',
        };
    }

    private function message(Order $order): string
    {
        return match ($order->status) {
            OrderStatus::Shipped => 'Hemos entregado el paquete al transportista.',
            OrderStatus::Delivered => 'El transportista ha marcado el paquete como entregado.',
            OrderStatus::Cancelled => 'El pedido se ha cancelado. Si había un cobro, revisaremos su devolución.',
            OrderStatus::Refunded => 'El importe del pedido ha sido marcado como reembolsado.',
            default => "El pedido está ahora en estado: {$order->status->label()}.",
        };
    }
}
