<?php

namespace App\Services\Orders;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Notifications\OrderPaidNotification;
use App\Notifications\OrderPlacedNotification;
use App\Notifications\OrderStatusChangedNotification;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

class OrderNotifier
{
    public function placed(Order $order): void
    {
        $this->route($order)->notify((new OrderPlacedNotification($order))->afterCommit());
    }

    public function paid(Order $order): void
    {
        $this->route($order)->notify((new OrderPaidNotification($order))->afterCommit());
    }

    public function statusChanged(Order $order, OrderStatus $previous): void
    {
        if ($order->status === OrderStatus::Paid) {
            $this->paid($order);

            return;
        }

        $this->route($order)->notify(
            (new OrderStatusChangedNotification($order, $previous))->afterCommit(),
        );
    }

    private function route(Order $order): AnonymousNotifiable
    {
        $name = $order->shipping_address['full_name'] ?? null;

        return Notification::route('mail', $name ? [$order->email => $name] : $order->email);
    }
}
