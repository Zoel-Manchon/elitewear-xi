<?php

namespace App\Notifications;

use App\Models\Order;
use App\Support\OrderTrackingUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderPlacedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Order $order) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $order = $this->order->fresh(['items']);
        $trackingUrl = app(OrderTrackingUrl::class)->for($order);

        return (new MailMessage)
            ->subject("Pedido {$order->number} recibido")
            ->greeting('Pedido recibido')
            ->line("Hemos reservado tu pedido {$order->number} y está pendiente de pago.")
            ->line('Total: '.$order->total()->format())
            ->action('Consultar el pedido', $trackingUrl)
            ->line('El stock ya está reservado para evitar que otra compra se lleve tu talla.')
            ->salutation('Elitewear XI');
    }
}
