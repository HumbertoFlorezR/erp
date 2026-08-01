<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_default')->default(false); // categorías que vienen de fábrica
            $table->timestamps();
        });

        // Sembramos las categorías base para que el select nunca empiece vacío
        $now = now();
        DB::table('expense_categories')->insert([
            ['name' => 'Arriendo',      'is_default' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Servicios Públicos', 'is_default' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Nómina',        'is_default' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Transporte',    'is_default' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Otros',         'is_default' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_categories');
    }
};
