<?php

namespace App\Services;

use Illuminate\Support\Arr;

class DirectorConversationContextService
{
    private const SESSION_KEY = 'director_ai_conversation_context';
    private const HISTORY_KEY = 'director_chat_history';
    private const HISTORY_LIMIT = 20;

    public function current(): array
    {
        return (array) session(self::SESSION_KEY, []);
    }

    /**
     * Representación estable del turno: pronombres y follow-ups leen esto,
     * no solo frases sueltas del texto.
     *
     * @param  array<string,mixed>|null  $context
     * @return array<string,mixed>
     */
    public function snapshot(?array $context = null): array
    {
        $ctx = $context ?? $this->current();
        $focus = (array) ($ctx['focus'] ?? []);
        $students = array_values(array_filter(array_map(
            fn ($name) => is_string($name) ? trim($name) : '',
            (array) ($ctx['student_names'] ?? $focus['student_names'] ?? [])
        )));
        $grade = $ctx['grades'][0] ?? $ctx['grade'] ?? $focus['grade'] ?? null;
        $section = $ctx['section'] ?? $focus['section'] ?? null;

        return array_merge($ctx, [
            'history' => $this->history(),
            'last_user_text' => $ctx['last_user_text'] ?? null,
            'last_intent' => $ctx['last_intent'] ?? ($focus['intent'] ?? null),
            'last_student' => $ctx['student_name'] ?? $focus['student_name'] ?? ($students[0] ?? null),
            'last_students' => $students,
            'last_grade' => is_string($grade) && $grade !== '' ? $grade : null,
            'last_section' => is_string($section) && $section !== '' ? $section : null,
            'last_subject' => $ctx['subject_name'] ?? $focus['worst_subject'] ?? $focus['subject'] ?? null,
            'last_teacher' => $ctx['teacher_name'] ?? $focus['worst_teacher'] ?? $focus['teacher'] ?? null,
            'last_course' => trim((string) ($grade ?? '').' '.((string) ($section ?? ''))),
            'filters' => (array) ($ctx['filters'] ?? []),
            'sort' => $ctx['sort'] ?? $focus['sort'] ?? data_get($ctx, 'filters.sort'),
            'focus' => $focus,
            'last_result_summary' => $ctx['last_result_summary'] ?? null,
            'pending_plan' => session('director_ai_pending_plan'),
            'chat_mode' => session('chat_mode', 'main_menu'),
            'chat_subject' => session('chat_subject'),
        ]);
    }

