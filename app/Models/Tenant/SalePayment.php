<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalePayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'sale_id',
        'payment_method',
        'amount',
        'received_amount',
        'change_amount',
        'transaction_reference'
    ];

    // Relación hacia la cabecera de la venta
    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }
}
