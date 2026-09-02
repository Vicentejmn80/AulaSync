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
use App\Support\DatabaseBoolean;
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
        $contacts = collect();

        try {
            if ($this->communicationTablesReady()) {
                $announcements = CommunicationAnnouncement::where('teacher_id', $teacher->id)
                    ->withCount([
                        'reads as recipients_count',
                        'reads as read_count' => fn ($q) => $q->whereNotNull('read_at'),
                    ])
                    ->latest()
                    ->limit(30)
                    ->get();

                $threads = $this->threadPayloads($teacher->id);
                $contacts = $this->contactPayloads($teacher);
            }
        } catch (QueryException $e) {
            Log::warning('Communication index skipped due to missing schema: ' . $e->getMessage());
        }

        return view('teacher.communication.index', compact(
            'teacher',
            'courses',
            'students',
            'announcements',
            'threads',
            'contacts'
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

    public function contacts(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'contacts' => $this->contactPayloads(auth()->user()),
        ]);
    }

    public function startThread(Request $request): JsonResponse
    {
        $data = $request->validate([
            'student_id' => 'required|integer',
            'body' => 'required|string|min:1|max:4000',
        ]);

        $student = $this->authorizeStudentForTeacher((int) $data['student_id']);
        $parents = app(AttendanceAlertService::class)->parentsFor($student);
        if ($parents->isEmpty()) {
            return response()->json([
                'success' => false,
                'error' => 'Este alumno aún no tiene un representante vinculado.',
            ], 422);
        }

        $thread = CommunicationThread::query()->firstOrCreate(
            [
                'teacher_id' => auth()->id(),
                'student_id' => $student->id,
            ],
            [
                'contact_name' => $parents->first()->name,
                'contact_role' => 'representante',
            ]
        );

        $thread->fill([
            'contact_name' => $parents->first()->name,
            'contact_role' => 'representante',
        ])->save();

        $message = $this->storeTeacherMessage($thread, $data['body']);

        return response()->json([
            'success' => true,
            'message' => $this->presentMessage($message),
            'thread' => $this->presentThread($thread->fresh(['student', 'messages'])),
        ]);
    }

    public function sendMessage(Request $request, CommunicationThread $thread): JsonResponse
    {
        $this->authorizeThread($thread);
        $data = $request->validate([
            'body' => 'required|string|min:1|max:4000',
            'ai_suggested' => 'nullable|boolean',
        ]);

        $message = $this->storeTeacherMessage($thread, $data['body'], (bool) ($data['ai_suggested'] ?? false));

        return response()->json([
            'success' => true,
            'message' => $this->presentMessage($message),
            'thread' => $this->presentThread($thread->fresh(['student', 'messages'])),
        ]);
    }

    public function markThreadRead(CommunicationThread $thread): JsonResponse
    {
        $this->authorizeThread($thread);
        $thread->messages()
            ->whereNull('read_at')
            ->where('sender_role', '!=', 'teacher')
            ->update(['read_at' => now()]);

        return response()->json(['success' => true]);
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
            ->whereHas('messages')
            ->with(['student:id,name,grade,section,colegio_id', 'messages' => fn ($q) => $q->latest()->limit(80)])
            ->withCount([
                'messages as unread_count' => fn ($q) => $q
                    ->whereNull('read_at')
                    ->where('sender_role', '!=', 'teacher'),
            ])
            ->orderByDesc('last_message_at')
            ->limit(80)
            ->get()
            ->map(fn (CommunicationThread $thread) => $this->presentThread($thread))
            ->values();
    }

    private function presentThread(CommunicationThread $thread): array
    {
        $thread->loadMissing(['student:id,name,grade,section,colegio_id']);
        if (! $thread->relationLoaded('messages')) {
            $thread->load(['messages' => fn ($q) => $q->orderBy('created_at')->limit(80)]);
        }

        $avg = $thread->student
            ? round((float) $thread->student->grades()->avg('score'), 1)
            : null;
        $isFamily = in_array($thread->contact_role, ['representante', 'parent'], true);
        $label = $thread->student?->name ?? $thread->contact_name;
        if ($isFamily) {
            $label = ($thread->contact_name ?: 'Familia').' · '.$thread->student?->name;
        }

        $unread = (int) ($thread->unread_count ?? $thread->messages
            ->whereNull('read_at')
            ->where('sender_role', '!=', 'teacher')
            ->count());

        return [
            'id' => $thread->id,
            'contact_name' => $label,
            'contact_role' => $thread->contact_role,
            'is_family' => $isFamily,
            'last_message_preview' => $thread->last_message_preview,
            'last_message_at' => optional($thread->last_message_at)->toIso8601String(),
            'unread' => $unread,
            'student' => $thread->student,
            'student_id' => $thread->student_id,
            'student_avg' => $avg,
            'messages' => $thread->messages->sortBy('created_at')->values()->map(fn (CommunicationMessage $m) => $this->presentMessage($m))->values(),
        ];
    }

    private function presentMessage(CommunicationMessage $message): array
    {
        return [
            'id' => $message->id,
            'sender_role' => $message->sender_role,
            'body' => $message->body,
            'ai_suggested' => (bool) $message->ai_suggested,
            'created_at' => optional($message->created_at)->toIso8601String(),
        ];
    }

    private function storeTeacherMessage(CommunicationThread $thread, string $body, bool $aiSuggested = false): CommunicationMessage
    {
        $message = $thread->messages()->create([
            'sender_role' => 'teacher',
            'body' => $body,
            'ai_suggested' => DatabaseBoolean::bind($aiSuggested),
        ]);

        $message->refresh();

        $thread->loadMissing('student');
        $parents = $thread->student
            ? app(AttendanceAlertService::class)->parentsFor($thread->student)
            : collect();

        $thread->update([
            'last_message_preview' => mb_substr($body, 0, 160),
            'last_message_at' => now(),
            'contact_name' => $parents->first()?->name ?: ($thread->contact_name ?: $thread->student?->name),
            'contact_role' => $parents->isNotEmpty() ? 'representante' : ($thread->contact_role ?: 'estudiante'),
        ]);

        if ($thread->student && Schema::hasTable('notifications')) {
            foreach ($parents as $parent) {
                Notification::create([
                    'user_id' => $parent->id,
                    'colegio_id' => $thread->student->colegio_id,
                    'title' => 'Nuevo mensaje del docente',
                    'message' => auth()->user()->name.' escribió sobre '.$thread->student->name.': '.mb_substr($body, 0, 120),
                    'link' => route('representante.dashboard').'#comms',
                ]);
            }
        }

        return $message;
    }

    private function contactPayloads($teacher)
    {
        $alerts = app(AttendanceAlertService::class);

        return Student::query()
            ->where(function ($q) use ($teacher) {
                $q->where('teacher_id', $teacher->id)
                    ->orWhereHas('courses', fn ($c) => $c->where('teacher_id', $teacher->id));
            })
            ->orderBy('name')
            ->get(['id', 'name', 'grade', 'section', 'family_code', 'colegio_id', 'teacher_id'])
            ->map(function (Student $student) use ($alerts) {
                $parents = $alerts->parentsFor($student);

                return [
                    'id' => $student->id,
                    'name' => $student->name,
                    'grade' => $student->grade,
                    'section' => $student->section,
                    'has_family' => $parents->isNotEmpty(),
                    'parents' => $parents->map(fn ($p) => [
                        'id' => $p->id,
                        'name' => $p->name,
                    ])->values(),
                    'parent_label' => $parents->pluck('name')->filter()->unique()->implode(', ') ?: 'Sin representante vinculado',
                ];
            })
            ->filter(fn ($row) => $row['has_family'])
            ->values();
    }

    private function authorizeStudentForTeacher(int $studentId): Student
    {
        $teacherId = auth()->id();

        return Student::query()
            ->where('id', $studentId)
            ->where(function ($q) use ($teacherId) {
                $q->where('teacher_id', $teacherId)
                    ->orWhereHas('courses', fn ($c) => $c->where('teacher_id', $teacherId));
            })
            ->firstOrFail();
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

