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
        Schema::create('products', function (Blueprint $table) {
            $table->id();

            // Campos de Clasificación
            $table->enum('type', ['PRODUCTO', 'SERVICIO'])->default('PRODUCTO');
            $table->string('code', 50)->nullable()->comment('Código interno o SKU');
            $table->string('name', 150);
            $table->text('description')->nullable();

            // Precios e Impuestos (Uso de decimales para precisión financiera)
            $table->decimal('average_cost', 14, 2)->default(0.00)->comment('Costo promedio ponderado de adquisición (Base para cálculo de utilidad)');
            $table->decimal('last_purchase_price', 14, 2)->default(0.00)->comment('Último precio de compra registrado (Alerta de reposición)');

            $table->decimal('price_excluding_tax', 14, 2)->default(0.00)->comment('Precio antes de IVA / Base gravable');
            $table->decimal('tax_rate', 5, 2)->default(19.00)->comment('Porcentaje de IVA (ej: 19.00, 5.00, 0.00)');
            $table->enum('tax_type', ['GRAVADO', 'EXENTO', 'EXCLUIDO'])->default('GRAVADO');

            // Control de Inventario (Solo aplica si type = PRODUCTO)
            $table->decimal('stock', 12, 2)->default(0.00)->comment('Cantidad actual disponible');
            $table->decimal('minimum_stock', 12, 2)->default(0.00)->comment('Umbral mínimo permitido antes de generar alerta');
            $table->boolean('manage_stock')->default(false)->comment('Indica si el sistema debe restar stock al vender');

            // CARACTERÍSTICAS ESPECIALES (PERECEDEROS)
            $table->boolean('is_perishable')->default(false)->comment('Indica si el producto es perecedero y requiere control de vencimiento');

            // Datos Estándar DIAN / Unidades
            $table->string('unit_measure_code', 5)->default('94')->comment('94 = Unidad estándar DIAN, WSD = Servicios estándar');
            $table->string('dian_code', 20)->nullable()->comment('Código de estándar de producto si aplica (UNSPSC)');

            // Estado del ítem
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
