<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\IntelligenceDocument;
use App\Models\User;
use App\Services\IntelligenceActionService;
use App\Services\IntelligenceAnalyticsService;
use App\Services\IntelligenceApplicationService;
use App\Services\IntelligenceConnectorRegistry;
use App\Services\IntelligenceExtractionService;
use App\Services\IntelligenceQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class IntelligenceController extends Controller
{
    private const ACCESS_DENIED = 'No tienes permisos para acceder a esta información o realizar esta acción.';

    public function __construct(
        private IntelligenceExtractionService $extraction,
        private IntelligenceApplicationService $application,
        private IntelligenceAnalyticsService $analytics,
        private IntelligenceQueryService $query,
        private IntelligenceActionService $actions,
        private IntelligenceConnectorRegistry $connectors,
    ) {}

    public function index()
    {
        $teacher = auth()->user();

        return view('teacher.intelligence.index', [
            'courses' => $this->analytics->courses($teacher),
            'connectors' => $this->connectors->available()->all(),
            'aiAvailable' => $this->extraction->enabled(),
        ]);
    }

    public function documents(Request $request): JsonResponse
    {
        $documents = IntelligenceDocument::where('teacher_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->limit(30)
            ->get(['id', 'original_name', 'kind', 'status', 'confidence', 'course_id', 'error', 'created_at', 'applied_at'])
            ->map(fn ($document) => [
                'id' => (int) $document->id,
                'original_name' => $document->original_name,
                'kind' => $document->kind,
                'kind_label' => $document->kindLabel(),
                'status' => $document->status,
                'confidence' => $document->confidence,
                'course_id' => $document->course_id,
                'error' => $document->error,
                'created_at' => optional($document->created_at)->format('d/m/Y H:i'),
                'applied_at' => optional($document->applied_at)->format('d/m/Y H:i'),
            ]);

        return response()->json(['success' => true, 'documents' => $documents]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:12288', 'mimes:pdf,docx,xlsx,csv,txt,tsv,jpg,jpeg,png,webp'],
        ]);

        /** @var User $teacher */
        $teacher = $request->user();
        @set_time_limit(120);

        $file = $request->file('file');
        $path = $file->store('intelligence-documents/'.$teacher->id, 'local');

        $document = IntelligenceDocument::create([
            'teacher_id' => $teacher->id,
            'colegio_id' => $teacher->colegio_id,
            'original_name' => mb_substr($file->getClientOriginalName(), 0, 180),
            'disk_path' => (string) $path,
            'mime_type' => mb_substr((string) $file->getMimeType(), 0, 120),
            'size_bytes' => (int) $file->getSize(),
            'status' => IntelligenceDocument::STATUS_UPLOADED,
        ]);

        try {
            $document = $this->extraction->extract($document, $teacher);
        } catch (\Throwable $e) {
            Log::error('Intelligence upload failed', [
                'document_id' => $document->id,
                'teacher_id' => $teacher->id,
                'error' => $e->getMessage(),
            ]);

            $document->status = IntelligenceDocument::STATUS_FAILED;
            $document->error = 'No pude analizar este archivo. Verifica que sea un documento legible e inténtalo de nuevo.';
            $document->save();
        }

        return response()->json([
            'success' => true,
            'document' => $this->documentPayload($document),
            'review' => $document->review,
        ]);
    }

    public function show(Request $request, IntelligenceDocument $document): JsonResponse
    {
        if ($document->teacher_id !== (int) $request->user()->id) {
            return response()->json(['success' => false, 'message' => self::ACCESS_DENIED], 403);
        }

        return response()->json([
            'success' => true,
            'document' => $this->documentPayload($document),
            'review' => $document->review,
        ]);
    }

    public function apply(Request $request, IntelligenceDocument $document): JsonResponse
    {
        if ($document->teacher_id !== (int) $request->user()->id) {
            return response()->json(['success' => false, 'message' => self::ACCESS_DENIED], 403);
        }

        $request->validate([
            'course_id' => ['required', 'integer'],
            'students' => ['sometimes', 'array', 'max:300'],
            'students.*' => ['integer'],
            'student_choices' => ['sometimes', 'array'],
            'activities' => ['sometimes', 'array', 'max:200'],
            'activities.*' => ['integer'],
            'grades' => ['sometimes', 'array', 'max:800'],
            'grades.*' => ['integer'],
            'attendance' => ['sometimes', 'array', 'max:800'],
            'attendance.*' => ['integer'],
        ]);

        try {
            $result = $this->application->apply($document, $request->user(), $request->all());
        } catch (\Throwable $e) {
            if ($e instanceof \Illuminate\Validation\ValidationException) {
                throw $e;
            }

            Log::error('Intelligence apply failed', [
                'document_id' => $document->id,
                'teacher_id' => $request->user()->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['success' => false, 'message' => self::ACCESS_DENIED]);
        }

        return response()->json($result);
    }

    public function destroy(Request $request, IntelligenceDocument $document): JsonResponse
    {
        if ($document->teacher_id !== (int) $request->user()->id) {
            return response()->json(['success' => false, 'message' => self::ACCESS_DENIED], 403);
        }

        if ($document->disk_path) {
            Storage::disk('local')->delete($document->disk_path);
        }
        $document->delete();

        return response()->json(['success' => true, 'message' => 'Documento eliminado.']);
    }

    public function dashboard(Request $request): JsonResponse
    {
        $request->validate([
            'course_id' => ['sometimes', 'nullable', 'integer'],
        ]);

        $summary = $this->analytics->groupSummary(
            $request->user(),
            $request->filled('course_id') ? (int) $request->input('course_id') : null
        );

        return response()->json(['success' => true, 'summary' => $summary]);
    }

    public function query(Request $request): JsonResponse
    {
        $request->validate([
            'text' => ['required', 'string', 'max:500'],
            'course_id' => ['sometimes', 'nullable', 'integer'],
        ]);

        $result = $this->query->answer(
            $request->user(),
            (string) $request->input('text'),
            $request->filled('course_id') ? (int) $request->input('course_id') : null
        );

        return response()->json(['success' => true, 'answer' => $result]);
    }

    public function runAction(Request $request): JsonResponse
    {
        $request->validate([
            'action' => ['required', 'string', 'in:analyze_group,analyze_student,detect_attention,generate_planning,generate_activities,generate_tasks,generate_report'],
            'course_id' => ['sometimes', 'nullable', 'integer'],
            'student_name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'count' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:8'],
        ]);

        try {
            $result = $this->actions->run($request->user(), (string) $request->input('action'), $request->all());
        } catch (\Throwable $e) {
            Log::error('Intelligence action failed', [
                'action' => $request->input('action'),
                'teacher_id' => $request->user()->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['success' => false, 'type' => 'error', 'message' => self::ACCESS_DENIED]);
        }

        return response()->json($result);
    }

    public function applyAction(Request $request): JsonResponse
    {
        $request->validate([
            'selected' => ['required', 'array', 'min:1', 'max:8'],
            'selected.*' => ['integer'],
            'dates' => ['sometimes', 'array'],
            'dates.*' => ['nullable', 'date'],
        ]);

        try {
            $result = $this->actions->applyProposal(
                $request->user(),
                array_map('intval', $request->input('selected')),
                (array) $request->input('dates', [])
            );
        } catch (\Throwable $e) {
            Log::error('Intelligence proposal apply failed', [
                'teacher_id' => $request->user()->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['success' => false, 'type' => 'error', 'message' => self::ACCESS_DENIED]);
        }

        return response()->json($result);
    }

    /**
     * @return array<string, mixed>
     */
    private function documentPayload(IntelligenceDocument $document): array
    {
        return [
            'id' => (int) $document->id,
            'original_name' => $document->original_name,
            'kind' => $document->kind,
            'kind_label' => $document->kindLabel(),
            'status' => $document->status,
            'confidence' => $document->confidence,
            'error' => $document->error,
            'created_at' => optional($document->created_at)->format('d/m/Y H:i'),
        ];
    }
}
