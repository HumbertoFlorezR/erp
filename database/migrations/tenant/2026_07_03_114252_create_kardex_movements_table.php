<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kardex_movements', function (Blueprint $table) {
            $table->id();

            // Relación con el producto
            $table->foreignId('product_id')->constrained('products')->onDelete('restrict');

            // Campos opcionales para asociar el origen del movimiento (Polimorfismo o llaves foráneas limpias)
            // Esto nos dice QUÉ documento generó el movimiento
            $table->nullableMorphs('movable'); // Creará 'movable_type' y 'movable_id' (ej: PurchaseInvoice o SalesInvoice)

            // 🌟 Datos del Lote (Para empalmar con perecederos)
            $table->string('batch_number')->nullable();
            $table->date('expiration_date')->nullable();

            // Tipo de movimiento: 'ENTRADA' (Compra, Ajuste +, Devolución) o 'SALIDA' (Venta, Ajuste -, Baja)
            $table->string('type');
            $table->string('concept'); // Ej: 'COMPRA', 'VENTA', 'AJUSTE_INVENTARIO', 'DEVOLUCION'

            // Cantidades del movimiento (soportando decimales)
            $table->decimal('quantity', 12, 4);
            $table->decimal('price_unit', 15, 2); // Costo o precio unitario de este movimiento
            $table->decimal('total', 15, 2);      // Cantidad * Precio Unitario

            // 📊 FOTO DEL STOCK EN ESE INSTANTE (El "Saldo" del Kardex)
            // Esto es crucial para auditorías rápidas sin recalcular toda la historia
            $table->decimal('balance_quantity', 12, 4);
            $table->decimal('balance_price_unit', 15, 2); // Costo promedio en ese momento
            $table->decimal('balance_total', 15, 2);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kardex_movements');
    }
};
