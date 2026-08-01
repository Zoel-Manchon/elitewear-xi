<?php

namespace App\Support;

use App\Models\Order;
use Illuminate\Support\Facades\URL;

class OrderTrackingUrl
{
    public function for(Order $order): string
    {
        return URL::signedRoute('tracking.email', ['order' => $order]);
    }
}
