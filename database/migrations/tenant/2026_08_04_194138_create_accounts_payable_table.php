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
        Schema::create('accounts_payable', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_invoice_id')->constrained('purchase_invoices');
            $table->foreignId('provider_id')->constrained('contacts');
            $table->decimal('original_amount', 14, 2);
            $table->decimal('balance', 14, 2);
            $table->date('due_date');
            $table->enum('status', ['PENDIENTE', 'PAGADA', 'ANULADA'])->default('PENDIENTE');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts_payable');
    }
};
