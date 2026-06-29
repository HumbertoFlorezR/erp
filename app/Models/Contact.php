<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    use HasFactory;

    // Conexión por defecto (el TenantMiddleware la conmuta en caliente)
    protected $connection = 'mysql';

    protected $table = 'contacts';

    /**
     * Atributos asignables de forma masiva.
     */
    protected $fillable = [
        'is_client',
        'is_supplier',
        'is_employee',
        'document_type',
        'document_number',
        'verification_digit',
        'first_name',
        'last_name',
        'company_name',
        'email',
        'phone',
        'address',
        'city_code',
        'regime_type',
        'is_active',
    ];

    /**
     * Conversión de tipos nativos (Casting).
     */
    protected $casts = [
        'is_client'   => 'boolean',
        'is_supplier' => 'boolean',
        'is_employee' => 'boolean',
        'is_active'   => 'boolean',
    ];

    /**
     * Atributo dinámico (Accesor) para obtener el nombre completo o razón social.
     * Esto facilita pintar el nombre en las tablas de Vue sin hacer condicionales allí.
     */
    public function getFullNameAttribute()
    {
        if ($this->company_name) {
            return $this->company_name;
        }

        return trim("{$this->first_name} {$this->last_name}");
    }

    /**
     * Aseguramos que los datos clave siempre viajen en mayúsculas o limpios a la BD
     */
    public function setFirstNameAttribute($value)
    {
        $this->attributes['first_name'] = $value ? mb_strtoupper(trim($value), 'UTF-8') : null;
    }

    public function setLastNameAttribute($value)
    {
        $this->attributes['last_name'] = $value ? mb_strtoupper(trim($value), 'UTF-8') : null;
    }

    public function setCompanyNameAttribute($value)
    {
        $this->attributes['company_name'] = $value ? mb_strtoupper(trim($value), 'UTF-8') : null;
    }
}
