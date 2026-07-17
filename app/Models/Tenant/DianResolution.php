<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DianResolution extends Model
{
    use HasFactory;

    protected $fillable = [
        'prefix',
        'resolution_number',
        'from_number',
        'to_number',
        'current_number',
        'date_from',
        'date_to',
        'is_active'
    ];

    protected $casts = [
        'from_number'    => 'integer',
        'to_number'      => 'integer',
        'current_number' => 'integer',
        'date_from'      => 'date',
        'date_to'        => 'date',
        'is_active'      => 'boolean',
    ];

    // Relación: Una resolución tiene muchas ventas asociadas
    public function sales()
    {
        return $this->hasMany(Sale::class);
    }
}
