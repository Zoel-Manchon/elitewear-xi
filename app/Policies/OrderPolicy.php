<?php

namespace App\Policies;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    /** El admin pasa por encima de todo lo demás. */
    public function before(User $user): ?bool
    {
        return $user->is_admin ? true : null;
    }

    public function view(User $user, Order $order): bool
    {
        return $user->id === $order->user_id;
    }

    public function cancel(User $user, Order $order): bool
    {
        return $user->id === $order->user_id
            && $order->status === OrderStatus::Pending;
    }
}
