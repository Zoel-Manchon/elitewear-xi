<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Orders\OrderNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function __construct(private readonly OrderNotifier $notifier) {}

    public function index(Request $request): View
    {
        $status = $request->string('estado')->toString();

        return view('admin.orders.index', [
            'orders' => Order::query()
                ->with('user')
                ->withCount('items')
                ->when(
                    $status && OrderStatus::tryFrom($status),
                    fn ($query) => $query->where('status', $status),
                )
                ->latest()
                ->paginate(20)
                ->withQueryString(),
            'statuses' => OrderStatus::cases(),
            'current' => $status,
        ]);
    }

    public function show(Order $order): View
    {
        return view('admin.orders.show', [
            'order' => $order->load(['items', 'payments', 'user']),
            'transitions' => array_filter(
                OrderStatus::cases(),
                fn (OrderStatus $status) => $order->status->canTransitionTo($status),
            ),
        ]);
    }

    public function updateStatus(Request $request, Order $order): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::enum(OrderStatus::class)],
            'carrier' => ['nullable', 'string', 'max:80', 'required_if:status,shipped'],
            'tracking_number' => ['nullable', 'string', 'max:120', 'required_if:status,shipped'],
            'tracking_url' => ['nullable', 'url:http,https', 'max:500'],
        ]);

        $next = OrderStatus::from($validated['status']);
        $previous = $order->status;

        if (! $previous->canTransitionTo($next)) {
            return back()->withErrors([
                'status' => "No se puede pasar de {$previous->label()} a {$next->label()}.",
            ]);
        }

        $changes = ['status' => $next];

        if ($next === OrderStatus::Paid) {
            $changes['paid_at'] = $order->paid_at ?? now();
        }

        if ($next === OrderStatus::Shipped) {
            $changes += [
                'carrier' => $validated['carrier'],
                'tracking_number' => $validated['tracking_number'],
                'tracking_url' => $validated['tracking_url'] ?? null,
                'shipped_at' => $order->shipped_at ?? now(),
            ];
        }

        if ($next === OrderStatus::Delivered) {
            $changes['delivered_at'] = $order->delivered_at ?? now();
        }

        $order->update($changes);
        $this->notifier->statusChanged($order->fresh(), $previous);

        return back()->with('status', "Pedido marcado como {$next->label()}.");
    }
}
