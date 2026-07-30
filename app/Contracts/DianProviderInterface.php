<?php

namespace App\Contracts;

use App\Models\Tenant\Sale;

interface DianProviderInterface
{
    /**
     * Emite la factura electrónica hacia el proveedor/DIAN.
     * Retorna un arreglo estándar con los datos de respuesta fiscal (CUFE, QR, PDF, etc.)
     *
     * @param Sale $sale
     * @return array
     */
    public function sendInvoice(Sale $sale): array;
}
