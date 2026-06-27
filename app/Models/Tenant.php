<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    // Indicamos explícitamente la tabla central de la base de datos landlord
    protected $table = 'tenants';

    // Desactivamos el incremento automático ya que usamos el ID de texto como llave primaria (ej: 'empresa1')
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'nombre_empresa',
        'nit_rut',
        'telefono',
        'email_contacto',
        'estado'
    ];
}
