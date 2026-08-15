<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Evaluation;
use App\Models\EvaluationAttempt;
use App\Models\EvaluationQuestion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class EvaluationController extends Controller
{
    public function index(): View
    {
        $teacher = auth()->user();
        $evaluations = Evaluation::where('teacher_id', $teacher->id)
            ->with(['course:id,subject_name,grade,section', 'questions'])
            ->withCount('attempts')
            ->latest()
            ->get();

        $pendingAttempts = EvaluationAttempt::whereHas('evaluation', fn ($q) => $q->where('teacher_id', $teacher->id))
            ->where(function ($q) {
                $q->whereNull('score')->orWhere('status', 'pending_review');
            })
            ->with('evaluation:id,title')
            ->latest()
            ->limit(20)
            ->get();

        $bank = EvaluationQuestion::whereHas('evaluation', fn ($q) => $q->where('teacher_id', $teacher->id))
            ->with('evaluation:id,title,topic')
            ->latest()
            ->limit(80)
            ->get();

        $courses = Course::where('teacher_id', $teacher->id)
            ->orderBy('subject_name')
            ->get(['id', 'subject_name', 'grade', 'section']);

        $stats = [
            'active' => $evaluations->whereIn('status', ['published', 'scheduled'])->count(),
            'pending' => $pendingAttempts->count(),
            'upcoming' => $evaluations->where('status', 'scheduled')->take(5)->values(),
            'average' => $this->averageScore($evaluations),
        ];

        return view('teacher.evaluations.index', compact(
            'evaluations',
            'pendingAttempts',
            'bank',
            'courses',
            'stats',
            'teacher'
        ));
    }

    public function generate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'prompt' => 'required|string|min:8',
            'mode' => 'required|in:digital,physical',
            'course_id' => 'nullable|integer',
            'topic' => 'nullable|string|max:255',
            'difficulty' => 'nullable|in:basico,intermedio,avanzado',
            'question_mix' => 'nullable|in:multiple_choice,true_false,open,completion,mixto',
            'question_count' => 'nullable|integer|min:3|max:40',
            'large_print' => 'nullable|boolean',
        ]);

        $apiKey = config('services.openai.key');
        if (empty($apiKey)) {
            return response()->json([
                'success' => false,
                'error' => 'OPENAI_API_KEY no está configurada.',
            ], 200);
        }

        $count = $data['question_count'] ?? 10;
        $mix = $data['question_mix'] ?? 'mixto';
        $system = 'Eres un experto en evaluación educativa. Responde SOLO JSON válido, sin markdown. '
            . 'Estructura: {"title":"","instructions":"","questions":[{"type":"multiple_choice|true_false|open|completion","text":"","options":[],"correct_answer":"","points":1}],"rubric":{"total_points":0,"passing_score":0}}. '
            . 'Si type no es multiple_choice o true_false, options puede ser [].';

        $user = "Crea una evaluación modo {$data['mode']}. "
            . "Tema: " . ($data['topic'] ?: 'según la descripción') . ". "
            . "Dificultad: " . ($data['difficulty'] ?: 'intermedio') . ". "
            . "Tipo de preguntas: {$mix}. Número: {$count}. "
            . "Descripción del profesor: {$data['prompt']}";

        try {
            $response = Http::withToken($apiKey)
                ->timeout(70)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => 'gpt-4o-mini',
                    'temperature' => 0.4,
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        ['role' => 'system', 'content' => $system],
                        ['role' => 'user', 'content' => $user],
                    ],
                ]);

            if (! $response->successful()) {
                Log::warning('Evaluation AI error', ['status' => $response->status(), 'body' => $response->body()]);
                return response()->json(['success' => false, 'error' => 'La IA no pudo generar la evaluación.'], 200);
            }

            $content = data_get($response->json(), 'choices.0.message.content', '{}');
            $payload = json_decode($content, true);
            if (! is_array($payload) || empty($payload['questions'])) {
                return response()->json(['success' => false, 'error' => 'La IA devolvió un formato inválido.'], 200);
            }

            return response()->json([
                'success' => true,
                'message' => 'Evaluación generada con éxito. Revisa, edita y publica cuando estés listo.',
                'evaluation' => $payload,
            ]);
        } catch (\Throwable $e) {
            Log::error('Evaluation AI exception: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Error al contactar la IA.'], 200);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'topic' => 'nullable|string|max:255',
            'course_id' => 'nullable|integer',
            'mode' => 'required|in:digital,physical',
            'status' => 'nullable|in:draft,scheduled,published',
            'difficulty' => 'nullable|string|max:20',
            'question_mix' => 'nullable|string|max:30',
            'instructions' => 'nullable|string',
            'scheduled_at' => 'nullable|date',
            'generated_by_ai' => 'nullable|boolean',
            'large_print' => 'nullable|boolean',
            'physical_format' => 'nullable|array',
            'rubric' => 'nullable|array',
            'questions' => 'required|array|min:1',
            'questions.*.type' => 'required|string',
            'questions.*.text' => 'required|string',
            'questions.*.options' => 'nullable|array',
            'questions.*.correct_answer' => 'nullable|string',
            'questions.*.points' => 'nullable|integer|min:1',
        ]);

        $teacher = auth()->user();
        if (! empty($data['course_id'])) {
            abort_unless(Course::where('id', $data['course_id'])->where('teacher_id', $teacher->id)->exists(), 403);
        }

        $questions = $data['questions'];
        $total = collect($questions)->sum(fn ($q) => (int) ($q['points'] ?? 1));

        $evaluation = Evaluation::create([
            'teacher_id' => $teacher->id,
            'course_id' => $data['course_id'] ?? null,
            'colegio_id' => $teacher->colegio_id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'topic' => $data['topic'] ?? null,
            'mode' => $data['mode'],
            'status' => $data['status'] ?? 'draft',
            'difficulty' => $data['difficulty'] ?? null,
            'question_mix' => $data['question_mix'] ?? null,
            'question_count' => count($questions),
            'generated_by_ai' => (bool) ($data['generated_by_ai'] ?? false),
            'instructions' => $data['instructions'] ?? null,
            'scheduled_at' => $data['scheduled_at'] ?? null,
            'total_points' => $total,
            'passing_score' => (int) data_get($data, 'rubric.passing_score', max(1, (int) floor($total * 0.6))),
            'rubric' => $data['rubric'] ?? ['total_points' => $total, 'passing_score' => max(1, (int) floor($total * 0.6))],
            'physical_format' => $data['physical_format'] ?? ['paper_size' => 'A4', 'orientation' => 'portrait', 'font_size' => 12, 'include_qr' => true],
            'large_print' => (bool) ($data['large_print'] ?? false),
        ]);

        $this->syncQuestions($evaluation, $questions);

        return response()->json([
            'success' => true,
            'evaluation' => $evaluation->fresh(['questions', 'course']),
        ]);
    }

    public function update(Request $request, Evaluation $evaluation): JsonResponse
    {
        $this->authorizeTeacher($evaluation);

        $data = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'topic' => 'nullable|string|max:255',
            'course_id' => 'nullable|integer',
            'mode' => 'sometimes|in:digital,physical',
            'status' => 'sometimes|in:draft,scheduled,published,graded',
            'difficulty' => 'nullable|string|max:20',
            'instructions' => 'nullable|string',
            'scheduled_at' => 'nullable|date',
            'large_print' => 'nullable|boolean',
            'physical_format' => 'nullable|array',
            'rubric' => 'nullable|array',
            'questions' => 'sometimes|array|min:1',
        ]);

        if (isset($data['questions'])) {
            $this->syncQuestions($evaluation, $data['questions']);
            $data['question_count'] = count($data['questions']);
            $data['total_points'] = collect($data['questions'])->sum(fn ($q) => (int) ($q['points'] ?? 1));
            unset($data['questions']);
        }

        $evaluation->update($data);

        return response()->json(['success' => true, 'evaluation' => $evaluation->fresh(['questions', 'course'])]);
    }

    public function destroy(Evaluation $evaluation): JsonResponse
    {
        $this->authorizeTeacher($evaluation);
        $evaluation->delete();
        return response()->json(['success' => true]);
    }

    public function duplicate(Evaluation $evaluation): JsonResponse
    {
        $this->authorizeTeacher($evaluation);
        $copy = $evaluation->replicate();
        $copy->title = $evaluation->title . ' (copia)';
        $copy->status = 'draft';
        $copy->public_token = null;
        $copy->save();

        foreach ($evaluation->questions as $question) {
            $clone = $question->replicate();
            $clone->evaluation_id = $copy->id;
            $clone->save();
        }

        return response()->json(['success' => true, 'evaluation' => $copy->fresh(['questions', 'course'])]);
    }

    public function print(Evaluation $evaluation): View
    {
        $this->authorizeTeacher($evaluation);
        $evaluation->load(['questions', 'course', 'teacher.settings']);
        return view('teacher.evaluations.print', compact('evaluation'));
    }

    public function regenerateQuestion(Request $request, Evaluation $evaluation): JsonResponse
    {
        $this->authorizeTeacher($evaluation);
        $data = $request->validate([
            'index' => 'required|integer|min:0',
            'instruction' => 'nullable|string',
        ]);

        $questions = $evaluation->questions()->get()->values();
        $current = $questions->get($data['index']);
        if (! $current) {
            return response()->json(['success' => false, 'error' => 'Pregunta no encontrada.'], 404);
        }

        $apiKey = config('services.openai.key');
        if (empty($apiKey)) {
            return response()->json(['success' => false, 'error' => 'OPENAI_API_KEY no está configurada.'], 200);
        }

        $prompt = 'Regenera esta pregunta manteniendo el tipo ' . $current->type . '. '
            . 'Pregunta actual: ' . $current->text . '. '
            . ($data['instruction'] ?: 'Hazla más clara y del mismo nivel.');

        try {
            $response = Http::withToken($apiKey)
                ->timeout(45)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => 'gpt-4o-mini',
                    'temperature' => 0.5,
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        ['role' => 'system', 'content' => 'Responde JSON: {"text":"","options":[],"correct_answer":"","points":1,"type":""}'],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                ]);
            $payload = json_decode((string) data_get($response->json(), 'choices.0.message.content', '{}'), true);
            if (! is_array($payload) || empty($payload['text'])) {
                return response()->json(['success' => false, 'error' => 'No se pudo regenerar.'], 200);
            }
            $current->update([
                'text' => $payload['text'],
                'options' => $payload['options'] ?? $current->options,
                'correct_answer' => $payload['correct_answer'] ?? $current->correct_answer,
                'points' => $payload['points'] ?? $current->points,
                'type' => $payload['type'] ?? $current->type,
            ]);
            return response()->json(['success' => true, 'question' => $current->fresh()]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'error' => 'Error al regenerar.'], 200);
        }
    }

    public function gradeOpen(Request $request, EvaluationAttempt $attempt): JsonResponse
    {
        $evaluation = $attempt->evaluation;
        $this->authorizeTeacher($evaluation);

        $apiKey = config('services.openai.key');
        if (empty($apiKey)) {
            return response()->json(['success' => false, 'error' => 'OPENAI_API_KEY no está configurada.'], 200);
        }

        $openQuestions = $evaluation->questions->whereIn('type', ['open', 'completion']);
        $prompt = "Califica estas respuestas abiertas. Devuelve JSON {\"score\":0,\"comments\":[{\"question_id\":1,\"suggested_score\":0,\"comment\":\"\"}]}.\n";
        foreach ($openQuestions as $question) {
            $answer = data_get($attempt->answers, (string) $question->id, '');
            $prompt .= "Q{$question->id} ({$question->points} pts): {$question->text}\nRespuesta esperada: {$question->correct_answer}\nRespuesta alumno: {$answer}\n\n";
        }

        try {
            $response = Http::withToken($apiKey)
                ->timeout(50)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => 'gpt-4o-mini',
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        ['role' => 'system', 'content' => 'Eres un profesor justo. Responde solo JSON.'],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                ]);
            $payload = json_decode((string) data_get($response->json(), 'choices.0.message.content', '{}'), true) ?: [];
            $attempt->update([
                'ai_feedback' => $payload,
                'status' => 'pending_review',
            ]);
            return response()->json(['success' => true, 'feedback' => $payload]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'error' => 'No se pudo calificar con IA.'], 200);
        }
    }

    public function take(string $token): View
    {
        $evaluation = Evaluation::where('public_token', $token)
            ->where('status', 'published')
            ->where('mode', 'digital')
            ->with('questions')
            ->firstOrFail();

        return view('teacher.evaluations.take', compact('evaluation'));
    }

    public function submitTake(Request $request, string $token): JsonResponse
    {
        $evaluation = Evaluation::where('public_token', $token)
            ->where('status', 'published')
            ->where('mode', 'digital')
            ->with('questions')
            ->firstOrFail();

        $data = $request->validate([
            'student_name' => 'required|string|max:255',
            'answers' => 'required|array',
        ]);

        $score = 0;
        foreach ($evaluation->questions as $question) {
            if (in_array($question->type, ['open'], true)) {
                continue;
            }
            $given = trim((string) data_get($data['answers'], (string) $question->id, ''));
            $expected = trim((string) $question->correct_answer);
            if ($given !== '' && mb_strtolower($given) === mb_strtolower($expected)) {
                $score += $question->points;
            }
        }

        $hasOpen = $evaluation->questions->contains(fn ($q) => $q->type === 'open');
        EvaluationAttempt::create([
            'evaluation_id' => $evaluation->id,
            'student_name' => $data['student_name'],
            'answers' => $data['answers'],
            'score' => $score,
            'status' => $hasOpen ? 'pending_review' : 'graded',
        ]);

        return response()->json(['success' => true, 'score' => $score, 'total' => $evaluation->total_points]);
    }

    private function syncQuestions(Evaluation $evaluation, array $questions): void
    {
        $evaluation->questions()->delete();
        foreach (array_values($questions) as $index => $question) {
            $evaluation->questions()->create([
                'sort_order' => $index,
                'type' => $question['type'] ?? 'open',
                'text' => $question['text'],
                'options' => $question['options'] ?? [],
                'correct_answer' => $question['correct_answer'] ?? null,
                'points' => (int) ($question['points'] ?? 1),
                'topic' => $question['topic'] ?? $evaluation->topic,
            ]);
        }
    }

    private function authorizeTeacher(Evaluation $evaluation): void
    {
        abort_unless($evaluation->teacher_id === auth()->id(), 403);
    }

    private function averageScore($evaluations): ?float
    {
        $scores = EvaluationAttempt::whereIn('evaluation_id', $evaluations->pluck('id'))
            ->whereNotNull('score')
            ->pluck('score');

        return $scores->isNotEmpty() ? round((float) $scores->avg(), 1) : null;
    }
}
