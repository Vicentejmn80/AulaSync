<?php

namespace App\Http\Controllers\Director;

use App\Http\Controllers\Controller;
use App\Models\AcademicPeriod;
use App\Models\ReportCard;
use App\Services\AcademicReportCardService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AcademicPeriodController extends Controller
{
    public function __construct(private AcademicReportCardService $svc)
    {
    }

    // ── Main page ─────────────────────────────────────────────────────────────

    public function index(): View
    {
        $colegioId = auth()->user()->colegio_id;
        $periods   = $this->svc->periodsForSchool($colegioId);

        return view('director.academic-periods', compact('periods'));
    }

    // ── Periods API ───────────────────────────────────────────────────────────

    public function apiPeriods(): JsonResponse
    {
        $periods = $this->svc->periodsForSchool(auth()->user()->colegio_id);

        return response()->json($periods);
    }

    public function storePeriod(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'                 => ['required', 'string', 'max:120'],
            'start_date'           => ['required', 'date'],
            'end_date'             => ['required', 'date', 'after_or_equal:start_date'],
            'report_card_due_date' => ['nullable', 'date'],
            'status'               => ['in:active,closed'],
        ]);

        $period = $this->svc->createPeriod(auth()->user()->colegio_id, $validated);

        return response()->json(['ok' => true, 'period' => $period], 201);
    }

    public function updatePeriod(Request $request, int $periodId): JsonResponse
    {
        $period = $this->findPeriod($periodId);
        $validated = $request->validate([
            'name'                 => ['sometimes', 'string', 'max:120'],
            'start_date'           => ['sometimes', 'date'],
            'end_date'             => ['sometimes', 'date', 'after_or_equal:start_date'],
            'report_card_due_date' => ['nullable', 'date'],
            'status'               => ['sometimes', 'in:active,closed'],
        ]);

        $period = $this->svc->updatePeriod($period, $validated);

        return response()->json(['ok' => true, 'period' => $period]);
    }

    // ── Grades summary ────────────────────────────────────────────────────────

    public function gradesSummary(int $periodId): JsonResponse
    {
        $period  = $this->findPeriod($periodId);
        $summary = $this->svc->gradesSummary($period);

        return response()->json($summary);
    }

    // ── Generate boletas ──────────────────────────────────────────────────────

    public function generate(int $periodId): JsonResponse
    {
        $period = $this->findPeriod($periodId);
        $result = $this->svc->generateForPeriod($period, auth()->user());

        return response()->json([
            'ok'        => true,
            'generated' => $result['generated'],
            'skipped'   => $result['skipped'],
            'message'   => "Boletas generadas: {$result['generated']}. Omitidas (ya publicadas): {$result['skipped']}.",
        ]);
    }

    // ── Boletas list ──────────────────────────────────────────────────────────

    public function listCards(int $periodId): JsonResponse
    {
        $period = $this->findPeriod($periodId);

        $cards = ReportCard::where('academic_period_id', $period->id)
            ->with(['student:id,name,grade,section', 'grades:report_card_id,grade'])
            ->orderBy(
                \App\Models\Student::select('name')
                    ->whereColumn('students.id', 'report_cards.student_id')
                    ->limit(1)
            )
            ->get()
            ->map(function (ReportCard $card) {
                return [
                    'id'             => $card->id,
                    'student'        => [
                        'id'      => $card->student?->id,
                        'name'    => $card->student?->name,
                        'grade'   => $card->student?->grade,
                        'section' => $card->student?->section,
                    ],
                    'status'         => $card->status,
                    'status_label'   => $card->statusLabel(),
                    'status_color'   => $card->statusColor(),
                    'global_average' => $card->globalAverage(),
                    'generated_at'   => $card->generated_at?->format('d/m/Y H:i'),
                    'published_at'   => $card->published_at?->format('d/m/Y H:i'),
                ];
            });

        return response()->json(['cards' => $cards]);
    }

    // ── Get / Update single boleta ────────────────────────────────────────────

    public function getCard(int $cardId): JsonResponse
    {
        $card = $this->svc->getReportCard($cardId, auth()->user()->colegio_id);

        return response()->json($this->svc->reportCardPayload($card));
    }

    public function updateCard(Request $request, int $cardId): JsonResponse
    {
        $card = $this->svc->getReportCard($cardId, auth()->user()->colegio_id);

        $validated = $request->validate([
            'observations'                     => ['nullable', 'string', 'max:3000'],
            'grades'                           => ['nullable', 'array'],
            'grades.*.course_id'               => ['required', 'integer'],
            'grades.*.grade'                   => ['required', 'numeric', 'min:0', 'max:100'],
            'grades.*.teacher_observations'    => ['nullable', 'string', 'max:1000'],
        ]);

        $card = $this->svc->updateReportCard($card, $validated, auth()->user());

        return response()->json(['ok' => true, 'card' => $this->svc->reportCardPayload($card)]);
    }

    // ── Publish ───────────────────────────────────────────────────────────────

    public function publish(int $periodId): JsonResponse
    {
        $period = $this->findPeriod($periodId);
        $result = $this->svc->publishPeriod($period, auth()->user());

        return response()->json([
            'ok'        => true,
            'published' => $result['published'],
            'message'   => $result['message'] ?? "¡{$result['published']} boletas publicadas! Los representantes ya pueden verlas.",
        ]);
    }

    // ── PDF ───────────────────────────────────────────────────────────────────

    public function pdfCard(int $cardId)
    {
        $card = $this->svc->getReportCard($cardId, auth()->user()->colegio_id);
        $data = $this->svc->pdfData($card);

        $pdf = Pdf::loadView('director.report-card-pdf-period', $data);
        $pdf->setPaper('letter', 'portrait');

        $name = str_replace(' ', '-', strtolower($card->student->name ?? 'boleta'));

        return $pdf->download("boleta-{$name}-{$card->id}.pdf");
    }

    public function pdfBulk(int $periodId)
    {
        $period = $this->findPeriod($periodId);

        $cards = ReportCard::where('academic_period_id', $period->id)
            ->with(['student.colegio', 'period', 'grades'])
            ->get();

        if ($cards->isEmpty()) {
            return back()->with('error', 'No hay boletas generadas para este período.');
        }

        $allData = $cards->map(fn (ReportCard $card) => $this->svc->pdfData($card))->values()->toArray();

        $pdf = Pdf::loadView('director.report-card-bulk-pdf', [
            'cards'   => $allData,
            'period'  => $period,
            'colegio' => auth()->user()->colegio,
        ]);
        $pdf->setPaper('letter', 'portrait');

        return $pdf->download("boletas-{$period->name}-".now()->format('Ymd').'.pdf');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function findPeriod(int $id): AcademicPeriod
    {
        return AcademicPeriod::where('colegio_id', auth()->user()->colegio_id)
            ->findOrFail($id);
    }
}
