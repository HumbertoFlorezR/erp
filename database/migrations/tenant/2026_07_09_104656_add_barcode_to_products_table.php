<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Ubicamos el código de barras justo después de tu columna 'code'
            $table->string('barcode', 50)->nullable()->unique()->after('code')->comment('Código de barras para lector óptico POS');

            // Añadimos el porcentaje de descuento por defecto para futuras ofertas
            $table->decimal('discount_rate', 5, 2)->default(0.00)->after('tax_type')->comment('Porcentaje de descuento base predefinido para ofertas');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['barcode', 'discount_rate']);
        });
    }
};
