<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\CommunicationAnnouncement;
use App\Models\CommunicationAnnouncementRead;
use App\Models\CommunicationMessage;
use App\Models\CommunicationThread;
use App\Models\Course;
use App\Models\Student;
use App\Models\Notification;
use App\Services\AttendanceAlertService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class CommunicationController extends Controller
{
    public function index(): View
    {
        $teacher = auth()->user();
        $courses = Course::where('teacher_id', $teacher->id)
            ->withCount('students')
            ->orderBy('subject_name')
            ->get(['id', 'subject_name', 'grade', 'section']);

        $students = Student::query()
            ->where(function ($q) use ($teacher) {
                $q->where('teacher_id', $teacher->id)
                    ->orWhereHas('courses', fn ($c) => $c->where('teacher_id', $teacher->id));
            })
            ->orderBy('name')
            ->get(['id', 'name', 'grade', 'section']);

        $announcements = collect();
        $threads = collect();

        try {
            if ($this->communicationTablesReady()) {
                $this->ensureThreads($teacher->id, $students);

                $announcements = CommunicationAnnouncement::where('teacher_id', $teacher->id)
                    ->withCount([
                        'reads as recipients_count',
                        'reads as read_count' => fn ($q) => $q->whereNotNull('read_at'),
                    ])
                    ->latest()
                    ->limit(30)
                    ->get();

                $threads = $this->threadPayloads($teacher->id);
            }
        } catch (QueryException $e) {
            Log::warning('Communication index skipped due to missing schema: ' . $e->getMessage());
        }

        return view('teacher.communication.index', compact(
            'teacher',
            'courses',
            'students',
            'announcements',
            'threads'
        ));
    }

    public function generateAnnouncement(Request $request): JsonResponse
    {
        $data = $request->validate([
            'idea' => 'required|string|min:8|max:1200',
            'audience' => 'nullable|string|max:180',
            'tone' => 'nullable|in:formal,cercano,institucional',
        ]);

        $apiKey = config('services.openai.key');
        if (empty($apiKey)) {
            return response()->json([
                'success' => true,
                'result' => [
                    'title' => 'Comunicado importante',
                    'body' => "Estimadas familias y estudiantes:\n\n{$data['idea']}\n\nAgradecemos su atención.",
                ],
                'note' => 'Se generó en modo básico porque OPENAI_API_KEY no está configurada.',
            ]);
        }

        $tone = $data['tone'] ?? 'formal';
        $prompt = "Redacta un anuncio escolar profesional en español. "
            . "Idea base: {$data['idea']}. Audiencia: " . ($data['audience'] ?: 'familias y estudiantes')
            . ". Tono: {$tone}. "
            . 'Devuelve JSON {"title":"","body":""} con texto claro, breve y accionable.';

        try {
            $response = Http::withToken($apiKey)
                ->timeout(45)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => 'gpt-4o-mini',
                    'temperature' => 0.35,
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        ['role' => 'system', 'content' => 'Eres coordinador académico experto en comunicación escolar. Responde solo JSON válido.'],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                ]);

            if (! $response->successful()) {
                return response()->json(['success' => false, 'error' => 'No se pudo generar el anuncio.'], 200);
            }

            $payload = json_decode((string) data_get($response->json(), 'choices.0.message.content', '{}'), true);
            if (! is_array($payload) || empty($payload['body'])) {
                return response()->json(['success' => false, 'error' => 'Formato de respuesta inválido.'], 200);
            }

            return response()->json(['success' => true, 'result' => $payload]);
        } catch (\Throwable $e) {
            Log::error('Communication announcement generation error: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Error al contactar la IA.'], 200);
        }
    }

    public function storeAnnouncement(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'course_id' => 'nullable|integer',
            'section' => 'nullable|string|max:20',
            'audience_type' => 'nullable|string|max:30',
            'smart_segment' => 'nullable|in:none,pending_tasks,low_score',
            'schedule_at' => 'nullable|date',
            'drive_link' => 'nullable|url|max:600',
            'files.*' => 'file|max:8192',
        ]);

        $teacher = auth()->user();
        $course = null;
        if (! empty($data['course_id'])) {
            $course = Course::where('id', $data['course_id'])->where('teacher_id', $teacher->id)->firstOrFail();
        }

        $attachments = [];
        if ($request->hasFile('files')) {
            foreach ((array) $request->file('files') as $file) {
                $path = $file->store('communication-attachments', 'public');
                $attachments[] = [
                    'type' => 'file',
                    'name' => $file->getClientOriginalName(),
                    'path' => $path,
                ];
            }
        }
        if (! empty($data['drive_link'])) {
            $attachments[] = [
                'type' => 'drive',
                'name' => 'Google Drive',
                'url' => $data['drive_link'],
            ];
        }

        $scheduledAt = ! empty($data['schedule_at']) ? Carbon::parse($data['schedule_at']) : null;
        $status = $scheduledAt && $scheduledAt->isFuture() ? 'scheduled' : 'sent';

        $announcement = CommunicationAnnouncement::create([
            'teacher_id' => $teacher->id,
            'colegio_id' => $teacher->colegio_id,
            'title' => $data['title'],
            'body' => $data['body'],
            'targeting' => [
                'course_id' => $data['course_id'] ?? null,
                'section' => $data['section'] ?? null,
                'audience_type' => $data['audience_type'] ?? 'students',
                'smart_segment' => $data['smart_segment'] ?? 'none',
            ],
            'attachments' => $attachments,
            'scheduled_at' => $scheduledAt,
            'sent_at' => $status === 'sent' ? now() : null,
            'status' => $status,
        ]);

        $recipients = $this->resolveRecipients($teacher->id, $course, $data['smart_segment'] ?? 'none');
        foreach ($recipients as $recipient) {
            CommunicationAnnouncementRead::create([
                'announcement_id' => $announcement->id,
                'student_id' => $recipient['student_id'],
                'recipient_name' => $recipient['name'],
                'recipient_type' => $recipient['type'],
            ]);
        }

        $announcement->loadCount([
            'reads as recipients_count',
            'reads as read_count' => fn ($q) => $q->whereNotNull('read_at'),
        ]);

        return response()->json(['success' => true, 'announcement' => $announcement]);
    }

    public function markReadDemo(CommunicationAnnouncement $announcement): JsonResponse
    {
        $this->authorizeAnnouncement($announcement);
        $read = $announcement->reads()->whereNull('read_at')->first();
        if ($read) {
            $read->update(['read_at' => now()]);
        }

        $announcement->loadCount([
            'reads as recipients_count',
            'reads as read_count' => fn ($q) => $q->whereNotNull('read_at'),
        ]);

        return response()->json(['success' => true, 'announcement' => $announcement]);
    }

    public function threads(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'threads' => $this->threadPayloads(auth()->id()),
        ]);
    }

    public function sendMessage(Request $request, CommunicationThread $thread): JsonResponse
    {
        $this->authorizeThread($thread);
        $data = $request->validate([
            'body' => 'required|string|min:1|max:3000',
            'ai_suggested' => 'nullable|boolean',
        ]);

        $message = $thread->messages()->create([
            'sender_role' => 'teacher',
            'body' => $data['body'],
            'ai_suggested' => (bool) ($data['ai_suggested'] ?? false),
        ]);

        $thread->update([
            'last_message_preview' => mb_substr($data['body'], 0, 160),
            'last_message_at' => now(),
        ]);

        if ($thread->student && Schema::hasTable('notifications')) {
            $parents = app(AttendanceAlertService::class)->parentsFor($thread->student);
            foreach ($parents as $parent) {
                Notification::create([
                    'user_id' => $parent->id,
                    'colegio_id' => $thread->student->colegio_id,
                    'title' => 'Nuevo mensaje del docente',
                    'message' => auth()->user()->name.' respondió sobre '.$thread->student->name.': '.mb_substr($data['body'], 0, 120),
                    'link' => route('representante.dashboard'),
                ]);
            }
        }

        return response()->json(['success' => true, 'message' => $message]);
    }

    public function simulateIncoming(CommunicationThread $thread): JsonResponse
    {
        $this->authorizeThread($thread);
        $body = 'Hola profe, buenas tardes. Quería confirmar la fecha de la próxima evaluación y qué tema entra.';
        $message = $thread->messages()->create([
            'sender_role' => 'student',
            'body' => $body,
        ]);
        $thread->update([
            'last_message_preview' => mb_substr($body, 0, 160),
            'last_message_at' => now(),
        ]);
        return response()->json(['success' => true, 'message' => $message]);
    }

    public function suggestQuickReply(Request $request, CommunicationThread $thread): JsonResponse
    {
        $this->authorizeThread($thread);
        $data = $request->validate([
            'incoming' => 'nullable|string|max:3000',
        ]);

        $incoming = $data['incoming'] ?: (string) optional($thread->messages()->latest()->first())->body;
        if ($incoming === '') {
            return response()->json(['success' => true, 'suggestions' => [
                'Gracias por escribir. Te confirmo esta información en breve.',
                'La evaluación está publicada en AulaSync y te comparto los detalles ahora.',
            ]]);
        }

        $apiKey = config('services.openai.key');
        if (empty($apiKey)) {
            return response()->json(['success' => true, 'suggestions' => $this->fallbackSuggestions($incoming)]);
        }

        $student = $thread->student;
        $avg = $student ? round((float) $student->grades()->avg('score'), 1) : null;
        $prompt = "Mensaje recibido: {$incoming}\n"
            . 'Contexto estudiante: ' . ($student?->name ?: $thread->contact_name)
            . ', promedio: ' . ($avg !== null ? $avg : 'sin datos') . ".\n"
            . 'Genera 3 respuestas cortas, empáticas y profesionales para docente. JSON {"suggestions":["","",""]}.';

        try {
            $response = Http::withToken($apiKey)
                ->timeout(35)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => 'gpt-4o-mini',
                    'temperature' => 0.35,
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        ['role' => 'system', 'content' => 'Eres asistente de comunicación escolar. Responde solo JSON válido.'],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                ]);

            if (! $response->successful()) {
                return response()->json(['success' => true, 'suggestions' => $this->fallbackSuggestions($incoming)]);
            }
            $payload = json_decode((string) data_get($response->json(), 'choices.0.message.content', '{}'), true);
            $suggestions = array_values(array_filter((array) data_get($payload, 'suggestions', [])));
            if (count($suggestions) === 0) {
                $suggestions = $this->fallbackSuggestions($incoming);
            }
            return response()->json(['success' => true, 'suggestions' => array_slice($suggestions, 0, 3)]);
        } catch (\Throwable $e) {
            return response()->json(['success' => true, 'suggestions' => $this->fallbackSuggestions($incoming)]);
        }
    }

    private function threadPayloads(int $teacherId)
    {
        if (! Schema::hasTable('communication_threads')) {
            return collect();
        }

        return CommunicationThread::where('teacher_id', $teacherId)
            ->with(['student:id,name,grade,section,colegio_id', 'messages' => fn ($q) => $q->latest()->limit(40)])
            ->orderByDesc('last_message_at')
            ->limit(40)
            ->get()
            ->map(function (CommunicationThread $thread) {
                $avg = $thread->student
                    ? round((float) $thread->student->grades()->avg('score'), 1)
                    : null;
                $label = $thread->student?->name ?? $thread->contact_name;
                if ($thread->contact_role === 'representante') {
                    $label = ($thread->contact_name ?: 'Familia').' · '.$thread->student?->name;
                }

                return [
                    'id' => $thread->id,
                    'contact_name' => $label,
                    'contact_role' => $thread->contact_role,
                    'last_message_preview' => $thread->last_message_preview,
                    'last_message_at' => optional($thread->last_message_at)->toDateTimeString(),
                    'student' => $thread->student,
                    'student_avg' => $avg,
                    'messages' => $thread->messages->sortBy('created_at')->values(),
                ];
            })
            ->values();
    }

    private function authorizeAnnouncement(CommunicationAnnouncement $announcement): void
    {
        abort_unless($announcement->teacher_id === auth()->id(), 403);
    }

    private function authorizeThread(CommunicationThread $thread): void
    {
        abort_unless($thread->teacher_id === auth()->id(), 403);
    }

    private function communicationTablesReady(): bool
    {
        return Schema::hasTable('communication_threads')
            && Schema::hasTable('communication_announcements');
    }

    private function ensureThreads(int $teacherId, $students): void
    {
        if (! Schema::hasTable('communication_threads')) {
            return;
        }

        foreach ($students->take(10) as $student) {
            CommunicationThread::firstOrCreate(
                ['teacher_id' => $teacherId, 'student_id' => $student->id],
                [
                    'contact_name' => $student->name,
                    'contact_role' => 'estudiante',
                    'last_message_preview' => 'Hilo iniciado para seguimiento académico.',
                    'last_message_at' => now(),
                ]
            );
        }
    }

    private function resolveRecipients(int $teacherId, ?Course $course, string $segment): array
    {
        $query = Student::query()->where('teacher_id', $teacherId);
        if ($course) {
            $query->whereHas('courses', fn ($q) => $q->where('courses.id', $course->id));
        }
        if ($segment === 'low_score') {
            $query->whereHas('grades', fn ($q) => $q->where('score', '<', 12));
        }
        if ($segment === 'pending_tasks') {
            $query->whereHas('grades', fn ($q) => $q->whereNull('published_at'));
        }

        return $query->limit(120)->get(['id', 'name'])->map(fn ($s) => [
            'student_id' => $s->id,
            'name' => $s->name,
            'type' => 'student',
        ])->all();
    }

    private function fallbackSuggestions(string $incoming): array
    {
        $lower = mb_strtolower($incoming);
        if (str_contains($lower, 'examen') || str_contains($lower, 'evaluaci')) {
            return [
                'La evaluación está programada para la próxima semana y compartiré la guía hoy por AulaSync.',
                'Gracias por consultar. En breve te envío temario y fecha exacta por este mismo canal.',
                'La fecha tentativa es el viernes; si hay cambios se notificará por circular oficial.',
            ];
        }
        if (str_contains($lower, 'nota') || str_contains($lower, 'calific')) {
            return [
                'Ya estoy revisando las calificaciones y estarán visibles en AulaSync al finalizar la jornada.',
                'Gracias por tu mensaje. Te confirmo la nota apenas cierre el proceso de revisión.',
                'Puedes revisar avances en el panel del estudiante; hoy actualizaré comentarios de desempeño.',
            ];
        }
        return [
            'Gracias por escribir. Confirmo esta información y te respondo en breve.',
            'Perfecto, lo reviso ahora y te doy respuesta por este chat.',
            'Recibido. Te comparto el detalle completo en un momento.',
        ];
    }
}

