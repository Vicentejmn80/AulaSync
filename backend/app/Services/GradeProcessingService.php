<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GradeProcessingService
{
    private string $apiKey;
    private string $model;

    public function __construct()
    {
        $this->apiKey = config('services.openai.key', env('OPENAI_API_KEY', ''));
        $this->model  = 'gpt-5.1 mini  ';
    }

    /**
     * Parse a free-text or voice-transcript string and return structured grades.
     *
     * @param  string  $rawInput  Text or voice transcription from the teacher.
     * @param  int     $maxScore  Maximum score for the activity (used for validation).
     * @return array<int, array{student_name: string, score: float}>
     *
     * @throws \RuntimeException  When the API call fails or the response is not parseable.
     */
    public function parseGradesFromText(string $rawInput, int $maxScore = 20): array
    {
        $systemPrompt = <<<PROMPT
Eres un asistente de evaluación escolar. Tu única tarea es extraer nombres de alumnos y sus calificaciones
a partir del texto o transcripción de voz del profesor.

Reglas estrictas:
1. Devuelve ÚNICAMENTE un array JSON válido, sin texto adicional, sin markdown.
2. Cada elemento debe tener exactamente dos claves: "student_name" (string) y "score" (number).
3. Si el puntaje supera {$maxScore}, márcalo como {$maxScore}.
4. Si no encuentras calificaciones, devuelve un array vacío: [].
5. No inventes alumnos ni calificaciones que no estén en el texto.

Ejemplo de salida esperada:
[{"student_name":"María López","score":18},{"student_name":"Juan Pérez","score":15.5}]
PROMPT;

        $response = Http::withToken($this->apiKey)
            ->timeout(30)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model'       => $this->model,
                'temperature' => 0,
                'messages'    => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user',   'content' => $rawInput],
                ],
            ]);

        if ($response->failed()) {
            Log::error('GradeProcessingService: OpenAI request failed', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            throw new \RuntimeException('El asistente de IA no está disponible en este momento.');
        }

        $content = $response->json('choices.0.message.content', '[]');

        $parsed = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($parsed)) {
            Log::warning('GradeProcessingService: non-JSON response', ['raw' => $content]);
            throw new \RuntimeException('La IA devolvió una respuesta en formato inesperado.');
        }

        return array_map(fn ($item) => [
            'student_name' => (string) ($item['student_name'] ?? ''),
            'score'        => (float)  ($item['score']        ?? 0),
        ], $parsed);
    }
}
