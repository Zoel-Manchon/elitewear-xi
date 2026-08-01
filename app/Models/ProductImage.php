<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ProductImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id', 'path', 'alt', 'position', 'is_primary',
        'source_provider', 'source_external_id', 'source_url',
        'source_credit', 'downloaded_at',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'is_primary' => 'boolean',
            'downloaded_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Cae a un placeholder si el fichero no existe todavía.
     * Hace un stat por imagen: aceptable en desarrollo, cachéalo en producción.
     */
    public function url(): string
    {
        return Storage::disk('public')->exists($this->path)
            ? Storage::disk('public')->url($this->path)
            : asset('images/placeholder.svg');
    }
}
