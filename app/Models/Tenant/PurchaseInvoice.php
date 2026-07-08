<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Contact;
use App\Models\Tenant\PurchaseInvoiceItem;

class PurchaseInvoice extends Model
{
    protected $fillable = [
        'contact_id',
        'invoice_number',
        'issue_date',
        'due_date',
        'subtotal',
        'discount',
        'tax_amount',
        'total',
        'payment_status',
        'notes',
    ];

    // Convertimos las cadenas de texto a objetos Carbon automáticamente para formatear fechas fácil
    protected $casts = [
        'issue_date' => 'date',
        'due_date' => 'date',
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    /**
     * Relación: Una Factura de Compra pertenece a un Proveedor (Tercero/Contacto)
     */
    public function provider(): BelongsTo
    {
        // Ajusta 'Contact::class' según el nombre y ruta real de tu modelo de Terceros
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    /**
     * Relación: Una Factura de Compra tiene muchos ítems/detalles
     */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseInvoiceItem::class);
    }
}
