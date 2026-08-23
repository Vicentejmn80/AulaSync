<?php

namespace App\Services;

use Illuminate\Support\Arr;

class DirectorConversationContextService
{
    private const SESSION_KEY = 'director_ai_conversation_context';

    public function current(): array
    {
        return (array) session(self::SESSION_KEY, []);
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

    private function absorbData(array &$context, array $data): void
    {
        $teacher = $data['teacher_name']
            ?? $data['teacher']['name']
            ?? $data['teacher_label']
            ?? null;
        if (is_string($teacher) && trim($teacher) !== '') {
            $context['teacher_name'] = trim($teacher);
        }

        $subject = $data['subject_name'] ?? null;
        if (is_string($subject) && trim($subject) !== '') {
            $context['subject_name'] = trim($subject);
        }

        $grades = $data['grades'] ?? null;
        if (is_array($grades) && $grades !== []) {
            $context['grades'] = array_values(array_filter(array_map('strval', $grades)));
        } elseif (! empty($data['grade'])) {
            $context['grades'] = [(string) $data['grade']];
        }

        $studentNames = $data['names'] ?? $data['student_names'] ?? null;
        if (is_array($studentNames) && $studentNames !== []) {
            $context['student_names'] = array_values(array_filter(array_map('strval', $studentNames)));
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

        if (is_string($data['student'] ?? null) && trim($data['student']) !== '') {
            $context['student_names'] = [trim($data['student'])];
            $context['student_name'] = trim($data['student']);
        }
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
        if (! empty($focus['grade'])) {
            $context['grades'] = [(string) $focus['grade']];
        }
        if (! empty($focus['section'])) {
            $context['section'] = (string) $focus['section'];
        }
        session([self::SESSION_KEY => $context]);
    }
}
