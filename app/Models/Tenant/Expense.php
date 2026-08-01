<?php

namespace App\Models\Tenant;

use App\Models\Contact;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    use HasFactory;

    protected $fillable = [
        'expense_category_id',
        'provider_id',
        'description',
        'amount',
        'expense_date',
        'payment_method',
        'reference',
        'status',
    ];

    protected $casts = [
        'expense_date' => 'date',
    ];

    public function category()
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function provider()
    {
        return $this->belongsTo(Contact::class, 'provider_id');
    }
}
