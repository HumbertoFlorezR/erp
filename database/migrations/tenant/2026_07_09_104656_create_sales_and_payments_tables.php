<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A. CABECERA DE LA VENTA
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dian_resolution_id')->constrained('dian_resolutions');
            $table->string('invoice_number', 30)->unique()->comment('Ej: SETP-1051');
            $table->foreignId('customer_id')->constrained('contacts'); // Reutiliza tu tabla de contactos/terceros
            $table->foreignId('user_id')->constrained('users')->comment('Cajero que realiza la venta');

            // Financieros globales
            $table->decimal('subtotal', 14, 2);
            $table->decimal('discount_total', 14, 2)->default(0.00);
            $table->decimal('tax_total', 14, 2)->default(0.00)->comment('Acumulado de IVA');
            $table->decimal('total', 14, 2);

            // Estados operativos
            $table->enum('payment_status', ['PAGADA', 'PENDIENTE', 'SEPARE'])->default('PAGADA');
            $table->timestamps();
        });

        // B. DETALLE DE LA VENTA (ITEMS)
        Schema::create('sale_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('products');
            $table->integer('quantity')->unsigned();
            $table->decimal('price', 14, 2)->comment('Precio unitario pactado en la venta');
            $table->decimal('discount_percentage', 5, 2)->default(0.00)->comment('Descuento aplicado por item %');
            $table->decimal('discount_amount', 14, 2)->default(0.00)->comment('Valor en dinero del descuento');
            $table->decimal('tax_percentage', 5, 2)->default(0.00)->comment('Porcentaje de IVA del producto (Ej: 19.00)');
            $table->decimal('tax_amount', 14, 2)->default(0.00)->comment('Dinero del IVA por este item');
            $table->decimal('subtotal', 14, 2);
            $table->timestamps();
        });

        // C. MÉTODOS DE PAGO MIXTOS
        Schema::create('sale_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales')->onDelete('cascade');
            $table->enum('payment_method', ['EFECTIVO', 'TRANSFERENCIA', 'TARJETA_DEBITO', 'TARJETA_CREDITO'])->default('EFECTIVO');
            $table->decimal('amount', 14, 2)->comment('Dinero neto abonado a la deuda');
            $table->decimal('received_amount', 14, 2)->default(0.00)->comment('Efectivo entregado por el cliente');
            $table->decimal('change_amount', 14, 2)->default(0.00)->comment('Vuelto entregado al cliente');
            $table->string('transaction_reference', 50)->nullable()->comment('Número de váucher o aprobación del datáfono/transferencia');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_payments');
        Schema::dropIfExists('sale_details');
        Schema::dropIfExists('sales');
    }
};
