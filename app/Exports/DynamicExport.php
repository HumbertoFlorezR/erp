<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Illuminate\Support\Collection;

class DynamicExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    protected $collection;
    protected $headings;
    protected $columns;

    /**
     * @param Collection $collection Datos ya filtrados y transformados
     * @param array $headings Nombres de las columnas visibles para el usuario (ej: ['Código', 'Nombre'])
     * @param array $columns Atributos reales del modelo (ej: ['code', 'name'])
     */
    public function __construct(Collection $collection, array $headings, array $columns)
    {
        $this->collection = $collection;
        $this->headings = $headings;
        $this->columns = $columns;
    }

    public function collection()
    {
        return $this->collection;
    }

    public function headings(): array
    {
        return $this->headings;
    }

    /**
     * Mapea dinámicamente cada fila usando solo los atributos seleccionados
     */
    public function map($row): array
    {
        $mappedRow = [];

        foreach ($this->columns as $column) {
            // Soporta sub-propiedades si fuera necesario (ej: 'tax.name')
            $mappedRow[] = data_get($row, $column);
        }

        return $mappedRow;
    }

    /**
     * Estilos visuales por defecto para que el Excel no sea plano
     */
    public function styles(Worksheet $sheet)
    {
        return [
            // Fila 1 (Encabezados) en Negrita, Texto Blanco y Fondo Azul Corporativo Muted
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '1E293B'] // slate-800 de Tailwind
                ]
            ],
        ];
    }
}
