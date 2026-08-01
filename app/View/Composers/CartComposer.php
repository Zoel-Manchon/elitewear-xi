<?php

namespace App\View\Composers;

use App\Support\CartResolver;
use Illuminate\View\View;

class CartComposer
{
    public function __construct(private readonly CartResolver $resolver) {}

    public function compose(View $view): void
    {
        $cart = $this->resolver->current();

        $view->with([
            'cart' => $cart,
            'cartCount' => $cart?->itemCount() ?? 0,
        ]);
    }
}
