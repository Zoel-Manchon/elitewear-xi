<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Support\OrderAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TrackingController extends Controller
{
    public function __construct(private readonly OrderAccess $access) {}

    public function index(): View
    {
        return view('tracking.index');
    }

    public function lookup(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'number' => ['required', 'string', 'max:24'],
            'email' => ['required', 'email', 'max:180'],
        ]);

        $order = Order::query()
            ->where('number', strtoupper(trim($validated['number'])))
            ->whereRaw('LOWER(email) = ?', [mb_strtolower(trim($validated['email']))])
            ->first();

        if (! $order) {
            return back()->withInput()->withErrors([
                'number' => 'No encontramos un pedido con esos datos.',
            ]);
        }

        $this->access->grant($order);

        return redirect()->route('tracking.show', $order);
    }

    public function email(Order $order): RedirectResponse
    {
        $this->access->grant($order);

        return redirect()->route('tracking.show', $order);
    }

    public function show(Order $order): View
    {
        $this->access->authorize($order);

        return view('tracking.show', [
            'order' => $order->load('items'),
        ]);
    }
}
