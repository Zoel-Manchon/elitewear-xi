<?php

namespace App\Services\PayPal;

use App\Contracts\PaymentGateway;
use App\Models\Order;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PayPalGateway implements PaymentGateway
{
    public function __construct(
        private readonly string $clientId,
        private readonly string $secret,
        private readonly string $baseUrl,
        private readonly ?string $webhookId = null,
    ) {}

    public function createOrder(Order $order): string
    {
        // El importe se toma del PEDIDO, calculado en servidor. Nunca de la
        // petición del cliente: ese es el fraude más elemental del checkout.
        $response = $this->request()->post('/v2/checkout/orders', [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => $order->number,
                'custom_id' => (string) $order->id,
                'amount' => [
                    'currency_code' => $order->currency,
                    'value' => number_format($order->total_cents / 100, 2, '.', ''),
                ],
            ]],
        ])->throw();

        return $response->json('id');
    }

    public function capture(string $providerOrderId): string
    {
        $response = $this->request()
            ->post("/v2/checkout/orders/{$providerOrderId}/capture")
            ->throw();

        $captureId = $response->json('purchase_units.0.payments.captures.0.id');

        if (! $captureId) {
            throw new RuntimeException('PayPal no devolvió un id de captura.');
        }

        return $captureId;
    }

    public function capturedAmountCents(string $providerOrderId): int
    {
        $response = $this->request()
            ->get("/v2/checkout/orders/{$providerOrderId}")
            ->throw();

        $value = $response->json('purchase_units.0.payments.captures.0.amount.value')
            ?? $response->json('purchase_units.0.amount.value')
            ?? '0';

        if (! is_string($value) || ! preg_match('/^(\d+)\.(\d{2})$/', $value, $parts)) {
            throw new RuntimeException('PayPal devolvió un importe con formato inesperado.');
        }

        return ((int) $parts[1] * 100) + (int) $parts[2];
    }

    public function verifyWebhookSignature(array $headers, string $body): bool
    {
        if (! $this->webhookId) {
            return false;
        }

        $response = $this->request()->post('/v1/notifications/verify-webhook-signature', [
            'auth_algo' => $headers['paypal-auth-algo'] ?? '',
            'cert_url' => $headers['paypal-cert-url'] ?? '',
            'transmission_id' => $headers['paypal-transmission-id'] ?? '',
            'transmission_sig' => $headers['paypal-transmission-sig'] ?? '',
            'transmission_time' => $headers['paypal-transmission-time'] ?? '',
            'webhook_id' => $this->webhookId,
            'webhook_event' => json_decode($body, true),
        ]);

        return $response->successful()
            && $response->json('verification_status') === 'SUCCESS';
    }

    private function request()
    {
        return Http::baseUrl($this->baseUrl)
            ->withToken($this->accessToken())
            ->acceptJson()
            ->timeout(20);
    }

    private function accessToken(): string
    {
        return Cache::remember('paypal.access_token', now()->addMinutes(25), function () {
            $response = Http::baseUrl($this->baseUrl)
                ->asForm()
                ->withBasicAuth($this->clientId, $this->secret)
                ->post('/v1/oauth2/token', ['grant_type' => 'client_credentials'])
                ->throw();

            return $response->json('access_token');
        });
    }
}
