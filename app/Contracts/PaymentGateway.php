<?php

namespace App\Contracts;

use App\Models\Order;

/**
 * Aísla la pasarela detrás de una interfaz para poder falsearla en los tests.
 * Es la única abstracción de este proyecto que se gana su sitio: los tests de
 * checkout no pueden depender de que PayPal esté levantado.
 */
interface PaymentGateway
{
    /** Crea la orden en el proveedor y devuelve su identificador. */
    public function createOrder(Order $order): string;

    /** Captura el pago. Devuelve el id de captura del proveedor. */
    public function capture(string $providerOrderId): string;

    /** Importe capturado en céntimos, para contrastarlo con el pedido. */
    public function capturedAmountCents(string $providerOrderId): int;

    public function verifyWebhookSignature(array $headers, string $body): bool;
}
