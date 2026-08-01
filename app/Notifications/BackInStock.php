<?php

namespace App\Notifications;

use App\Models\StockAlert;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class BackInStock extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly StockAlert $alert) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $variant = $this->alert->variant;
        $product = $variant->product;

        // URL firmada y caducable: nadie puede fabricar una baja ajena
        // manipulando el token, y el enlace deja de servir con el tiempo.
        $unsubscribe = URL::temporarySignedRoute(
            'stock-alerts.destroy',
            now()->addDays(60),
            ['alert' => $this->alert->token],
        );

        return (new MailMessage)
            ->subject("Vuelve a estar disponible: {$product->name}")
            ->greeting('Ha vuelto')
            ->line("La {$product->name} está otra vez disponible en talla {$variant->size->value}.")
            ->line('Nos quedan pocas unidades, así que no la dejes pasar.')
            ->action('Ver la camiseta', route('products.show', $product))
            ->line("Precio: {$variant->price()->format()}")
            ->salutation('Elitewear XI')
            ->line("Si ya no te interesa, puedes darte de baja de este aviso: {$unsubscribe}");
    }
}
