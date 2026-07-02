<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExportPreference extends Model
{
    protected $fillable = [
        'user_id',
        'module',
        'default_format',
        'selected_columns'
    ];

    /**
     * Convertir automáticamente el JSON de la BD en un array de PHP y viceversa.
     */
    protected $casts = [
        'selected_columns' => 'array',
    ];

    /**
     * Relación con el usuario propietario de la preferencia.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
