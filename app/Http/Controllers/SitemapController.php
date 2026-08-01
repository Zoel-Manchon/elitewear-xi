<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $products = Product::published()
            ->select(['slug', 'updated_at'])
            ->latest('updated_at')
            ->get();

        return response()
            ->view('seo.sitemap', ['products' => $products])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
