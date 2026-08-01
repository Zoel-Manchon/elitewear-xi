<?php

namespace App\Support;

use App\Enums\ShirtPattern;
use App\Models\Product;

/**
 * Dibuja una camiseta en SVG a partir de los colores y el patrón del producto.
 *
 * Por qué generadas y no fotografías: las camisetas retro llevan escudos y
 * logotipos de patrocinador que son marca registrada. Una representación
 * estilizada con los colores del equipo es reconocible, es nuestra, y no
 * arrastra derechos de nadie.
 *
 * Estas SVG las produce el servidor. Las que suben los administradores se
 * siguen rechazando por ser vectores (ver ProductRequest): un SVG subido
 * puede llevar scripts dentro.
 */
class ShirtRenderer
{
    private const WIDTH = 400;

    private const HEIGHT = 500;

    /** Silueta: hombros, mangas, torso y cuello. */
    private const BODY = 'M140,100 L75,138 L98,218 L131,203 L131,437 L269,437 '
        .'L269,203 L302,218 L325,138 L260,100 C238,130 162,130 140,100 Z';

    /** @return array<string, string> vista => contenido SVG */
    public function render(Product $product): array
    {
        return [
            'frente' => $this->front($product),
            'espalda' => $this->back($product),
            'detalle' => $this->detail($product),
        ];
    }

    private function front(Product $product): string
    {
        return $this->document(
            $this->pattern($product)
            .$this->collar($product)
            .$this->crest($product)
            .$this->shading()
        );
    }

    private function back(Product $product): string
    {
        $number = $product->shirt_number ?? 10;
        $ink = $this->contrast($product->primary_color);

        return $this->document(
            $this->pattern($product)
            .$this->collar($product)
            .sprintf(
                '<text x="200" y="330" text-anchor="middle" font-family="Archivo, Arial Black, sans-serif" '
                .'font-size="150" font-weight="900" fill="%s" letter-spacing="-6">%d</text>',
                $ink,
                $number,
            )
            .sprintf(
                '<text x="200" y="215" text-anchor="middle" font-family="Barlow Condensed, Arial, sans-serif" '
                .'font-size="34" font-weight="700" fill="%s" letter-spacing="4">%s</text>',
                $ink,
                htmlspecialchars(strtoupper($this->surname($product)), ENT_QUOTES),
            )
            .$this->shading()
        );
    }

    /** Primer plano del pecho: el detalle que se mira antes de comprar. */
    private function detail(Product $product): string
    {
        return $this->document(
            '<g transform="translate(-160,-90) scale(1.85)">'
            .$this->pattern($product)
            .$this->collar($product)
            .$this->crest($product)
            .'</g>'
            .$this->shading()
        );
    }

    private function document(string $inner): string
    {
        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %d %d" width="%d" height="%d" role="img">'
            .'<rect width="%d" height="%d" fill="#13231d"/>'
            .'<clipPath id="body"><path d="%s"/></clipPath>'
            .'%s'
            .'<path d="%s" fill="none" stroke="rgba(0,0,0,.35)" stroke-width="2"/>'
            .'</svg>',
            self::WIDTH, self::HEIGHT, self::WIDTH, self::HEIGHT,
            self::WIDTH, self::HEIGHT,
            self::BODY,
            $inner,
            self::BODY,
        );
    }

    private function pattern(Product $product): string
    {
        $a = $product->primary_color;
        $b = $product->secondary_color;
        $shapes = sprintf('<rect width="%d" height="%d" fill="%s"/>', self::WIDTH, self::HEIGHT, $a);

        $type = $product->pattern;

        $shapes .= match ($type) {
            ShirtPattern::Stripes => $this->repeat(6, fn ($i) => sprintf(
                '<rect x="%d" y="0" width="24" height="%d" fill="%s"/>',
                60 + $i * 48, self::HEIGHT, $b,
            )),
            ShirtPattern::Hoops => $this->repeat(5, fn ($i) => sprintf(
                '<rect x="0" y="%d" width="%d" height="34" fill="%s"/>',
                130 + $i * 66, self::WIDTH, $b,
            )),
            ShirtPattern::Halves => sprintf(
                '<rect x="200" y="0" width="200" height="%d" fill="%s"/>', self::HEIGHT, $b,
            ),
            ShirtPattern::Quarters => sprintf(
                '<rect x="200" y="0" width="200" height="250" fill="%s"/>'
                .'<rect x="0" y="250" width="200" height="250" fill="%s"/>', $b, $b,
            ),
            ShirtPattern::Sash => sprintf(
                '<path d="M60,110 L340,330 L340,392 L60,172 Z" fill="%s"/>', $b,
            ),
            ShirtPattern::Solid => '',
        };

        return '<g clip-path="url(#body)">'.$shapes.'</g>';
    }

    private function collar(Product $product): string
    {
        return sprintf(
            '<path d="M140,100 C162,130 238,130 260,100 L252,96 C232,120 168,120 148,96 Z" fill="%s"/>',
            $product->secondary_color,
        );
    }

    /** Marca genérica: un escudo abstracto, nunca el de un club real. */
    private function crest(Product $product): string
    {
        $ink = $this->contrast($product->primary_color);

        return sprintf(
            '<g opacity=".9"><path d="M240,150 h30 v22 l-15,12 -15,-12 Z" fill="none" stroke="%s" stroke-width="3"/>'
            .'<circle cx="255" cy="161" r="4" fill="%s"/></g>',
            $ink, $ink,
        );
    }

    /** Sombreado suave: sin esto la camiseta parece un pictograma plano. */
    private function shading(): string
    {
        return '<g clip-path="url(#body)" opacity=".28">'
            .'<path d="M131,203 L131,437 L180,437 L180,203 Z" fill="url(#lg)"/>'
            .'</g>'
            .'<defs><linearGradient id="lg" x1="0" x2="1">'
            .'<stop offset="0" stop-color="#000" stop-opacity=".55"/>'
            .'<stop offset="1" stop-color="#000" stop-opacity="0"/>'
            .'</linearGradient></defs>';
    }

    private function repeat(int $times, callable $fn): string
    {
        return implode('', array_map($fn, range(0, $times - 1)));
    }

    private function surname(Product $product): string
    {
        $words = preg_split('/\s+/', (string) $product->team->name);

        return $words[0] === 'Selección' ? ($words[2] ?? 'XI') : $words[0];
    }

    /** Blanco o negro según el fondo, para que el dorsal siempre se lea. */
    private function contrast(string $hex): string
    {
        [$r, $g, $b] = sscanf(ltrim($hex, '#'), '%2x%2x%2x');
        $luma = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;

        return $luma > 0.6 ? '#12201a' : '#f4f1e8';
    }
}
