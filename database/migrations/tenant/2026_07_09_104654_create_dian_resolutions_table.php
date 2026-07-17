<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dian_resolutions', function (Blueprint $table) {
            $table->id();
            $table->string('prefix', 10)->nullable()->comment('Ej: SETP, FE, POS');
            $table->string('resolution_number', 50)->comment('Número oficial del formulario 1876');
            $table->integer('from_number')->unsigned();
            $table->integer('to_number')->unsigned();
            $table->integer('current_number')->unsigned()->comment('Último número utilizado');
            $table->date('date_from');
            $table->date('date_to');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dian_resolutions');
    }
};
