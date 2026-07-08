<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_invoices', function (Blueprint $blueprint) {
            $blueprint->id();

            // Relación con el Proveedor (viene de tu tabla de contactos/terceros)
            $blueprint->foreignId('contact_id')->constrained('contacts')->onDelete('restrict');

            // Datos de la factura del proveedor
            $blueprint->string('invoice_number'); // Número de factura (ej: FE-5420)
            $blueprint->date('issue_date');       // Fecha de emisión
            $blueprint->date('due_date');         // Fecha de vencimiento (para el control de CxP)

            // Totales financieros
            $blueprint->decimal('subtotal', 15, 2)->default(0.00);
            $blueprint->decimal('discount', 15, 2)->default(0.00);
            $blueprint->decimal('tax_amount', 15, 2)->default(0.00); // Total de IVA u otros impuestos
            $blueprint->decimal('total', 15, 2)->default(0.00);

            // Estados del proceso
            // Estado de pago: 'PENDIENTE', 'PARCIAL', 'PAGADA'
            $blueprint->string('payment_status')->default('PENDIENTE');

            $blueprint->text('notes')->nullable(); // Observaciones internas
            $blueprint->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_invoices');
    }
};
