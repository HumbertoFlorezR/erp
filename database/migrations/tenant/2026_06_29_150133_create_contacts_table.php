<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();

            // 📑 Clasificación del Tercero (Reactividad en el ERP)
            $table->boolean('is_client')->default(false);
            $table->boolean('is_supplier')->default(false);
            $table->boolean('is_employee')->default(false); // 💡 Activador para el módulo de Nómina

            // 🪪 Identificación (Pensado en DIAN / Nómina Electrónica)
            $table->string('document_type', 5)->default('CC'); // CC, NIT, CE, PPT
            $table->string('document_number', 20)->unique();
            $table->string('verification_digit', 1)->nullable(); // Solo para NIT

            // 👤 Datos Principales
            $table->string('first_name', 100)->nullable(); // Separados para nómina/empleados
            $table->string('last_name', 100)->nullable();
            $table->string('company_name', 200)->nullable(); // Razón social si es empresa

            // 📍 Ubicación y Contacto
            $table->string('email', 150)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('address', 255)->nullable();
            $table->string('city_code', 10)->nullable(); // Código Dane para reportes/nómina

            // 💼 Información Tributaria / Laboral básica
            $table->string('regime_type', 20)->default('RESPONSABLE_IVA'); // O NO_RESPONSABLE

            // 🔒 Estado
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
