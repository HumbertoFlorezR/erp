<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('export_preferences', function (Blueprint $table) {
            $table->id();

            // Relación con el usuario que guarda la preferencia
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // Identificador del módulo (ej: 'contacts', 'products')
            $table->string('module', 50);

            // Formato preferido por defecto (ej: 'XLSX', 'PDF', 'CSV')
            $table->string('default_format', 10)->default('XLSX');

            // Guardaremos el array de columnas seleccionadas como un JSON nativo en la BD
            $table->json('selected_columns')->comment('Lista de columnas elegidas por el usuario');

            $table->timestamps();

            // Índice compuesto para que un usuario solo tenga una preferencia por módulo
            $table->unique(['user_id', 'module']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('export_preferences');
    }
};
