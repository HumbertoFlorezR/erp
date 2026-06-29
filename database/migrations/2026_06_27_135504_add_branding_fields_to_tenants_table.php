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
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('logo_url')->nullable()->after('id');
            // Almacenaremos códigos Hexadecimales (ej: #3B82F6 para azul)
            $table->string('primary_color')->default('#3b82f6')->after('logo_url');
            $table->string('secondary_color')->default('#1e293b')->after('primary_color');
            $table->string('company_name')->nullable()->after('secondary_color');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['logo_url', 'primary_color', 'secondary_color', 'company_name']);
        });
    }
};
