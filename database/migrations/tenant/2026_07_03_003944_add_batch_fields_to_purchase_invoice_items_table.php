<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_invoice_items', function (Blueprint $table) {
            // Añadimos los campos nullable después del product_id
            $table->string('batch_number')->nullable()->after('product_id');
            $table->date('expiration_date')->nullable()->after('batch_number');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_invoice_items', function (Blueprint $table) {
            // Por si algún día necesitas revertir esto
            $table->dropColumn(['batch_number', 'expiration_date']);
        });
    }
};
