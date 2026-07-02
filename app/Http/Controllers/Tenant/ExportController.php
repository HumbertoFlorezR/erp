<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\ExportPreference;
use App\Exports\DynamicExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use Barrier\Pdf\Facade as Pdf; // Alias para DomPDF

class ExportController extends Controller
{
    /**
     * Guardar o actualizar las preferencias de columnas del usuario para un módulo.
     */
    public function savePreferences(Request $request)
    {
        $request->validate([
            'module'           => 'required|string|max:50',
            'default_format'   => 'required|string|in:XLSX,CSV,PDF,TXT',
            'selected_columns' => 'required|array',
        ]);

        ExportPreference::updateOrCreate(
            [
                'user_id' => Auth::id(),
                'module'  => $request->module,
            ],
            [
                'default_format'   => $request->default_format,
                'selected_columns' => $request->selected_columns,
            ]
        );

        return redirect()->back()->with('success', 'Preferencias de exportación guardadas.');
    }

    /**
     * Motor de Exportación Multipropósito
     */
    public function export(Request $request)
    {
        $format    = $request->input('format', 'XLSX');
        $module    = $request->input('module');

        // 🛠️ DECODIFICAR EN ARRAYS NATIVOS SI LLEGAN COMO STRING DESDE EL FORMULARIO
        $columns   = $request->input('columns', []);
        $headings  = $request->input('headings', []);
        $rawData   = $request->input('data', []);

        if (is_string($columns)) {
            $columns = json_decode($columns, true) ?? [];
        }
        if (is_string($headings)) {
            $headings = json_decode($headings, true) ?? [];
        }
        if (is_string($rawData)) {
            $rawData = json_decode($rawData, true) ?? [];
        }

        // Convertir los datos decodificados en una colección
        $data = collect($rawData);

        // 2. Si el formato es un Archivo Plano (TXT), lo estructuramos manualmente
        if ($format === 'TXT') {
            $txtContent = implode("\t", $headings) . "\r\n"; // Encabezados separados por tabulador
            foreach ($data as $row) {
                $line = [];
                foreach ($columns as $col) {
                    $line[] = data_get($row, $col, '');
                }
                $txtContent .= implode("\t", $line) . "\r\n";
            }
            return response($txtContent)
                ->header('Content-Type', 'text/plain')
                ->header('Content-Disposition', "attachment; filename=\"reporte_{$module}_" . date('Ymd_His') . ".txt\"");
        }

        // 3. Si el formato es PDF, renderizamos una vista HTML limpia
        if ($format === 'PDF') {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('exports.generic_pdf', [
                'module'   => strtoupper($module),
                'headings' => $headings,
                'columns'  => $columns,
                'data'     => $data,
                'date'     => date('d/m/Y H:i')
            ])->setPaper('a4', 'landscape'); // Horizontal para que quepan bien las columnas

            return $pdf->download("reporte_{$module}_" . date('Ymd_His') . ".pdf");
        }

        // 4. Si el formato es Excel (XLSX) o CSV, usamos Laravel Excel
        $exportClass = new DynamicExport($data, $headings, $columns);
        $filename = "reporte_{$module}_" . date('Ymd_His');

        if ($format === 'CSV') {
            return Excel::download($exportClass, "{$filename}.csv", \Maatwebsite\Excel\Excel::CSV);
        }

        return Excel::download($exportClass, "{$filename}.xlsx", \Maatwebsite\Excel\Excel::XLSX);
    }
}
