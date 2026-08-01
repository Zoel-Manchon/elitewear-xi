<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        return view('account.orders', [
            'orders' => $request->user()->orders()
                ->withCount('items')
                ->latest()
                ->paginate(10),
        ]);
    }

    public function show(Order $order): View
    {
        $this->authorize('view', $order);

        return view('account.order', ['order' => $order->load('items.product')]);
    }
}
