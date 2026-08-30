<?php

namespace App\Support;

/**
 * Etiquetas en español para el Founder Center. Los eventos se guardan con
 * códigos internos; aquí se muestran como frases que un founder puede leer.
 */
class SuperAdminCopy
{
    public static function source(?string $value): string
    {
        return self::pick($value, [
            'auth' => 'Inicio de sesión',
            'director_ai' => 'Chat del director (acciones)',
            'director_data_agent' => 'Chat del director (consultas)',
            'teacher_ai' => 'Chat del docente',
            'intelligence' => 'Documentos de inteligencia',
            'import' => 'Importación',
            'teacher' => 'Panel del docente',
            'evaluation_controller' => 'Evaluaciones',
            'ai_controller' => 'Planificación con IA',
            'ai_controller.save' => 'Guardar planificación',
            'bulk_plan' => 'Planificación masiva',
            'manual_planning.store' => 'Planificación manual',
            'system' => 'Sistema',
        ]);
    }

    public static function action(?string $value): string
    {
        return self::pick($value, [
            'login' => 'Inició sesión',
            'store' => 'Guardó un registro',
            'save' => 'Guardó',
            'get_students' => 'Consultó la lista de alumnos',
            'get_student' => 'Consultó un alumno',
            'get_courses' => 'Consultó cursos',
            'get_teachers' => 'Consultó profesores',
            'verify_teacher' => 'Verificó si alguien es profesor',
            'verify_student' => 'Verificó un alumno',
            'get_grades' => 'Consultó notas',
            'get_attendance' => 'Consultó asistencia',
            'get_evaluations' => 'Consultó evaluaciones',
            'get_assignments' => 'Consultó tareas y actividades',
            'get_student_performance' => 'Consultó el rendimiento de un alumno',
            'get_course_performance' => 'Consultó el rendimiento de un curso',
            'compare_courses' => 'Comparó cursos',
            'get_at_risk_students' => 'Consultó alumnos en riesgo',
            'get_declining_students' => 'Consultó alumnos que bajan de nota',
            'get_academic_trends' => 'Consultó tendencias académicas',
            'generate_school_report' => 'Pidió un reporte del colegio',
            'get_rankings' => 'Consultó rankings',
            'get_section_counts' => 'Consultó cupos por sección',
            'query_academic' => 'Hizo una consulta académica',
            'combined_student' => 'Consultó varios datos de un alumno',
            'needs_course' => 'Faltó indicar el curso',
            'out_of_scope' => 'Pregunta fuera de lo que puede responder',
            'unclear' => 'No entendió la pregunta',
            'clarify' => 'Pidió aclaración',
            'explain_from_memory' => 'Explicó con datos de la conversación',
            'rate_limited' => 'Llegó al límite diario de consultas',
            'create_course' => 'Crear curso',
            'create_courses_batch' => 'Crear varios cursos',
            'create_subject' => 'Crear materia',
            'create_teacher' => 'Crear docente',
            'create_student' => 'Crear alumno',
            'create_students_batch' => 'Cargar varios alumnos',
            'assign_teacher' => 'Asignar docente a un curso',
            'sync_all_enrollments' => 'Sincronizar matrículas',
            'manage_invite_code' => 'Consultar código DOC-',
            'get_teacher_invite_code' => 'Consultó el código de un docente',
            'unassign_teacher' => 'Desasignar docente',
            'update_course' => 'Modificar curso',
            'update_student' => 'Actualizar alumno',
            'enroll_students_course' => 'Inscribir alumnos a un curso',
            'unenroll_students_course' => 'Retirar alumnos de un curso',
            'delete_teacher' => 'Eliminar docente',
            'delete_teacher_invite' => 'Cancelar invitación de docente',
            'delete_all_teachers' => 'Eliminar todos los docentes',
            'delete_course' => 'Eliminar curso',
            'delete_all_courses' => 'Eliminar todos los cursos',
            'delete_student' => 'Eliminar alumno',
            'createCourse' => 'Crear curso',
            'createActivity' => 'Crear actividad',
            'modifyActivity' => 'Modificar actividad',
            'registerStudent' => 'Registrar alumno',
            'bulkPlan' => 'Planificar la semana',
            'deleteActivities' => 'Borrar actividades',
            'deleteResource' => 'Borrar un recurso',
            'setGrade' => 'Cargar una nota',
            'setGradeBatch' => 'Cargar notas en lote',
            'publishGrades' => 'Publicar notas',
            'createEvaluation' => 'Crear evaluación',
            'attachEvaluationToPlan' => 'Vincular evaluación al plan',
            'getCalendarContext' => 'Consultó el calendario',
            'findStudent' => 'Buscó un alumno',
            'getGradebookContext' => 'Consultó el cuaderno de notas',
            'analyze_group' => 'Analizó el grupo',
            'analyze_student' => 'Analizó un alumno',
            'detect_attention' => 'Detectó alumnos que necesitan atención',
            'generate_planning' => 'Generó planificación',
            'generate_activities' => 'Generó actividades',
            'generate_tasks' => 'Generó tareas',
            'generate_report' => 'Generó reporte',
            'intelligence_apply' => 'Aplicó un documento',
            'intelligence_forward_director' => 'Envió un documento al director',
            'intelligence_apply_proposal' => 'Aplicó una propuesta',
        ]);
    }

