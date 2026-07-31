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
        Schema::create('accounts_receivable', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->unique()->constrained('sales')->onDelete('cascade'); // 1 cuenta de cobro por venta
            $table->foreignId('customer_id')->constrained('contacts'); // denormalizado: consultar cartera por cliente sin join a sales
            $table->decimal('original_amount', 14, 2)->comment('Saldo pendiente al momento de generar la cuenta de cobro');
            $table->decimal('balance', 14, 2)->comment('Saldo actual pendiente por cobrar');
            $table->date('due_date')->nullable()->comment('Fecha límite de pago');
            $table->enum('status', ['PENDIENTE', 'PAGADA', 'VENCIDA', 'ANULADA'])->default('PENDIENTE');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounts_receivable');
    }
};
