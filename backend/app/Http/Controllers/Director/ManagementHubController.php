<?php

namespace App\Http\Controllers\Director;

use App\Helpers\InviteCodeHelper;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Materia;
use App\Models\Student;
use App\Models\TeacherInvite;
use App\Models\User;
use App\Services\DirectorActionService;
use App\Services\PersonNameSanitizer;
use App\Services\StudentEnrollmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ManagementHubController extends Controller
{
    public function __construct(
        private DirectorActionService $actions,
        private StudentEnrollmentService $enrollment,
        private PersonNameSanitizer $names,
    ) {}

    public function index(): View
    {
        return view('director.gestion');
    }

    public function snapshot(Request $request): JsonResponse
    {
        $colegioId = (int) $request->user()->colegio_id;

        $teachers = User::query()
            ->where('colegio_id', $colegioId)
            ->where('role', 'profesor')
            ->with(['courses' => function ($query) {
                $query->withCount('students')->orderBy('subject_name')->orderBy('grade')->orderBy('section');
            }])
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role', 'colegio_id'])
            ->map(fn (User $teacher) => $this->serializeTeacher($teacher));

        $invites = TeacherInvite::query()
            ->where('colegio_id', $colegioId)
            ->whereNull('claimed_by')
            ->whereNull('claimed_at')
            ->whereNull('revoked_at')
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->with(['courses' => function ($query) {
                $query->withCount('students')->orderBy('subject_name')->orderBy('grade');
            }])
            ->latest('id')
            ->get()
            ->map(fn (TeacherInvite $invite) => $this->serializeInvite($invite));

        $students = Student::query()
            ->where('colegio_id', $colegioId)
            ->with(['courses:id,subject_name,grade,section'])
            ->orderBy('name')
            ->get()
            ->map(fn (Student $student) => $this->serializeStudent($student));

        $courses = Course::query()
            ->where('colegio_id', $colegioId)
            ->with(['teacher:id,name', 'pendingInvite:id,name,invite_code', 'students:id,name,grade,section'])
            ->withCount('students')
            ->orderBy('grade')
            ->orderBy('subject_name')
            ->get()
            ->map(fn (Course $course) => $this->serializeCourse($course));

        $materias = Materia::query()
            ->where('colegio_id', $colegioId)
            ->withCount('courses')
            ->orderBy('name')
            ->get()
            ->map(fn (Materia $materia) => [
                'id' => $materia->id,
                'name' => $materia->name,
                'courses_count' => $materia->courses_count,
            ]);

        $grades = $courses->pluck('grade')->merge($students->pluck('grade'))->filter()->unique()->values();

        return response()->json([
            'success' => true,
            'counts' => [
                'teachers' => $teachers->count() + $invites->count(),
                'teachers_active' => $teachers->count(),
                'teachers_pending' => $invites->count(),
                'students' => $students->count(),
                'courses' => $courses->count(),
                'materias' => $materias->count(),
            ],
            'teachers' => $teachers,
            'invites' => $invites,
            'students' => $students,
            'courses' => $courses,
            'materias' => $materias,
            'grades' => $grades,
        ]);
    }

    public function storeTeacher(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'email' => ['nullable', 'email', 'max:180'],
            'course_ids' => ['nullable', 'array'],
            'course_ids.*' => ['integer'],
        ]);

        $result = $this->actions->createTeacherInviteWithAssignments($request->user(), [
            'teacher_name' => $data['name'],
            'email' => $data['email'] ?? null,
        ]);

        $invite = $result['invite'];
        $courseIds = collect($data['course_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->unique()->values();
        if ($courseIds->isNotEmpty()) {
            $this->assignCoursesToInvite($request->user()->colegio_id, $invite, $courseIds->all());
        }

        return response()->json([
            'success' => true,
            'message' => "Invitación lista para {$invite->display_name}. Código {$invite->invite_code}.",
            'invite' => $this->serializeInvite($invite->fresh(['courses'])),
        ]);
    }

    public function storeStudent(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'grade' => ['nullable', 'string', 'max:60'],
            'section' => ['nullable', 'string', 'max:10'],
            'course_id' => ['nullable', 'integer'],
            'course_ids' => ['nullable', 'array'],
            'course_ids.*' => ['integer'],
        ]);

        $courseIds = collect($data['course_ids'] ?? [])
            ->when(! empty($data['course_id']), fn ($c) => $c->push($data['course_id']))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        $course = null;
        if ($courseIds->isNotEmpty()) {
            $course = Course::query()
                ->where('colegio_id', $request->user()->colegio_id)
                ->where('id', $courseIds->first())
                ->firstOrFail();
            $data['grade'] = ($data['grade'] ?? '') ?: $course->grade;
            $data['section'] = ($data['section'] ?? '') ?: $course->section;
        }

        $student = $this->enrollment->enroll($request->user(), $data, $course);

        foreach ($courseIds->skip(1) as $id) {
            $extra = Course::query()
                ->where('colegio_id', $request->user()->colegio_id)
                ->where('id', $id)
                ->first();
            if ($extra) {
                $this->enrollment->attachExisting($extra, $student, $request->user());
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Alumno «{$student->name}» matriculado.",
            'student' => $this->serializeStudent($student->fresh(['courses'])),
        ]);
    }

    public function updateStudent(Request $request, Student $student): JsonResponse
    {
        abort_unless((int) $student->colegio_id === (int) $request->user()->colegio_id, 404);

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:180'],
            'grade' => ['nullable', 'string', 'max:60'],
            'section' => ['nullable', 'string', 'max:10'],
        ]);

        $payload = ['student_name' => $student->name];
        if (array_key_exists('name', $data) && $data['name']) {
            $payload['new_name'] = $this->names->displayName($data['name']) ?: $data['name'];
        }
        if (array_key_exists('grade', $data) && $data['grade'] !== null) {
            $payload['new_grade'] = $data['grade'];
        }
        if (array_key_exists('section', $data)) {
            $payload['new_section'] = $data['section'];
        }

        $result = $this->actions->updateStudent($request->user(), $payload);

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'student' => $this->serializeStudent($student->fresh(['courses'])),
        ]);
    }

    public function storeCourse(Request $request): JsonResponse
    {
        $data = $request->validate([
            'materia_id' => ['nullable', 'integer'],
            'subject_name' => ['nullable', 'string', 'max:120'],
            'grade' => ['required', 'string', 'max:60'],
            'section' => ['nullable', 'string', 'max:10'],
            'teacher_id' => ['nullable', 'integer'],
            'invite_id' => ['nullable', 'integer'],
        ]);

        $director = $request->user();
        $materia = null;
        if (! empty($data['materia_id'])) {
            $materia = Materia::query()
                ->where('colegio_id', $director->colegio_id)
                ->where('id', $data['materia_id'])
                ->firstOrFail();
            $data['subject_name'] = $materia->name;
        }
        abort_unless(! empty($data['subject_name']), 422, 'Selecciona o escribe una materia.');

        $teacherId = null;
        $inviteId = null;
        $label = 'sin docente';

        if (! empty($data['invite_id'])) {
            $invite = TeacherInvite::query()
                ->where('colegio_id', $director->colegio_id)
                ->where('id', $data['invite_id'])
                ->whereNull('claimed_by')
                ->firstOrFail();
            $inviteId = $invite->id;
            $label = $invite->display_name;
        } elseif (! empty($data['teacher_id'])) {
            $teacher = User::query()
                ->where('colegio_id', $director->colegio_id)
                ->where('role', 'profesor')
                ->where('id', $data['teacher_id'])
                ->firstOrFail();
            $teacherId = $teacher->id;
            $label = $teacher->name;
        }

        $result = $this->actions->createCourse($director, [
            'subject_name' => $data['subject_name'],
            'grade' => $data['grade'],
            'section' => ($data['section'] ?? null) ?: null,
            'teacher_name' => $label !== 'sin docente' ? $label : null,
        ]);
        $course = $result['course'];

        $created = ! ($result['was_existing'] ?? false);

        return response()->json([
            'success' => true,
            'message' => $created
                ? "Curso {$course->subject_name} {$course->grade} creado".($label !== 'sin docente' ? " y asignado a {$label}" : '').'.'
                : "Ese curso ya existía: {$course->subject_name} {$course->grade}.",
            'course' => $this->serializeCourse($course->fresh(['teacher', 'pendingInvite', 'students'])),
        ]);
    }

    public function updateCourse(Request $request, Course $course): JsonResponse
    {
        abort_unless((int) $course->colegio_id === (int) $request->user()->colegio_id, 404);

        $data = $request->validate([
            'subject_name' => ['nullable', 'string', 'max:120'],
            'grade' => ['nullable', 'string', 'max:60'],
            'section' => ['nullable', 'string', 'max:10'],
        ]);

        $course->update(array_filter([
            'subject_name' => $data['subject_name'] ?? null,
            'grade' => $data['grade'] ?? null,
            'section' => array_key_exists('section', $data) ? ($data['section'] ?: null) : null,
        ], fn ($value) => $value !== null));

        return response()->json([
            'success' => true,
            'message' => 'Curso actualizado.',
            'course' => $this->serializeCourse($course->fresh(['teacher', 'pendingInvite', 'students'])),
        ]);
    }

    public function assignTeacherCourses(Request $request): JsonResponse
    {
        $data = $request->validate([
            'teacher_id' => ['nullable', 'integer'],
            'invite_id' => ['nullable', 'integer'],
            'course_ids' => ['required', 'array'],
            'course_ids.*' => ['integer'],
        ]);

        abort_unless(! empty($data['teacher_id']) || ! empty($data['invite_id']), 422);

        $colegioId = (int) $request->user()->colegio_id;
        $courseIds = collect($data['course_ids'])->map(fn ($id) => (int) $id)->unique()->values();
        $owned = Course::query()
            ->where('colegio_id', $colegioId)
            ->whereIn('id', $courseIds)
            ->get()
            ->filter(function (Course $course) use ($data) {
                if (! empty($data['teacher_id']) && (int) $course->teacher_id === (int) $data['teacher_id']) {
                    return true;
                }
                if (! empty($data['invite_id']) && (int) $course->teacher_invite_id === (int) $data['invite_id']) {
                    return true;
                }

                return ! $course->teacher_id && ! $course->teacher_invite_id;
            })
            ->values();

        abort_if($owned->isEmpty(), 422, 'Solo se pueden asignar cursos sin docente. Los ocupados no se reasignan desde aquí.');

        if (! empty($data['invite_id'])) {
            $invite = TeacherInvite::query()
                ->where('colegio_id', $colegioId)
                ->where('id', $data['invite_id'])
                ->whereNull('claimed_by')
                ->firstOrFail();
            $this->assignCoursesToInvite($colegioId, $invite, $owned->pluck('id')->all());
            $label = $invite->display_name;
        } else {
            $teacher = User::query()
                ->where('colegio_id', $colegioId)
                ->where('role', 'profesor')
                ->where('id', $data['teacher_id'])
                ->firstOrFail();
            Course::query()
                ->where('colegio_id', $colegioId)
                ->whereIn('id', $owned->pluck('id'))
                ->update([
                    'teacher_id' => $teacher->id,
                    'teacher_invite_id' => null,
                ]);
            $label = $teacher->name;
        }

        return response()->json([
            'success' => true,
            'message' => $owned->count().' curso(s) asignados a '.$label.'.',
        ]);
    }

    public function storeMateria(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        $result = $this->actions->createSubject($request->user(), [
            'subject_name' => $data['name'],
        ]);

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'materia' => [
                'id' => $result['materia']->id,
                'name' => $result['materia']->name,
                'courses_count' => $result['materia']->courses()->count(),
            ],
        ]);
    }

    public function destroyMateria(Request $request, Materia $materia): JsonResponse
    {
        abort_unless((int) $materia->colegio_id === (int) $request->user()->colegio_id, 404);

        $count = $materia->courses()->count();
        if ($count > 0) {
            return response()->json([
                'success' => false,
                'message' => "No se puede borrar «{$materia->name}»: tiene {$count} curso(s). Borra o reasigna esos cursos primero.",
            ], 422);
        }

        $name = $materia->name;
        $materia->delete();

        return response()->json([
            'success' => true,
            'message' => "Se eliminó «{$name}» del catálogo.",
        ]);
    }

    public function unassignCourse(Request $request, Course $course): JsonResponse
    {
        abort_unless((int) $course->colegio_id === (int) $request->user()->colegio_id, 404);

        $course->update([
            'teacher_id' => null,
            'teacher_invite_id' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => $course->subject_name.' quedó sin docente.',
        ]);
    }

    public function bulkDestroy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'teachers' => ['nullable', 'array'],
            'teachers.*' => ['integer'],
            'invites' => ['nullable', 'array'],
            'invites.*' => ['integer'],
            'students' => ['nullable', 'array'],
            'students.*' => ['integer'],
            'courses' => ['nullable', 'array'],
            'courses.*' => ['integer'],
            'materias' => ['nullable', 'array'],
            'materias.*' => ['integer'],
        ]);

        $director = $request->user();
        $colegioId = (int) $director->colegio_id;
        $deleted = 0;

        foreach (User::query()->where('colegio_id', $colegioId)->where('role', 'profesor')->whereIn('id', $data['teachers'] ?? [])->get() as $teacher) {
            $this->actions->deleteTeacher($director, ['teacher_name' => $teacher->name]);
            $deleted++;
        }

        foreach (TeacherInvite::query()->where('colegio_id', $colegioId)->whereIn('id', $data['invites'] ?? [])->get() as $invite) {
            Course::query()->where('colegio_id', $colegioId)->where('teacher_invite_id', $invite->id)->update(['teacher_invite_id' => null]);
            $invite->delete();
            $deleted++;
        }

        foreach (Student::query()->where('colegio_id', $colegioId)->whereIn('id', $data['students'] ?? [])->get() as $student) {
            $this->actions->deleteStudent($director, [
                'student_name' => $student->name,
                'student_id' => $student->id,
            ]);
            $deleted++;
        }

        $courses = Course::query()->where('colegio_id', $colegioId)->whereIn('id', $data['courses'] ?? [])->get();
        foreach ($courses as $course) {
            $course->students()->detach();
            $course->delete();
            $deleted++;
        }

        foreach (Materia::query()->where('colegio_id', $colegioId)->whereIn('id', $data['materias'] ?? [])->get() as $materia) {
            if ($materia->courses()->exists()) {
                continue;
            }
            $materia->delete();
            $deleted++;
        }

        return response()->json([
            'success' => true,
            'message' => $deleted === 0 ? 'No había elementos para eliminar.' : "Se eliminaron {$deleted} registro(s). Los cursos de un profesor quedan huérfanos para reasignar.",
            'deleted' => $deleted,
        ]);
    }

    public function destroySubject(Request $request): JsonResponse
    {
        $data = $request->validate([
            'subject_name' => ['required', 'string', 'max:120'],
            'grade' => ['nullable', 'string', 'max:60'],
        ]);

        $colegioId = (int) $request->user()->colegio_id;
        $courses = Course::query()
            ->where('colegio_id', $colegioId)
            ->whereRaw('LOWER(subject_name) = ?', [mb_strtolower($data['subject_name'])])
            ->get();

        if (! empty($data['grade'])) {
            $bucket = $this->gradeBucket($data['grade']);
            $courses = $courses->filter(function (Course $course) use ($data, $bucket) {
                if ($bucket !== null && $this->gradeBucket($course->grade) === $bucket) {
                    return true;
                }

                return mb_strtolower((string) $course->grade) === mb_strtolower($data['grade']);
            })->values();
        }
        foreach ($courses as $course) {
            $course->students()->detach();
            $course->delete();
        }

        $scope = $data['subject_name'].(! empty($data['grade']) ? ' · '.$data['grade'] : '');

        return response()->json([
            'success' => true,
            'message' => $courses->isEmpty()
                ? "No había cursos de {$scope}."
                : "Se eliminó {$scope} ({$courses->count()} curso(s)).",
            'deleted' => $courses->count(),
        ]);
    }

    /**
     * @param  array<int,int>  $courseIds
     */
    private function assignCoursesToInvite(int $colegioId, TeacherInvite $invite, array $courseIds): void
    {
        $openIds = Course::query()
            ->where('colegio_id', $colegioId)
            ->whereIn('id', $courseIds)
            ->get()
            ->filter(function (Course $course) use ($invite) {
                if ((int) $course->teacher_invite_id === (int) $invite->id) {
                    return true;
                }

                return ! $course->teacher_id && ! $course->teacher_invite_id;
            })
            ->pluck('id')
            ->all();

        if ($openIds === []) {
            return;
        }

        Course::query()
            ->where('colegio_id', $colegioId)
            ->whereIn('id', $openIds)
            ->update([
                'teacher_id' => null,
                'teacher_invite_id' => $invite->id,
            ]);

        $ids = collect($invite->course_ids ?? [])->merge($openIds)->unique()->values()->all();
        $invite->update(['course_ids' => $ids]);
    }

    private function serializeTeacher(User $teacher): array
    {
        return [
            'id' => $teacher->id,
            'kind' => 'teacher',
            'name' => $teacher->name,
            'email' => $teacher->email,
            'status' => 'activo',
            'courses' => $teacher->courses->map(fn (Course $course) => $this->courseChip($course))->values()->all(),
        ];
    }

    private function serializeInvite(TeacherInvite $invite): array
    {
        $courses = $invite->relationLoaded('courses')
            ? $invite->courses
            : Course::query()->where('teacher_invite_id', $invite->id)->withCount('students')->get();

        return [
            'id' => $invite->id,
            'kind' => 'invite',
            'name' => $invite->display_name ?? $invite->name,
            'email' => $invite->email,
            'invite_code' => $invite->invite_code,
            'status' => 'pendiente',
            'courses' => $courses->map(fn (Course $course) => $this->courseChip($course))->values()->all(),
        ];
    }

    private function serializeStudent(Student $student): array
    {
        return [
            'id' => $student->id,
            'name' => $student->name,
            'grade' => $student->grade,
            'section' => $student->section,
            'family_code' => $student->family_code,
            'courses_count' => $student->relationLoaded('courses') ? $student->courses->count() : ($student->courses_count ?? 0),
            'courses' => $student->relationLoaded('courses')
                ? $student->courses->map(fn (Course $course) => $this->courseChip($course))->values()->all()
                : [],
        ];
    }

    private function serializeCourse(Course $course): array
    {
        $teacherName = $course->teacher?->name ?: $course->pendingInvite?->name;
        $students = $course->relationLoaded('students') ? $course->students : collect();

        return [
            'id' => $course->id,
            'materia_id' => $course->materia_id,
            'subject_name' => $course->subject_name,
            'grade' => $course->grade,
            'section' => $course->section,
            'invite_code' => $course->invite_code,
            'teacher_id' => $course->teacher_id,
            'invite_id' => $course->teacher_invite_id,
            'teacher_name' => $teacherName,
            'pending' => (bool) $course->teacher_invite_id && ! $course->teacher_id,
            'orphan' => $teacherName === null || $teacherName === '',
            'assignment_status' => $teacherName ? 'occupied' : 'open',
            'students_count' => $course->students_count ?? $students->count(),
            'students' => $students->map(fn (Student $student) => [
                'id' => $student->id,
                'name' => $student->name,
                'grade' => $student->grade,
                'section' => $student->section,
            ])->values()->all(),
            'label' => trim($course->subject_name.' · '.$course->grade.($course->section ? ' '.$course->section : '')),
        ];
    }

    private function courseChip(Course $course): array
    {
        return [
            'id' => $course->id,
            'subject_name' => $course->subject_name,
            'grade' => $course->grade,
            'section' => $course->section,
            'students_count' => $course->students_count ?? null,
            'label' => trim($course->subject_name.' · '.$course->grade.($course->section ? ' '.$course->section : '')),
        ];
    }

    private function gradeBucket(?string $grade): ?int
    {
        $value = mb_strtolower(trim((string) $grade));
        if ($value === '') {
            return null;
        }

        $named = [
            'primero' => 1, 'primer' => 1, '1ro' => 1, '1ero' => 1, '1er' => 1,
            'segundo' => 2, '2do' => 2,
            'tercero' => 3, 'tercer' => 3, '3ro' => 3, '3er' => 3,
            'cuarto' => 4, '4to' => 4,
            'quinto' => 5, '5to' => 5,
            'sexto' => 6, '6to' => 6,
        ];
        foreach ($named as $needle => $bucket) {
            if ($value === $needle || str_contains($value, $needle)) {
                return $bucket;
            }
        }

        if (preg_match('/[1-6]/', $value, $match)) {
            return (int) $match[0];
        }

        return null;
    }
}