    public static function error(?string $value): string
    {
        return self::pick($value, [
            'tool_failed' => 'Falló al consultar los datos',
            'daily_limit_exceeded' => 'Llegó al límite diario de consultas',
            'needs_clarification' => 'Hizo falta aclarar la pregunta',
            'execute_exception' => 'Error interno al ejecutar la acción',
        ], true);
    }

    public static function status(?string $value): string
    {
        return self::pick($value, [
            'success' => 'Correcto',
            'failed' => 'Falló',
            'unresolved' => 'Sin respuesta clara',
            'pending_confirmation' => 'Esperando confirmación',
            'received' => 'Recibido',
            'verified' => 'Hecho',
            'confirmed' => 'Confirmado',
            'executed' => 'Hecho',
            'applied' => 'Aplicado',
            'estable' => 'Estable',
            'degradado' => 'Con problemas',
            'critico' => 'Crítico',
            'activo' => 'Activo',
            'riesgo' => 'En riesgo',
            'inactivo' => 'Inactivo',
        ]);
    }

    public static function role(?string $value): string
    {
        return self::pick($value, [
            'super_admin' => 'Super admin',
            'director' => 'Director',
            'profesor' => 'Docente',
            'representante' => 'Representante',
        ]);
    }

    public static function category(?string $value): string
    {
        return self::pick($value, [
            'roster' => 'Plantel y matrícula',
            'academic' => 'Consultas académicas',
            'planning' => 'Planificación',
            'intelligence' => 'Documentos',
            'auth' => 'Acceso',
            'other' => 'Otros',
        ]);
    }

    public static function day(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        try {
            return \Carbon\Carbon::parse((string) $value)->format('d/m/Y');
        } catch (\Throwable) {
            return (string) $value;
        }
    }

    /**
     * @param  array<string, string>  $map
     */
    private static function pick(?string $value, array $map, bool $appendUnknownSuffix = false): string
    {
        if ($value === null || trim($value) === '') {
            return '—';
        }

        if (isset($map[$value])) {
            return $map[$value];
        }

        $human = preg_replace('/([a-z0-9])([A-Z])/', '$1 $2', $value) ?? $value;
        $human = str_replace(['_', '-', '.'], ' ', $human);
        $human = trim(preg_replace('/\s+/', ' ', $human) ?? $human);
        $human = $human === '' ? $value : mb_strtolower($human);
        $human = mb_strtoupper(mb_substr($human, 0, 1)).mb_substr($human, 1);

        if ($appendUnknownSuffix && $human === $value) {
            return $value;
        }

        return $human;
    }
}
