<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de {{ $module }}</title>
    <style>
        @page {
            margin: 1.5cm 1cm;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #334155;
            font-size: 11px;
            line-height: 1.4;
        }
        /* ENCABEZADO CORPORATIVO */
        .header-container {
            border-bottom: 2px solid #1e293b;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .company-title {
            font-size: 18px;
            font-weight: bold;
            color: #0f172a;
            text-transform: uppercase;
        }
        .report-subtitle {
            font-size: 12px;
            color: #64748b;
            margin-top: 2px;
        }
        .meta-info {
            float: right;
            text-align: right;
            font-size: 10px;
            color: #64748b;
            line-top: -30px;
        }
        .clear {
            clear: both;
        }
        /* TABLA DINÁMICA DE DATOS */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th {
            background-color: #1e293b;
            color: #ffffff;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 9px;
            padding: 8px 6px;
            text-align: left;
            border: 1px solid #1e293b;
        }
        td {
            padding: 6px;
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
            word-wrap: break-word;
        }
        tr:nth-child(even) {
            background-color: #f8fafc;
        }
        /* PIE DE PÁGINA NATIVO (DOMPDF) */
        .footer {
            position: fixed;
            bottom: -30px;
            left: 0;
            right: 0;
            height: 20px;
            text-align: center;
            font-size: 9px;
            color: #94a3b8;
            border-top: 1px solid #e2e8f0;
            padding-top: 5px;
        }
        .page-number:after {
            content: counter(page);
        }
    </style>
</head>
<body>

    <div class="header-container">
        <div class="meta-info">
            <strong>Fecha de Impresión:</strong> {{ $date }}<br>
            <strong>Estatus:</strong> Reporte Operacional
        </div>
        <div class="company-title">ERP GLOBAL</div>
        <div class="report-subtitle">Módulo: REPORTE GENERAL DE {{ $module }}</div>
        <div class="clear"></div>
    </div>

    <table>
        <thead>
            <tr>
                @foreach($headings as $heading)
                    <th>{{ $heading }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($data as $row)
                <tr>
                    @foreach($columns as $col)
                        <td>
                            @php
                                $val = data_get($row, $col, '—');
                            @endphp

                            @if(is_bool($val))
                                {{ $val ? 'SÍ' : 'NO' }}
                            @elseif($val === '')
                                —
                            @else
                                {{ $val }}
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endforeach
            @if(count($data) === 0)
                <tr>
                    <td colspan="{{ count($columns) }}" style="text-align: center; color: #94a3b8; padding: 20px;">
                        No existen registros disponibles para los criterios seleccionados.
                    </td>
                </tr>
            @endif
        </tbody>
    </table>

    <div class="footer">
        Sistema ERP - Documento confidencial de control interno - Página <span class="page-number"></span>
    </div>

</body>
</html>
