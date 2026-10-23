<?php

namespace App\Http\Controllers;

use App\Models\ExperienciaLead;
use App\Services\ExperienciaPrizeCatalog;
use App\Services\TelegramNotifier;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ExperienciaController extends Controller
{
    public function __construct(private readonly ExperienciaPrizeCatalog $prizes) {}

    /**
     * Pantalla de espera: el robot escribe en bucle.
     * El toque cruza a robot-saludando.mp4 desde la vista.
     */
    public function idle(): View
    {
        $kiosk = config('experiencia.kiosk');
        $kiosk['prizes'] = $this->prizes->publicCatalog();
        $kiosk['exclusivePrizes'] = (bool) config('experiencia.exclusive_prizes');
        $kiosk['spinUrl'] = route('experiencia.spin');
        $kiosk['contactUrl'] = route('experiencia.contacto');

        return view('experiencia.idle', [
            'typing' => $this->clip('idle'),
            'wave' => $this->clip('wave'),
            'celebrate' => $this->clip('celebrate'),
            'kiosk' => $kiosk,
            'exclusivePrizes' => (bool) config('experiencia.exclusive_prizes'),
        ]);
    }

    public function spin(): JsonResponse
    {
        $prize = $this->prizes->pick();
        $spinId = (string) Str::uuid();

        session([
            'experiencia_spin' => [
                'id' => $spinId,
                'prize_id' => $prize['id'],
                'created_at' => now()->toIso8601String(),
            ],
        ]);

        return response()->json([
            'ok' => true,
            'spin_id' => $spinId,
            'prize' => $this->prizes->present($prize),
        ]);
    }

    public function store(Request $request, TelegramNotifier $telegram): JsonResponse
    {
        $data = $request->validate([
            'spin_id' => ['required', 'uuid'],
            'nombre' => ['required', 'string', 'max:80'],
            'apellido' => ['nullable', 'string', 'max:80'],
            'email' => ['nullable', 'email', 'max:160'],
            'colegio' => ['required', 'string', 'max:160'],
            'cargo' => ['required', 'in:director,coordinador,docente,otro'],
            'telefono' => ['nullable', 'string', 'max:24'],
            'problema' => ['nullable', 'string', 'max:500'],
            'rol' => ['nullable', 'string', 'max:40'],
            'puntaje' => ['nullable', 'string', 'max:16'],
            'consentimiento' => ['accepted'],
        ]);

        $email = $this->blankToNull($data['email'] ?? null);
        $phone = $this->normalizePhone($data['telefono'] ?? null);
        $apellido = $this->blankToNull($data['apellido'] ?? null);
        $problema = $this->blankToNull($data['problema'] ?? null);

        if ($email === null && $phone === null) {
            return response()->json([
                'ok' => false,
                'message' => 'Indica un correo o un teléfono para poder entregarte el premio.',
            ], 422);
        }

        if ($phone !== null && preg_match('/^\+\d{8,18}$/', $phone) !== 1) {
            return response()->json([
                'ok' => false,
                'message' => 'Revisa el teléfono: incluye el código de país, por ejemplo +584141234567.',
            ], 422);
        }

        $spin = session('experiencia_spin');
        if (! is_array($spin) || ($spin['id'] ?? '') !== $data['spin_id']) {
            return response()->json([
                'ok' => false,
                'message' => 'El giro ya no está vigente. Gira la ruleta otra vez.',
            ], 422);
        }

        $createdAt = strtotime((string) ($spin['created_at'] ?? ''));
        if ($createdAt === false || $createdAt < now()->subMinutes(20)->getTimestamp()) {
            session()->forget('experiencia_spin');

            return response()->json([
                'ok' => false,
                'message' => 'El giro expiró. Gira la ruleta otra vez.',
            ], 422);
        }

        $prize = $this->prizes->find((string) ($spin['prize_id'] ?? ''));
        if ($prize === null) {
            return response()->json([
                'ok' => false,
                'message' => 'No encontramos el premio de este giro.',
            ], 422);
        }

        if (! $this->prizes->hasQuota($prize)) {
            session()->forget('experiencia_spin');

            return response()->json([
                'ok' => false,
                'message' => 'Ese premio agotó su cupo de hoy. Gira otra vez.',
            ], 409);
        }

        try {
            $existing = $this->recentDuplicate($email, $phone, $data['colegio']);
        } catch (QueryException $exception) {
            Log::warning('experiencia.lead.lookup_failed', ['error' => $exception->getMessage()]);

            return response()->json([
                'ok' => false,
                'message' => 'No pudimos guardar tu participación. La base de datos no está disponible.',
            ], 500);
        }

        if ($existing) {
            session()->forget('experiencia_spin');

            return response()->json([
                'ok' => true,
                'duplicate' => true,
                'codigo' => $existing->codigo,
                'premio' => $existing->premio_nombre,
                'notifications' => [
                    'email' => $existing->email_status,
                    'telegram' => $existing->telegram_status,
                ],
            ]);
        }

        $lead = new ExperienciaLead([
            'spin_id' => $data['spin_id'],
            'tablet_id' => (string) config('experiencia.kiosk.tabletId', 'tablet-1'),
            'rol' => $this->blankToNull($data['rol'] ?? null),
            'nombre' => trim($data['nombre']),
            'apellido' => $apellido,
            'email' => $email,
            'colegio' => trim($data['colegio']),
            'cargo' => $data['cargo'],
            'telefono' => $phone,
            'problema' => $problema,
            'premio_id' => $prize['id'],
            'premio_nombre' => $prize['name'],
            'premio_condicion' => $prize['condition'],
            'codigo' => $this->code(),
            'puntaje' => $this->blankToNull($data['puntaje'] ?? null),
            'consentimiento' => true,
            'email_status' => 'pending',
            'telegram_status' => 'pending',
            'webhook_status' => 'pending',
        ]);

        try {
            $lead->save();
        } catch (QueryException $exception) {
            $saved = ExperienciaLead::query()->where('spin_id', $data['spin_id'])->first();
            if ($saved) {
                session()->forget('experiencia_spin');

                return response()->json([
                    'ok' => true,
                    'duplicate' => true,
                    'codigo' => $saved->codigo,
                    'premio' => $saved->premio_nombre,
                    'notifications' => [
                        'email' => $saved->email_status,
                        'telegram' => $saved->telegram_status,
                    ],
                ]);
            }

            Log::warning('experiencia.lead.save_failed', ['error' => $exception->getMessage()]);

            return response()->json([
                'ok' => false,
                'message' => 'No pudimos guardar tu participación. Inténtalo de nuevo.',
            ], 500);
        }

        $lead->email_status = $this->notifyEmail($lead);
        $lead->telegram_status = $this->notifyTelegram($lead, $telegram);
        $lead->webhook_status = $this->notifyWebhook($lead);
        $lead->save();

        session()->forget('experiencia_spin');

        return response()->json([
            'ok' => true,
            'duplicate' => false,
            'codigo' => $lead->codigo,
            'premio' => $lead->premio_nombre,
            'condicion' => $lead->premio_condicion,
            'notifications' => [
                'email' => $lead->email_status,
                'telegram' => $lead->telegram_status,
                'webhook' => $lead->webhook_status,
            ],
        ]);
    }

    private function clip(string $base): ?string
    {
        return $this->publicVideoUrl(config('experiencia.'.$base.'_video'));
    }

    private function publicVideoUrl(mixed $relative): ?string
    {
        $path = $this->sanitizeRelativePath($relative);

        if ($path === null || ! is_file(public_path($path))) {
            return null;
        }

        return asset($path).'?v='.filemtime(public_path($path));
    }

    private function sanitizeRelativePath(mixed $relative): ?string
    {
        if (! is_string($relative)) {
            return null;
        }

        $relative = ltrim(trim($relative), '/');

        if ($relative === '' || str_contains($relative, '..') || str_contains($relative, '\\')) {
            return null;
        }

        return $relative;
    }

    private function recentDuplicate(?string $email, ?string $phone, string $colegio): ?ExperienciaLead
    {
        $query = ExperienciaLead::query()->where('created_at', '>=', now()->subMinutes(15));

        if ($email !== null) {
            return (clone $query)->where('email', $email)->first();
        }

        return $query->where('telefono', $phone)->where('colegio', $colegio)->first();
    }

    private function notifyEmail(ExperienciaLead $lead): string
    {
        $to = trim((string) config('experiencia.notify_email'));
        if ($to === '') {
            return 'skipped';
        }

        $nombre = trim($lead->nombre.' '.($lead->apellido ?? ''));
        $body = implode("\n", [
            'Nuevo contacto de la experiencia AulaSync',
            'Nombre: '.$nombre,
            'Correo: '.($lead->email ?: '—'),
            'Teléfono: '.($lead->telefono ?: '—'),
            'Colegio: '.$lead->colegio,
            'Cargo: '.$lead->cargo,
            'Problema: '.($lead->problema ?: '—'),
            'Premio: '.$lead->premio_nombre,
            'Código: '.$lead->codigo,
            'Fecha: '.$lead->created_at?->toIso8601String(),
        ]);

        try {
            Mail::raw($body, function ($message) use ($to, $nombre): void {
                $message->to($to)->subject('Experiencia AulaSync: '.$nombre);
            });

            return 'sent';
        } catch (\Throwable $exception) {
            Log::warning('experiencia.notify.email_failed', ['error' => $exception->getMessage()]);

            return 'failed';
        }
    }

    private function notifyTelegram(ExperienciaLead $lead, TelegramNotifier $telegram): string
    {
        if (! $telegram->isEnabled()) {
            return 'skipped';
        }

        $nombre = trim($lead->nombre.' '.($lead->apellido ?? ''));
        $text = implode("\n", [
            '<b>Nuevo contacto · experiencia</b>',
            e($nombre).' · '.e($lead->cargo),
            e($lead->colegio),
            'Premio: '.e($lead->premio_nombre),
            'Contacto: '.e($lead->email ?: $lead->telefono ?: '—'),
            'Problema: '.e($lead->problema ?: '—'),
            'Código: '.e($lead->codigo),
        ]);

        return $telegram->sendMessage($text) ? 'sent' : 'failed';
    }

    private function notifyWebhook(ExperienciaLead $lead): string
    {
        $url = trim((string) config('experiencia.kiosk.webhookUrl'));
        if ($url === '') {
            return 'skipped';
        }

        try {
            $response = Http::timeout(8)
                ->withBody(json_encode([
                    'createdAt' => $lead->created_at?->toIso8601String(),
                    'tabletId' => $lead->tablet_id,
                    'rol' => $lead->rol,
                    'nombre' => trim($lead->nombre.' '.($lead->apellido ?? '')),
                    'apellido' => $lead->apellido,
                    'email' => $lead->email,
                    'colegio' => $lead->colegio,
                    'cargo' => $lead->cargo,
                    'whatsapp' => $lead->telefono,
                    'problema' => $lead->problema,
                    'reto_principal' => $lead->problema,
                    'puntaje' => $lead->puntaje,
                    'premio' => $lead->premio_nombre,
                    'premio_id' => $lead->premio_id,
                    'codigo' => $lead->codigo,
                    'consentimiento' => 'si',
                ], JSON_UNESCAPED_UNICODE), 'text/plain')
                ->post($url);

            return $response->successful() ? 'sent' : 'failed';
        } catch (\Throwable $exception) {
            Log::warning('experiencia.notify.webhook_failed', ['error' => $exception->getMessage()]);

            return 'failed';
        }
    }

    private function code(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $value = 'AULA-';
        for ($i = 0; $i < 4; $i++) {
            $value .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $value;
    }

    private function blankToNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function normalizePhone(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = preg_replace('/[^\d+]/', '', trim($value)) ?? '';

        return $value === '' || $value === '+' ? null : $value;
    }
}