    /**
     * @param  array<int,array{action_type?:string,intent?:string,success?:bool}>  $actions
     */
    public function addTurn(string $userMessage, string $assistantResponse, array $actions = []): void
    {
        $history = $this->history();
        $history[] = [
            'user' => $userMessage,
            'assistant' => $assistantResponse,
            'actions' => collect($actions)->map(function ($action) {
                return [
                    'intent' => (string) ($action['action_type'] ?? $action['intent'] ?? ''),
                    'success' => (bool) ($action['success'] ?? true),
                ];
            })->values()->all(),
            'entities' => $this->extractEntities($userMessage),
            'timestamp' => now()->toIso8601String(),
        ];

        if (count($history) > self::HISTORY_LIMIT) {
            $history = array_slice($history, -self::HISTORY_LIMIT);
        }

        session([self::HISTORY_KEY => $history]);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function history(): array
    {
        return array_values((array) session(self::HISTORY_KEY, []));
    }

    /**
     * @param  array<int,array{intent:string,data:array}>  $actions
     */
    public function rememberPlan(array $actions, string $rawText): void
    {
        $context = $this->current();
        $context['last_user_text'] = $rawText;
        $context['last_actions'] = $actions;

        foreach ($actions as $action) {
            $data = (array) Arr::get($action, 'data', []);
            $this->absorbData($context, $data);
            $context['last_intent'] = (string) Arr::get($action, 'intent', '');
        }

        session([self::SESSION_KEY => $context]);
    }

    public function rememberResult(string $intent, array $data, array $result): void
    {
        $context = $this->current();
        $context['last_intent'] = $intent;
        $this->absorbData($context, $data);
        $this->absorbData($context, (array) ($result['data'] ?? []));
        if (is_string($result['message'] ?? null) && trim((string) $result['message']) !== '') {
            $context['last_result_summary'] = mb_substr(trim(strip_tags((string) $result['message'])), 0, 280);
        }
        unset($context['last_error']);
        session([self::SESSION_KEY => $context]);
    }

    public function rememberError(string $message): void
    {
        $context = $this->current();
        $context['last_error'] = $message;
        session([self::SESSION_KEY => $context]);
    }

    public function clearPendingReferences(): void
    {
        $context = $this->current();
        unset($context['last_actions'], $context['last_error']);
        session([self::SESSION_KEY => $context]);
    }

    /**
     * @return array<string,mixed>
     */
    private function extractEntities(string $text): array
    {
        $entities = [];
        if (preg_match('/\b([1-6](?:ro|do|to|er|ero)?)\b/u', $text, $grade)) {
            $entities['grade'] = (string) $grade[1];
        }
        if (preg_match('/\bsecci[oó]n\s+([A-Za-z0-9]{1,3})\b/iu', $text, $section)) {
            $entities['section'] = mb_strtoupper((string) $section[1]);
        }

        return $entities;
    }

    private function absorbData(array &$context, array $data): void
    {
        $teacher = $data['teacher_name']
            ?? $data['teacher']['name']
            ?? $data['teacher_label']
            ?? (is_array($data['teachers'] ?? null) ? ($data['teachers'][0] ?? null) : null);
        if (is_string($teacher) && trim($teacher) !== '') {
            $context['teacher_name'] = trim($teacher);
        }

        $subject = $data['subject_name'] ?? $data['worst_subject'] ?? null;
        if (is_string($subject) && trim($subject) !== '') {
            $context['subject_name'] = trim($subject);
        }

        $grades = $data['grades'] ?? null;
        if (is_array($grades) && $grades !== []) {
            $context['grades'] = array_values(array_filter(array_map('strval', $grades)));
        } elseif (! empty($data['grade'])) {
            $context['grades'] = [(string) $data['grade']];
        }

        $fromList = $this->namesFromRows($data['students'] ?? $data['ranking'] ?? null);
        $roster = array_values(array_filter(array_map(
            'strval',
            array_merge(
                (array) ($data['roster_names'] ?? []),
                is_array($data['students_without_grades'] ?? null)
                    ? $data['students_without_grades']
                    : collect($data['students_without_grades'] ?? [])->all(),
            )
        )));
        $studentNames = $data['names'] ?? $data['student_names'] ?? null;
        if ($roster !== []) {
            $context['student_names'] = array_values(array_unique(array_merge(
                (array) ($context['student_names'] ?? []),
                $roster,
                $fromList,
            )));
        } elseif (is_array($studentNames) && $studentNames !== []) {
            $context['student_names'] = array_values(array_filter(array_map('strval', $studentNames)));
        } elseif ($fromList !== []) {
            $context['student_names'] = $fromList;
        } elseif (! empty($data['student_name'])) {
            $context['student_names'] = [(string) $data['student_name']];
        }

        if (! empty($data['section'])) {
            $context['section'] = (string) $data['section'];
        }
        if (! empty($data['course_id'])) {
            $context['course_id'] = (int) $data['course_id'];
        }
        if (! empty($data['invite_code'])) {
            $context['invite_code'] = (string) $data['invite_code'];
        }
        if (! empty($data['sort'])) {
            $context['sort'] = (string) $data['sort'];
            $context['filters'] = array_merge((array) ($context['filters'] ?? []), ['sort' => $data['sort']]);
        }

        if (is_string($data['student'] ?? null) && trim($data['student']) !== '') {
            $context['student_names'] = array_values(array_unique(array_merge(
                (array) ($context['student_names'] ?? []),
                [trim($data['student'])],
            )));
            $context['student_name'] = trim($data['student']);
        } elseif (is_array($data['student'] ?? null) && ! empty($data['student']['name'])) {
            $context['student_name'] = (string) $data['student']['name'];
            $context['student_names'] = array_values(array_unique(array_merge(
                (array) ($context['student_names'] ?? []),
                [$context['student_name']],
            )));
            if (! empty($data['student']['grade'])) {
                $context['grades'] = [(string) $data['student']['grade']];
            }
            if (! empty($data['student']['section'])) {
                $context['section'] = (string) $data['student']['section'];
            }
        }
    }

    /**
     * @param  mixed  $rows
     * @return array<int,string>
     */
    private function namesFromRows(mixed $rows): array
    {
        if (! is_iterable($rows)) {
            return [];
        }

        return collect($rows)->map(function ($row) {
            if (is_array($row)) {
                return $row['name'] ?? null;
            }
            if (is_object($row)) {
                return $row->name ?? null;
            }

            return is_string($row) ? $row : null;
        })->filter(fn ($name) => is_string($name) && trim($name) !== '')->unique()->values()->all();
    }

    /**
     * @param  array<string,mixed>  $focus
     */
    public function rememberFocus(array $focus): void
    {
        if ($focus === []) {
            return;
        }

        $context = $this->current();
        $context['focus'] = array_filter($focus, fn ($value) => $value !== null && $value !== '' && $value !== []);
        if (! empty($focus['student_name'])) {
            $context['student_name'] = $focus['student_name'];
            $context['student_names'] = array_values(array_unique(array_merge(
                (array) ($context['student_names'] ?? []),
                [$focus['student_name']],
            )));
        }
        if (! empty($focus['student_names']) && is_array($focus['student_names'])) {
            $context['student_names'] = array_values(array_unique(array_filter(array_map('strval', $focus['student_names']))));
        }
        if (! empty($focus['grade'])) {
            $context['grades'] = [(string) $focus['grade']];
        }
        if (! empty($focus['section'])) {
            $context['section'] = (string) $focus['section'];
        }
        if (! empty($focus['subject']) || ! empty($focus['worst_subject'])) {
            $context['subject_name'] = (string) ($focus['subject'] ?? $focus['worst_subject']);
        }
        if (! empty($focus['sort'])) {
            $context['sort'] = (string) $focus['sort'];
            $context['filters'] = array_merge((array) ($context['filters'] ?? []), [
                'grade' => $focus['grade'] ?? ($context['grades'][0] ?? null),
                'section' => $focus['section'] ?? ($context['section'] ?? null),
                'sort' => $focus['sort'],
            ]);
        }
        session([self::SESSION_KEY => $context]);
    }
}
