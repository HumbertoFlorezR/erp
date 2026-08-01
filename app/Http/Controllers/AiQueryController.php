<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class AiQueryController extends Controller
{
    public function processQuery($tenant, Request $request)
    {
        $request->validate([
            'prompt' => 'required|string|max:500'
        ]);

        $userPrompt = $request->input('prompt');

        // 1. Proporcionar el esquema de la BD al LLM (solo tablas públicas/seguras)
        $schemaDescription = "
            Tablas disponibles:
            - products(id, name, sku, current_stock, min_stock, price)
            - sales(id, invoice_number, total, created_at, user_id, contact_id)
            - sale_details(id, sale_id, product_id, quantity, price)
            - sale_payments(id, sale_id, amount, payment_method, created_at)
            - accounts_receivable(id, amount_due, due_date, contact_id)
            - purchase_invoices(id, invoice_number, total, created_at, user_id, contact_id)
            - purchase_invoice_items(id, purchase_invoice_id, product_id, quantity, price)
            - contacts(id, name, type) -- type: client, provider
        ";

        // 2. Solicitar una consulta SQL segura (o JSON estructurado) al modelo IA
        // Ejemplo usando OpenAI/Gemini/DeepSeek API:
        $aiResponse = Http::withHeaders([
            'Authorization' => 'Bearer ' . env('OPENAI_API_KEY'),
        ])->post('https://api.openai.com/v1/chat/completions', [
            'model' => 'gpt-4o-mini',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => "Eres un asistente SQL experto. Genera ÚNICAMENTE una consulta MySQL de lectura (SELECT). " .
                                 "Restricciones: Solo SELECT, max 20 resultados. " .
                                 "Responde estrictamente en formato JSON con la clave 'sql', 'label_column' y 'value_column' para el gráfico." .
                                 "Esquema: " . $schemaDescription
                ],
                ['role' => 'user', 'content' => $userPrompt]
            ],
            'response_format' => ['type' => 'json_object']
        ]);

        $aiJson = json_decode($aiResponse->json('choices.0.message.content'), true);

        $sql = $aiJson['sql'] ?? null;

        // Validar seguridad del SQL (evitar DROP, UPDATE, DELETE, INSERT)
        if (!$sql || !str_starts_with(strtoupper(trim($sql)), 'SELECT')) {
            return response()->json(['message' => 'Consulta no permitida por razones de seguridad.'], 422);
        }

        // 3. Ejecutar la consulta SQL de manera segura en la BD
        $results = DB::select(DB::raw($sql));
        $rows = json_decode(json_encode($results), true);

        if (empty($rows)) {
            return response()->json(['message' => 'No se encontraron resultados para la consulta.'], 404);
        }

        $columns = array_keys($rows[0]);

        // 4. Preparar estructura de datos para la vista Vue
        $labelCol = $aiJson['label_column'] ?? $columns[0];
        $valueCol = $aiJson['value_column'] ?? ($columns[1] ?? $columns[0]);

        return response()->json([
            'columns' => $columns,
            'rows' => $rows,
            'chart' => [
                'type' => 'bar', // bar, line, pie
                'title' => 'Resultado de: ' . $userPrompt,
                'labels' => array_column($rows, $labelCol),
                'data' => array_column($rows, $valueCol),
            ]
        ]);
    }
}
