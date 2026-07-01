<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    /**
     * Los atributos que se pueden asignar de forma masiva.
     */
    protected $fillable = [
        'type',
        'code',
        'name',
        'description',
        'average_cost',
        'last_purchase_price',
        'price_excluding_tax',
        'tax_rate',
        'tax_type',
        'stock',
        'minimum_stock',
        'manage_stock',
        'is_perishable',
        'unit_measure_code',
        'dian_code',
        'is_active',
    ];

    /**
     * Conversión de tipos nativos (Casting).
     */
    protected $casts = [
        'average_cost' => 'decimal:2',
        'last_purchase_price' => 'decimal:2',
        'price_excluding_tax' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'stock' => 'decimal:2',
        'minimum_stock' => 'decimal:2',
        'manage_stock' => 'boolean',
        'is_perishable' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Propiedades virtuales dinámicas que se incluirán automáticamente en JSON / Inertia.
     */
    protected $appends = [
        'is_low_stock',
        'profit_margin',
        'price_including_tax'
    ];

    /**
     * 🔔 Alerta Proactiva: Determina si el producto está en niveles críticos de inventario.
     */
    public function getIsLowStockAttribute(): bool
    {
        // Solo aplica si el ítem es un PRODUCTO y tiene activo el control de stock
        if ($this->type !== 'PRODUCTO' || !$this->manage_stock) {
            return false;
        }

        return $this->stock <= $this->minimum_stock;
    }

    /**
     * 📊 Margen de Utilidad Bruta Porcentual.
     * Calcula cuánto porcentaje le ganas al producto basado en su costo promedio.
     */
    public function getProfitMarginAttribute(): float
    {
        if ($this->price_excluding_tax <= 0) {
            return 0.00;
        }

        // Fórmula: ((Precio Venta - Costo Promedio) / Precio Venta) * 100
        $profit = $this->price_excluding_tax - $this->average_cost;
        return round(($profit / $this->price_excluding_tax) * 100, 2);
    }

    /**
     * 💵 Precio de Venta Final con el IVA incluido (Para visualización rápida en el ERP).
     */
    public function getPriceIncludingTaxAttribute(): float
    {
        if ($this->tax_type !== 'GRAVADO' || $this->tax_rate <= 0) {
            return (float) $this->price_excluding_tax;
        }

        $tax_amount = $this->price_excluding_tax * ($this->tax_rate / 100);
        return round($this->price_excluding_tax + $tax_amount, 2);
    }
}
