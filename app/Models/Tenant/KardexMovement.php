<?php

namespace App\Models\Tenant;

use App\Models\Tenant\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class KardexMovement extends Model
{
    protected $fillable = [
        'product_id',
        'movable_type',
        'movable_id',
        'batch_number',
        'expiration_date',
        'type',
        'concept',
        'quantity',
        'price_unit',
        'total',
        'balance_quantity',
        'balance_price_unit',
        'balance_total',
    ];

    protected $casts = [
        'expiration_date' => 'date',
        'quantity' => 'decimal:4',
        'price_unit' => 'decimal:2',
        'total' => 'decimal:2',
        'balance_quantity' => 'decimal:4',
        'balance_price_unit' => 'decimal:2',
        'balance_total' => 'decimal:2',
    ];

    /**
     * Relación con el producto afetado
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Relación polimórfica para saber qué documento originó esto (Compra, Venta, etc.)
     */
    public function movable(): MorphTo
    {
        return $this->morphTo();
    }
}
