<?php

namespace App\Services\Dian;

use App\Contracts\DianProviderInterface;
use App\Models\Tenant\Sale;

class NullDianDriver implements DianProviderInterface
{
    public function sendInvoice(Sale $sale): array
    {
        // Simulación mientras se integra un proveedor real (Factus, Siigo, Sovos, etc.)
        return [
            'status'  => 'SIMULATED',
            'cufe'    => 'SIMULATED-CUFE-' . $sale->invoice_number . '-' . strtoupper(uniqid()),
            'qr'      => 'https://api.dian.gov.co/document/search?cufe=SIMULATED',
            'xml_url' => null,
            'pdf_url' => null,
            'message' => 'Factura simulada correctamente (Modo Desarrollo/Pruebas).'
        ];
    }
}
