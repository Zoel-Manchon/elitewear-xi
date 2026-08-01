<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReviewController extends Controller
{
    public function store(Request $request, Product $product): RedirectResponse
    {
        abort_unless($product->is_active && $product->published_at?->isPast(), 404);

        $validated = $request->validate([
            'order_item_id' => ['required', 'integer'],
            'rating' => ['required', 'integer', Rule::in([1, 2, 3, 4, 5])],
            'title' => ['nullable', 'string', 'max:120'],
            'body' => ['required', 'string', 'min:20', 'max:2000'],
        ]);

        $item = OrderItem::query()
            ->whereKey($validated['order_item_id'])
            ->where('product_id', $product->id)
            ->whereHas('order', function ($query) use ($request) {
                $query->where('user_id', $request->user()->id)
                    ->whereIn('status', [
                        OrderStatus::Paid->value,
                        OrderStatus::Shipped->value,
                        OrderStatus::Delivered->value,
                    ]);
            })
            ->firstOrFail();

        $review = Review::firstOrCreate(
            ['order_item_id' => $item->id],
            [
                'user_id' => $request->user()->id,
                'product_id' => $product->id,
                'rating' => $validated['rating'],
                'title' => $validated['title'] ?? null,
                'body' => $validated['body'],
            ],
        );

        // is_approved y published_at quedan fuera de $fillable a propósito:
        // deciden la publicación y no deben poder llegar desde el request.
        // Aquí se establecen explícitamente, ya validada la compra.
        if ($review->wasRecentlyCreated) {
            $review->forceFill([
                'is_approved' => true,
                'published_at' => now(),
            ])->save();
        }

        return back()->with(
            'status',
            $review->wasRecentlyCreated
                ? 'Gracias. Tu reseña verificada ya está publicada.'
                : 'Esta compra ya tiene una reseña publicada.',
        );
    }

    public function destroy(Request $request, Review $review): RedirectResponse
    {
        abort_unless($review->user_id === $request->user()->id || $request->user()->is_admin, 403);
        $review->delete();

        return back()->with('status', 'Reseña eliminada.');
    }
}
