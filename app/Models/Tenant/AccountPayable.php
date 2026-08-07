<?php

namespace App\Models\Tenant;

use App\Models\Contact;
use Illuminate\Database\Eloquent\Model;

class AccountPayable extends Model
{
    protected $table = 'accounts_payable';

    protected $fillable = [
        'purchase_invoice_id',
        'provider_id',
        'original_amount',
        'balance',
        'due_date',
        'status',
    ];

    protected $casts = [
        'due_date' => 'date',
    ];

    public function invoice()
    {
        return $this->belongsTo(PurchaseInvoice::class, 'purchase_invoice_id');
    }

    public function provider()
    {
        return $this->belongsTo(Contact::class, 'provider_id');
    }
}
