<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_invoice_items', function (Blueprint $blueprint) {
            $blueprint->id();

            // Relación con la cabecera de la compra
            $blueprint->foreignId('purchase_invoice_id')
                      ->constrained('purchase_invoices')
                      ->onDelete('cascade'); // Si se borra la factura, se borran sus ítems

            // Relación con el Producto/Servicio
            $blueprint->foreignId('product_id')->constrained('products')->onDelete('restrict');

            // Datos de la transacción del ítem
            $blueprint->decimal('quantity', 12, 4);      // Cantidad comprada (soporta decimales si vendes por kilos/metros)
            $blueprint->decimal('price_unit', 15, 2);    // Costo unitario pactado con el proveedor
            $blueprint->decimal('tax_rate', 5, 2)->default(19.00); // Porcentaje de IVA aplicado (ej: 19.00, 5.00, 0.00)
            $blueprint->decimal('tax_amount', 15, 2);    // Total de impuesto para esta línea
            $blueprint->decimal('subtotal', 15, 2);      // (Cantidad * Precio) sin impuesto
            $blueprint->decimal('total', 15, 2);         // Subtotal + Tax Amount

            $blueprint->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_invoice_items');
    }
};
