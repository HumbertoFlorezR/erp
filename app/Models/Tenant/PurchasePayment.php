<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

class PurchasePayment extends Model
{
    protected $fillable = [
        'purchase_invoice_id',
        'payment_method',
        'amount',
        'received_amount',
        'change_amount',
        'transaction_reference',
    ];

    public function invoice()
    {
        return $this->belongsTo(PurchaseInvoice::class, 'purchase_invoice_id');
    }
}
