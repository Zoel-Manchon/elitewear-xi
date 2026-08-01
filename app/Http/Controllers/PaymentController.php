<?php

namespace App\Http\Controllers;

use App\Contracts\PaymentGateway;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Orders\OrderNotifier;
use App\Support\OrderAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly OrderAccess $access,
        private readonly OrderNotifier $notifier,
    ) {}

    /** Crea la orden en PayPal. El importe sale del pedido, no del navegador. */
    public function create(Order $order): JsonResponse
    {
        $this->access->authorize($order);
        abort_unless($order->status === OrderStatus::Pending, 409);

        $providerOrderId = $this->gateway->createOrder($order);

        $order->payments()->create([
            'provider' => 'paypal',
            'provider_order_id' => $providerOrderId,
            'status' => 'created',
            'amount_cents' => $order->total_cents,
            'currency' => $order->currency,
        ]);

        return response()->json(['id' => $providerOrderId]);
    }

    public function capture(Request $request, Order $order): JsonResponse
    {
        $this->access->authorize($order);

        $validated = $request->validate([
            'provider_order_id' => ['required', 'string', 'max:64'],
        ]);

        /** @var Payment $payment */
        $payment = $order->payments()
            ->where('provider_order_id', $validated['provider_order_id'])
            ->firstOrFail();

        // Si el webhook llegó antes, el pedido ya está pagado: no capturamos dos veces.
        if ($order->status === OrderStatus::Paid) {
            return response()->json(['status' => 'already_paid']);
        }

        $captureId = $this->gateway->capture($payment->provider_order_id);
        $captured = $this->gateway->capturedAmountCents($payment->provider_order_id);

        // Contrastar el importe capturado con el del pedido: si no cuadran,
        // no marcamos nada como pagado y lo revisamos a mano.
        if ($captured !== $order->total_cents) {
            Log::warning('Importe capturado distinto del pedido', [
                'order' => $order->number,
                'expected' => $order->total_cents,
                'captured' => $captured,
            ]);

            $payment->update(['status' => 'mismatch', 'provider_capture_id' => $captureId]);

            return response()->json(['status' => 'review'], 202);
        }

        if (! $this->markPaid($order, $payment, $captureId)) {
            return response()->json(['status' => 'already_paid']);
        }

        $this->notifier->paid($order->fresh());

        return response()->json([
            'status' => 'paid',
            'redirect' => route('checkout.confirmation', $order),
        ]);
    }

    /**
     * PayPal reintenta los webhooks. Esto tiene que poder ejecutarse N veces
     * con el mismo resultado que una: la idempotencia la da el estado del
     * pedido más el unique de provider_order_id.
     */
    public function webhook(Request $request): JsonResponse
    {
        $headers = array_map(
            fn ($values) => $values[0] ?? '',
            array_change_key_case($request->headers->all(), CASE_LOWER),
        );

        if (! $this->gateway->verifyWebhookSignature($headers, $request->getContent())) {
            Log::warning('Webhook de PayPal con firma inválida', ['ip' => $request->ip()]);

            return response()->json(['status' => 'invalid_signature'], 400);
        }

        $event = $request->input('event_type');
        $providerOrderId = $request->input('resource.supplementary_data.related_ids.order_id')
            ?? $request->input('resource.id');

        if ($event !== 'PAYMENT.CAPTURE.COMPLETED' || ! $providerOrderId) {
            return response()->json(['status' => 'ignored']);
        }

        /** @var Payment|null $payment */
        $payment = Payment::where('provider_order_id', $providerOrderId)->first();

        if (! $payment) {
            return response()->json(['status' => 'unknown_order']);
        }

        /** @var Order $order */
        $order = $payment->order;

        if ($order->status === OrderStatus::Paid) {
            return response()->json(['status' => 'already_processed']);
        }

        // Una firma válida demuestra que el evento procede de PayPal, pero no
        // que el importe sea el que esperamos. El webhook también contrasta
        // importe y divisa, igual que el flujo iniciado desde el navegador.
        $captured = $this->parseAmountCents($request->input('resource.amount.value'));
        $currency = $request->input('resource.amount.currency_code');

        if ($captured !== $order->total_cents || $currency !== $order->currency) {
            Log::warning('Webhook de PayPal con importe o divisa inesperados', [
                'order' => $order->number,
                'expected_amount' => $order->total_cents,
                'captured_amount' => $captured,
                'expected_currency' => $order->currency,
                'captured_currency' => $currency,
            ]);

            $payment->update([
                'status' => 'mismatch',
                'provider_capture_id' => $request->input('resource.id'),
            ]);

            return response()->json(['status' => 'review'], 202);
        }

        if (! $this->markPaid($order, $payment, $request->input('resource.id'))) {
            return response()->json(['status' => 'already_processed']);
        }

        $this->notifier->paid($order->fresh());

        return response()->json(['status' => 'ok']);
    }

    private function markPaid(Order $order, Payment $payment, ?string $captureId): bool
    {
        return DB::transaction(function () use ($order, $payment, $captureId) {
            // Captura y webhook pueden llegar casi simultáneamente. Los locks
            // convierten ambas rutas en una única transición pending -> paid.
            $lockedOrder = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $lockedPayment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($lockedOrder->status === OrderStatus::Paid) {
                return false;
            }

            $lockedPayment->update([
                'status' => 'captured',
                'provider_capture_id' => $captureId,
            ]);

            $lockedOrder->update([
                'status' => OrderStatus::Paid,
                'paid_at' => now(),
            ]);

            return true;
        });
    }

    private function parseAmountCents(mixed $value): ?int
    {
        if (! is_string($value) || ! preg_match('/^(\d+)\.(\d{2})$/', $value, $parts)) {
            return null;
        }

        return ((int) $parts[1] * 100) + (int) $parts[2];
    }
}
