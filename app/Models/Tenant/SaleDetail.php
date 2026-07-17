<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SaleDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'sale_id',
        'product_id',
        'quantity',
        'price',
        'discount_percentage',
        'discount_amount',
        'tax_percentage',
        'tax_amount',
        'subtotal'
    ];

    // Relación hacia la cabecera de la venta
    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    // Relación con el producto vendido
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
