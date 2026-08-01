<?php

namespace App\Notifications;

use App\Models\Order;
use App\Support\OrderTrackingUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderPaidNotification extends Notification implements ShouldQueue
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

        return (new MailMessage)
            ->subject("Pago confirmado · {$order->number}")
            ->greeting('Pago confirmado')
            ->line("El pago de {$order->total()->format()} se ha procesado correctamente.")
            ->line('Prepararemos tu pedido y te enviaremos otro correo cuando salga del almacén.')
            ->action('Seguir el pedido', app(OrderTrackingUrl::class)->for($order))
            ->salutation('Elitewear XI');
    }
}
