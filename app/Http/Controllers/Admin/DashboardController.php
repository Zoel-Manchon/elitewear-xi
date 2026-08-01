<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\Money;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    private const LOW_STOCK_THRESHOLD = 3;

    public function __invoke(): View
    {
        $paidThisMonth = Order::query()
            ->where('status', OrderStatus::Paid)
            ->where('paid_at', '>=', now()->startOfMonth())
            ->sum('total_cents');

        return view('admin.dashboard', [
            'revenueThisMonth' => Money::fromCents((int) $paidThisMonth),

            'ordersPending' => Order::where('status', OrderStatus::Pending)->count(),

            'ordersToday' => Order::whereDate('created_at', today())->count(),

            'productsPublished' => Product::published()->count(),

            // El stock bajo es la métrica que de verdad usa un admin de tienda.
            'lowStock' => ProductVariant::query()
                ->with('product.team')
                ->where('is_active', true)
                ->where('stock', '<=', self::LOW_STOCK_THRESHOLD)
                ->orderBy('stock')
                ->limit(10)
                ->get(),

            'recentOrders' => Order::query()
                ->with('user')
                ->latest()
                ->limit(8)
                ->get(),
        ]);
    }
}
