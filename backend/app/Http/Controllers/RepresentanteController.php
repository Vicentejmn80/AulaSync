<?php

namespace App\Http\Controllers;

use App\Models\AbsenceRequest;
use App\Models\AttendanceReason;
use App\Models\CommunicationThread;
use App\Models\Course;
use App\Services\AcademicReportCardService;
use App\Services\AttendanceAlertService;
use App\Services\RepresentanteDashboardService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class RepresentanteController extends Controller
{
    public function __construct(private RepresentanteDashboardService $dashboard)
    {
    }

    public function index(): Response
    {
        $user = auth()->user();
        $user->loadMissing('settings');
        $students = $this->dashboard->linkedStudents($user);
        $reasons = $this->dashboard->reasons($user);
        $school = optional($students->first())->colegio;

        return response()
            ->view('representante.hub', [
                'students' => $students->map(fn ($s) => $this->dashboard->studentPayload($s))->values(),
                'reasons' => $reasons->map(fn ($r) => [
                    'id' => $r->id,
                    'label' => $r->label,
                    'requires_comment' => (bool) $r->requires_comment,
                ])->values(),
                'schoolName' => $school?->name,
                'parent' => [
                    'name' => $user->name,
                    'email' => $user->email,
                    'initials' => mb_strtoupper(mb_substr($user->name, 0, 1)),
                    'phone' => data_get($user->settings?->preferencias, 'phone'),
                    'address' => data_get($user->settings?->preferencias, 'address'),
                    'emergency' => data_get($user->settings?->preferencias, 'emergency'),
                ],
            ])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');
    }

    public function csrfToken(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'token' => csrf_token(),
        ]);
    }

    public function students(): JsonResponse
    {
        $students = $this->dashboard->linkedStudents(auth()->user());

        return response()->json([
            'ok' => true,
            'students' => $students->map(fn ($s) => $this->dashboard->studentPayload($s))->values(),
        ]);
    }

    public function resumen(int $estudiante): JsonResponse
    {
        $student = $this->dashboard->authorizeStudent(auth()->user(), $estudiante);

        return response()->json([
            'ok' => true,
            'student' => $this->dashboard->studentPayload($student),
            'summary' => $this->dashboard->summary($student),
        ]);
    }

    public function calendario(Request $request, int $estudiante): JsonResponse
    {
        $student = $this->dashboard->authorizeStudent(auth()->user(), $estudiante);

        return response()->json([
            'ok' => true,
            'calendar' => $this->dashboard->calendar($student, $request->query('month')),
        ]);
    }

    public function materias(int $estudiante): JsonResponse
    {
        $student = $this->dashboard->authorizeStudent(auth()->user(), $estudiante);

        return response()->json([
            'ok' => true,
            'subjects' => $this->dashboard->subjects($student),
        ]);
    }

    public function materia(int $estudiante, int $materia): JsonResponse
    {
        $student = $this->dashboard->authorizeStudent(auth()->user(), $estudiante);
        $course = Course::with('teacher:id,name')->findOrFail($materia);

        return response()->json([
            'ok' => true,
            'subject' => $this->dashboard->subjectDetail($student, $course),
        ]);
    }

    public function anuncios(Request $request): JsonResponse
    {
        $parent = auth()->user();
        $student = $this->dashboard->authorizeStudent($parent, (int) $request->query('estudiante_id'));

        return response()->json([
            'ok' => true,
            'announcements' => $this->dashboard->announcements($parent, $student),
        ]);
    }

    public function leerAnuncio(Request $request, int $anuncio): JsonResponse
    {
        $parent = auth()->user();
        $student = $this->dashboard->authorizeStudent($parent, (int) $request->input('estudiante_id', $request->query('estudiante_id')));
        $this->dashboard->markAnnouncementRead($parent, $student, $anuncio);

        return response()->json(['ok' => true]);
    }

    public function mensajes(Request $request): JsonResponse
    {
        $parent = auth()->user();
        $student = $this->dashboard->authorizeStudent($parent, (int) $request->query('estudiante_id'));

        return response()->json([
            'ok' => true,
            'threads' => $this->dashboard->threads($parent, $student),
        ]);
    }

    public function thread(Request $request, CommunicationThread $thread): JsonResponse
    {
        $parent = auth()->user();
        $student = $this->dashboard->authorizeStudent($parent, (int) $request->query('estudiante_id', $thread->student_id));

        return response()->json([
            'ok' => true,
            'thread' => $this->dashboard->threadMessages($parent, $student, $thread),
        ]);
    }

    public function sendMessage(Request $request, CommunicationThread $thread): JsonResponse
    {
        $data = $request->validate([
            'estudiante_id' => 'required|integer',
            'body' => 'required|string|min:1|max:4000',
        ]);
        $parent = auth()->user();
        $student = $this->dashboard->authorizeStudent($parent, (int) $data['estudiante_id']);
        $message = $this->dashboard->sendMessage($parent, $student, $thread, $data['body']);

        return response()->json(['ok' => true, 'message' => $message]);
    }

    public function startMessage(Request $request): JsonResponse
    {
        $data = $request->validate([
            'estudiante_id' => 'required|integer',
            'course_id' => 'required|integer',
            'body' => 'required|string|min:1|max:4000',
        ]);
        $parent = auth()->user();
        $student = $this->dashboard->authorizeStudent($parent, (int) $data['estudiante_id']);
        $thread = $this->dashboard->startThread($parent, $student, (int) $data['course_id'], $data['body']);

        return response()->json(['ok' => true, 'thread_id' => $thread->id]);
    }

    public function notifications(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            ...$this->dashboard->notifications(auth()->user()),
        ]);
    }

    public function markNotificationsRead(): JsonResponse
    {
        $this->dashboard->markNotificationsRead(auth()->user());

        return response()->json(['ok' => true]);
    }

    public function storeAbsenceJson(Request $request, AttendanceAlertService $alerts): JsonResponse
    {
        $row = $this->createAbsence($request, $alerts);

        return response()->json([
            'ok' => true,
            'message' => 'Ausencia reportada. El colegio ya fue notificado.',
            'id' => $row->id,
        ]);
    }

    public function storeAbsence(Request $request, AttendanceAlertService $alerts): RedirectResponse
    {
        $this->createAbsence($request, $alerts);

        return back()->with('status', 'Ausencia reportada. El colegio ya fue notificado.');
    }

    public function boletin(int $estudiante): Response
    {
        $student = $this->dashboard->authorizeStudent(auth()->user(), $estudiante);
        $payload = $this->dashboard->reportCardData($student);
        $courseData = $payload['courseData'];
        $globalAverage = $payload['globalAverage'];

        $pdf = Pdf::loadView('director.report-card-pdf', compact('student', 'courseData', 'globalAverage'));
        $pdf->setPaper('letter', 'portrait');

        return $pdf->download('boletin-'.$student->id.'-'.now()->format('Ymd').'.pdf');
    }

    public function boletinPreview(int $estudiante): JsonResponse
    {
        $student = $this->dashboard->authorizeStudent(auth()->user(), $estudiante);
        $payload = $this->dashboard->reportCardData($student);

        return response()->json([
            'ok' => true,
            'student' => $this->dashboard->studentPayload($student),
            'globalAverage' => $payload['globalAverage'],
            'courses' => $payload['courseData'],
        ]);
    }

    public function constancia(int $estudiante): Response
    {
        $student = $this->dashboard->authorizeStudent(auth()->user(), $estudiante);
        $school = $student->colegio?->name ?? 'AulaSync';

        $html = view('representante.constancia-pdf', [
            'student' => $student,
            'school' => $school,
            'parent' => auth()->user(),
            'issued' => now(),
        ])->render();

        $pdf = Pdf::loadHTML($html);
        $pdf->setPaper('letter', 'portrait');

        return $pdf->download('constancia-'.$student->id.'.pdf');
    }

    public function boletasOficiales(int $estudiante): JsonResponse
    {
        $student = $this->dashboard->authorizeStudent(auth()->user(), $estudiante);
        $svc     = app(AcademicReportCardService::class);
        $boletas = $svc->publishedForStudent($student);

        return response()->json(['ok' => true, 'boletas' => $boletas]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:40',
            'address' => 'nullable|string|max:255',
            'emergency' => 'nullable|string|max:80',
        ]);

        $user = $request->user();
        $user->update(['name' => $data['name']]);

        $prefs = $user->settings?->preferencias ?? [];
        $prefs['phone'] = $data['phone'] ?? null;
        $prefs['address'] = $data['address'] ?? null;
        $prefs['emergency'] = $data['emergency'] ?? null;

        $user->settings()->updateOrCreate(
            ['user_id' => $user->id],
            ['preferencias' => $prefs]
        );

        return response()->json(['ok' => true, 'message' => 'Perfil actualizado.']);
    }

    private function createAbsence(Request $request, AttendanceAlertService $alerts): AbsenceRequest
    {
        abort_unless(Schema::hasTable('absence_requests'), 503);

        $data = $request->validate([
            'student_id' => 'required|integer',
            'kind' => 'required|in:absence,tardy',
            'reason_id' => 'required|integer|exists:attendance_reasons,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'comment' => 'nullable|string|max:500',
        ]);

        $parent = $request->user();
        $student = $this->dashboard->authorizeStudent($parent, (int) $data['student_id']);

        $reason = AttendanceReason::findOrFail($data['reason_id']);
        if ($reason->requires_comment && blank($data['comment'] ?? null)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'comment' => 'Este motivo requiere un comentario.',
            ]);
        }

        $row = AbsenceRequest::create([
            'colegio_id' => $student->colegio_id ?? $parent->colegio_id,
            'student_id' => $student->id,
            'parent_id' => $parent->id,
            'kind' => $data['kind'],
            'reason_id' => $reason->id,
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'comment' => $data['comment'] ?? null,
            'status' => 'pending',
        ]);

        $range = $row->start_date->format('d/m/Y');
        if ($row->end_date->toDateString() !== $row->start_date->toDateString()) {
            $range .= ' – '.$row->end_date->format('d/m/Y');
        }

        $alerts->notifyParentRequest($student, $parent, $row->kind, $range);

        return $row;
    }
}
