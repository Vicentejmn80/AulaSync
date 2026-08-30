<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class SpeechToTextService
{
    public function __construct(
        private DirectorCommandFocusService $commandFocus,
    ) {}

    public function transcribe(UploadedFile $audio, string $role = 'director'): string
    {
        $role = $role === 'profesor' ? 'profesor' : 'director';

        if (! $this->enabled()) {
            throw ValidationException::withMessages([
                'audio' => 'La transcripción de voz no está configurada. Revisa la clave de OpenAI.',
            ]);
        }

        $contents = $this->readAudio($audio);
        $filename = $audio->getClientOriginalName() ?: ('nota-voz.'.$this->guessExtension($audio));
        $raw = $this->callTranscription($contents, $filename, $role);
        $polished = $this->polishAndTranslate($raw, $role);

        if ($role === 'profesor') {
            return $polished;
        }

        $focus = $this->commandFocus->extract($polished);
        $working = trim((string) $focus['working']);

        return $working !== '' ? $working : $polished;
    }

    public function enabled(): bool
    {
        if (! config('services.openai.director_enabled', true)) {
            return false;
        }

        if (app()->environment('testing') && ! config('services.openai.director_test_enabled', false)) {
            return false;
        }

        $key = trim((string) config('services.openai.key'));

        return $key !== '' && ! str_contains($key, 'your_openai');
    }

    private function readAudio(UploadedFile $audio): string
    {
        $path = $audio->getRealPath() ?: $audio->getPathname();
        $contents = (is_string($path) && $path !== '' && is_readable($path))
            ? file_get_contents($path)
            : $audio->get();

        if (! is_string($contents) || $contents === '') {
            throw ValidationException::withMessages([
                'audio' => 'No pude leer el audio. Intenta grabar de nuevo.',
            ]);
        }

        return $contents;
    }

    private function callTranscription(string $contents, string $filename, string $role = 'director'): string
    {
        $models = array_values(array_unique(array_filter([
            (string) config('services.openai.whisper_model', 'gpt-4o-mini-transcribe'),
            'gpt-4o-mini-transcribe',
            'whisper-1',
        ])));

        $lastError = 'No pude transcribir la nota de voz. Intenta de nuevo.';

        foreach ($models as $model) {
            try {
                $payload = [
                    'model' => $model,
                    'language' => 'es',
                    'response_format' => 'json',
                    'prompt' => $this->vocabularyPrompt($role),
                ];
                if ($model === 'whisper-1') {
                    $payload['temperature'] = 0;
                }

                $response = Http::timeout(120)
                    ->withToken((string) config('services.openai.key'))
                    ->attach('file', $contents, $filename)
                    ->post('https://api.openai.com/v1/audio/transcriptions', $payload);

                if (! $response->successful()) {
                    $lastError = 'No pude transcribir la nota de voz. Intenta de nuevo.';
                    Log::warning('director.voice.transcribe_http', [
                        'model' => $model,
                        'status' => $response->status(),
                        'body' => mb_substr($response->body(), 0, 400),
                    ]);
                    continue;
                }

                $text = trim((string) $response->json('text'));
                if ($text !== '') {
                    return $text;
                }
            } catch (\Throwable $e) {
                $lastError = 'No pude transcribir la nota de voz. Intenta de nuevo.';
                Log::error('director.voice.transcribe_failed', [
                    'model' => $model,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        throw ValidationException::withMessages(['audio' => $lastError]);
    }

    private function polishAndTranslate(string $raw, string $role = 'director'): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            throw ValidationException::withMessages([
                'audio' => 'No escuché palabras claras. Graba de nuevo, un poco más cerca del micrófono.',
            ]);
        }

        if (! $this->enabled()) {
            return $this->normalizeSchoolSpanish($raw);
        }

        try {
            $response = Http::timeout(25)
                ->withToken((string) config('services.openai.key'))
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => (string) config('services.openai.director_model', 'gpt-4o-mini'),
                    'temperature' => 0.1,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => $this->polishPrompt($role),
                        ],
                        [
                            'role' => 'user',
                            'content' => $raw,
                        ],
                    ],
                ]);

            $polished = trim((string) $response->json('choices.0.message.content', ''));
            $polished = trim($polished, " \t\n\r\"'`");
            if ($polished !== '') {
                return $this->normalizeSchoolSpanish($polished);
            }
        } catch (\Throwable $e) {
            Log::debug('director.voice.polish_failed', ['error' => $e->getMessage()]);
        }

        return $this->normalizeSchoolSpanish($raw);
    }

    private function polishPrompt(string $role): string
    {
        if ($role === 'profesor') {
            return <<<PROMPT
Eres AulaSync, el asistente del docente. Recibes una transcripción de voz (español venezolano, a veces mezclado con inglés).
Devuelve SOLO el texto limpio, en español, listo para el chat. Sin comillas, sin explicación, sin markdown.

Reglas:
1. Traduce al español cualquier fragmento en inglés.
2. Corrige errores típicos de voz: 1ero/primer→1ro, 3ero/tercero→3ro, ingles→inglés, matematica→matemática, biologia→biología, planificacion→planificación, setiembre→septiembre.
3. Conserva materias, meses y días tal cual se pidieron (septiembre, lunes, martes, biología, 3ro).
4. Si pidió una planificación, un plan de clase, notas o asistencia, deja esa orden completa.
5. No inventes cursos, grados ni fechas que no estén en el audio.
PROMPT;
        }

        return <<<PROMPT
Eres AulaSync, el asistente del director. Recibes una transcripción de voz (español venezolano, a veces mezclado con inglés).
Devuelve SOLO el texto limpio, en español, listo para ejecutar. Sin comillas, sin explicación, sin markdown.

Reglas:
1. Traduce al español cualquier fragmento en inglés.
2. Corrige errores típicos de voz: 1ero/primer→1ro, 3ero→3ro, ingles→inglés, matematica→matemática, robotica→robótica, computacion→computación, religion→religión, fisica→física, biologia→biología.
3. Conserva nombres propios tal cual (Jorge Alarcón, Yovanny, etc.).
4. RESALTA las órdenes: si hay "necesito que", "quiero que", "crea", "modifica", "agrega", "elimina", "aumenta", "disminuye", "asigna", deja esas cláusulas completas y quita charla previa (saludos, "mira", "te cuento").
5. No inventes profesores, cursos ni grados que no estén en el audio.
6. NUNCA cambies "crea/crear/crees" por "tiene/hay". Si dijo crear, deja crear.
7. No agregues "va a dar" ni materias si el director no las dijo.
PROMPT;
    }

    private function vocabularyPrompt(string $role = 'director'): string
    {
        if ($role === 'profesor') {
            return 'Transcripción en español venezolano de un docente para AulaSync. '
                .'Vocabulario: planificación, plan de clase, evaluación, asistencia, notas, actividades, tareas, curso, materia, grado. '
                .'Materias: biología, matemática, inglés, lenguaje, ciencias, física, química. '
                .'Grados: 1ro, 2do, 3ro, 4to, 5to, 6to, tercero. '
                .'Tiempo: septiembre, octubre, lunes, martes, miércoles, jueves, viernes. '
                .'Frases: tírame una planificación, planifica, genera un plan, para el mes de septiembre.';
        }

        return 'Transcripción en español venezolano de un director de colegio para AulaSync. '
            .'Vocabulario: profesor, docente, alumno, curso, materia, grado, sección, invitación. '
            .'Materias: inglés, matemática, computación, robótica, biología, física, religión, lenguaje, ciencias. '
            .'Grados: 1ro, 2do, 3ro, 4to, 5to, 6to. Frases: necesito que, quiero que, crea, modifica, agrega, elimina, aumenta, disminuye, asígnale.';
    }

    private function normalizeSchoolSpanish(string $text): string
    {
        $replacements = [
            '/\b1ero\b/iu' => '1ro',
            '/\b1er\b/iu' => '1ro',
            '/\b3ero\b/iu' => '3ro',
            '/\bprimer grado\b/iu' => '1ro grado',
            '/\bingles\b/iu' => 'inglés',
            '/\bmatematicas?\b/iu' => 'matemática',
            '/\brobotica\b/iu' => 'robótica',
            '/\bcomputacion\b/iu' => 'computación',
            '/\breligion\b/iu' => 'religión',
            '/\bfisica\b/iu' => 'física',
            '/\bbiologia\b/iu' => 'biología',
            '/\btercero\b/iu' => '3ro',
            '/\bsetiembre\b/iu' => 'septiembre',
            '/\bplanificacion\b/iu' => 'planificación',
        ];

        foreach ($replacements as $pattern => $replacement) {
            $text = preg_replace($pattern, $replacement, $text) ?? $text;
        }

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    private function guessExtension(UploadedFile $audio): string
    {
        $mime = (string) $audio->getMimeType();

        return match (true) {
            str_contains($mime, 'webm') => 'webm',
            str_contains($mime, 'mp4'), str_contains($mime, 'm4a') => 'm4a',
            str_contains($mime, 'mpeg'), str_contains($mime, 'mp3') => 'mp3',
            str_contains($mime, 'ogg') => 'ogg',
            str_contains($mime, 'wav') => 'wav',
            default => 'webm',
        };
    }
}
