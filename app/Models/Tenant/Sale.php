<?php

namespace App\Models\Tenant;

use App\Models\Contact;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    use HasFactory;

    protected $fillable = [
        'dian_resolution_id',
        'invoice_number',
        'customer_id',
        'user_id',
        'subtotal',
        'discount_total',
        'tax_total',
        'total',
        'payment_status'
    ];

    // Relación con la resolución DIAN utilizada
    public function resolution()
    {
        return $this->belongsTo(DianResolution::class, 'dian_resolution_id');
    }

    // Relación con el cliente (tabla contacts)
    public function customer()
    {
        return $this->belongsTo(Contact::class, 'customer_id');
    }

    // Relación con el usuario/cajero que ejecutó la venta
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Relación con los artículos desglosados
    public function details()
    {
        return $this->hasMany(SaleDetail::class);
    }

    // Relación con los métodos de pago que liquidaron la factura
    public function payments()
    {
        return $this->hasMany(SalePayment::class);
    }

    // Relación polimórfica inversa con el Kardex (Movable)
    public function kardexMovements()
    {
        return $this->morphMany(KardexMovement::class, 'movable');
    }

    public function accountReceivable()
    {
        return $this->hasOne(AccountReceivable::class);
    }
}
