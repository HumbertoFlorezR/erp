<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class TenantUserSeeder extends Seeder
{
    public function run(): void
    {
        // Creamos el primer usuario administrador para la empresa
        User::create([
            'name'     => 'Administrador Sajjuna',
            'email'    => 'admin@sajjuna.com',
            'password' => Hash::make('admin123'), // Contraseña temporal de prueba
        ]);
    }
}
