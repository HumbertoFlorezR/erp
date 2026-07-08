<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Tenant\PurchaseInvoice;

class PurchaseInvoiceItem extends Model
{
    protected $fillable = [
        'purchase_invoice_id',
        'product_id',
        'batch_number',      // 🌟 El Lote
        'expiration_date',   // 🌟 Fecha de Vencimiento
        'quantity',
        'price_unit',
        'tax_rate',
        'tax_amount',
        'subtotal',
        'total',
    ];

    protected $casts = [
        'expiration_date' => 'date',
        'quantity' => 'decimal:4',
        'price_unit' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    /**
     * Relación: Este ítem pertenece a una factura de compra superior
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(PurchaseInvoice::class, 'purchase_invoice_id');
    }

    /**
     * Relación: Este ítem hace referencia a un Producto o Servicio del catálogo
     */
    public function product(): BelongsTo
    {
        // Ajusta 'Product::class' si tu modelo se llama de otra forma en el sistema
        return $this->belongsTo(Product::class, 'product_id');
    }
}
