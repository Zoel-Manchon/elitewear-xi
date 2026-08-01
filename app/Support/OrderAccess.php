<?php

namespace App\Support;

use App\Models\Order;
use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Contracts\Session\Session;

class OrderAccess
{
    private const SESSION_KEY = 'guest_order_access';

    public function __construct(
        private readonly Session $session,
        private readonly Auth $auth,
    ) {}

    public function grant(Order $order): void
    {
        $orders = $this->session->get(self::SESSION_KEY, []);
        $orders[(string) $order->getKey()] = now()->addDays(30)->timestamp;
        $this->session->put(self::SESSION_KEY, $orders);
    }

    public function allows(Order $order): bool
    {
        $user = $this->auth->guard()->user();

        if ($user && ($user->is_admin || $order->user_id === $user->getAuthIdentifier())) {
            return true;
        }

        $expiresAt = $this->session->get(self::SESSION_KEY.'.'.$order->getKey());

        return is_numeric($expiresAt) && (int) $expiresAt >= now()->timestamp;
    }

    public function authorize(Order $order): void
    {
        abort_unless($this->allows($order), 403);
    }
}
