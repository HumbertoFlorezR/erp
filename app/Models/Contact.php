<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    use HasFactory;

    // Operará sobre la conexión que el middleware conmute en caliente
    protected $connection = 'mysql';
    protected $table = 'contacts';

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

    protected $casts = [
        'is_client'   => 'boolean',
        'is_supplier' => 'boolean',
        'is_employee' => 'boolean',
        'is_active'   => 'boolean',
    ];

    protected $appends = ['full_name'];

    /**
     * Accesor dinámico para obtener el nombre completo o razón social.
     * Así en Vue solo pintamos 'contact.full_name' sin condicionales allá.
     */
    public function getFullNameAttribute()
    {
        if ($this->company_name) {
            return $this->company_name;
        }
        return trim("{$this->first_name} {$this->last_name}");
    }

    // Mutadores para guardar strings siempre limpios y en mayúsculas
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
