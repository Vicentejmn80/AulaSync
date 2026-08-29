# Esquema de ActionPlan — AulaSync Multi-Intención

## Propósito

Reemplazar el modelo actual de "una intención → una acción" por un modelo de **planificación de N acciones** que pueda ser confirmado en lote, ejecutado de forma transaccional y auditado acción por acción.

El `ActionPlan` es la estructura central que viaja entre el planificador (LLM con Structured Outputs), el gestor de confirmaciones, el ejecutor transaccional y el frontend.

---

## Estructura principal: `ActionPlan`

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | `string` (uuid) | Identificador único del plan. Se usa para evitar duplicados en reintentos. |
| `status` | `string` | `pending` \| `needs_info` \| `confirmed` \| `executed` \| `failed` \| `cancelled` |
| `actions` | `Action[]` | Lista ordenada de acciones a ejecutar. |
| `summary` | `string` | Resumen en lenguaje natural del lote, generado por el planificador. |
| `requires_confirmation` | `bool` | `true` si al menos una acción de escritura necesita confirmación del director. |
| `all_or_nothing` | `bool` | `false` por defecto. Si es `true`, un fallo en cualquier acción hace rollback de todo el plan. |
| `created_at` | `string` (ISO 8601) | Timestamp de creación. |
| `updated_at` | `string` (ISO 8601) | Timestamp de última modificación. |

### Estados del plan

- **`pending`**: Plan recién creado, aún no mostrado al usuario.
- **`needs_info`**: Faltan slots obligatorios en al menos una acción. Se debe preguntar al director.
- **`confirmed`**: El director confirmó el plan; listo para ejecutar.
- **`executed`**: Todas las acciones se ejecutaron correctamente.
- **`failed`**: Al menos una acción falló y no se pudo recuperar.
- **`cancelled`**: El director canceló el plan.

---

## Estructura de cada `Action`

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | `string` | Identificador único dentro del plan. |
| `type` | `string` | Nombre de la herramienta del catálogo unificado: `create_teacher`, `enroll_students_course`, `update_student`, `delete_teacher`, `get_course_performance`, etc. |
| `entity` | `string` | Entidad principal afectada: `teacher`, `student`, `course`, `grade`, `attendance`, `analytics`. |
| `params` | `array` | Parámetros normalizados listos para pasar a `DirectorUnifiedAgentService::execute()`. |
| `status` | `string` | `pending` \| `needs_info` \| `confirmed` \| `executed` \| `failed` \| `skipped` |
| `missing_slots` | `Slot[]` | Slots que faltan por completar para esta acción. |
| `result` | `array \| null` | Resultado devuelto por la ejecución (mensaje, success, data). |
| `error` | `string \| null` | Mensaje de error si la acción falló. |
| `depends_on` | `string[]` | IDs de acciones que deben ejecutarse antes que esta. |
| `audit_log_id` | `int \| null` | ID del registro de auditoría asociado. |
| `action_plan_id` | `string \| null` | ID del ActionPlan al que pertenece la acción. |
| `action_id` | `string \| null` | ID de la acción dentro del plan. |
| `confirmation_required` | `bool` | Si esta acción concreta requiere confirmación. |

### Estados de una acción

- **`pending`**: Acción planificada, sin confirmar ni ejecutar.
- **`needs_info`**: Faltan slots. El plan entero pasa a `needs_info`.
- **`confirmed`**: Confirmada por el director (o no requería confirmación).
- **`executed`**: Ejecutada con éxito.
- **`failed`**: Ejecutada pero falló. El resultado incluye `error` con el mensaje.
- **`skipped`**: Omitida, por ejemplo porque una dependencia falló. Se registra en `DirectorAiOperationLog` con `status = skipped`.

---

## Estructura de `Slot`

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `name` | `string` | Nombre del parámetro faltante, ej. `grade`, `section`, `teacher_name`. |
| `description` | `string` | Pregunta legible para el director, ej. "¿De qué grado se trata?". |
| `required` | `bool` | Si es obligatorio para poder ejecutar la acción. |
| `value` | `mixed` | Valor actual del slot (`null` si aún no se llenó). |
| `source` | `string` | `user` \| `context` \| `llm` \| `default` |

---

## Ejemplo de ActionPlan

```json
{
  "id": "plan_01j8xyz123",
  "status": "needs_info",
  "actions": [
    {
      "id": "a1",
      "type": "create_students_batch",
      "entity": "student",
      "params": {
        "students_data": [
          { "name": "Vicente", "grade": "3ro", "section": "A", "subject_name": null, "teacher_name": null },
          { "name": "Georgina", "grade": "1ro", "section": null, "subject_name": "Matemática", "teacher_name": null }
        ]
      },
      "status": "confirmed",
      "missing_slots": [],
      "result": null,
      "error": null,
      "depends_on": [],
      "audit_log_id": null,
      "confirmation_required": true
    },
    {
      "id": "a2",
      "type": "update_student",
      "entity": "student",
      "params": {
        "student_name": "Carlos",
        "new_grade": "4to",
        "new_section": "B"
      },
      "status": "confirmed",
      "missing_slots": [],
      "result": null,
      "error": null,
      "depends_on": [],
      "audit_log_id": null,
      "confirmation_required": true
    },
    {
      "id": "a3",
      "type": "delete_teacher",
      "entity": "teacher",
      "params": {
        "teacher_name": "Salvador"
      },
      "status": "confirmed",
      "missing_slots": [],
      "result": null,
      "error": null,
      "depends_on": [],
      "audit_log_id": null,
      "confirmation_required": true
    },
    {
      "id": "a4",
      "type": "get_course_performance",
      "entity": "analytics",
      "params": {
        "subject_name": null,
        "grade": "2do",
        "section": "A"
      },
      "status": "needs_info",
      "missing_slots": [
        {
          "name": "subject_name",
          "description": "¿De qué materia quieres ver el rendimiento de 2do A?",
          "required": true,
          "value": null,
          "source": "user"
        }
      ],
      "result": null,
      "error": null,
      "depends_on": [],
      "audit_log_id": null,
      "confirmation_required": false
    }
  ],
  "summary": "Voy a crear a Vicente en 3ro A y a Georgina en 1ro con Matemática, cambiar a Carlos a 4to B, eliminar al profesor Salvador y luego mostrarte el rendimiento de 2do A.",
  "requires_confirmation": true,
  "all_or_nothing": false,
  "created_at": "2026-08-26T12:00:00Z",
  "updated_at": "2026-08-26T12:00:00Z"
}
```

---

## Planificación con OpenAI Structured Outputs

`DirectorActionPlannerService` consume la API de OpenAI con:

```json
{
  "response_format": {
    "type": "json_schema",
    "json_schema": {
      "name": "action_plan",
      "strict": true,
      "schema": { ... }
    }
  }
}
```

El modelo recibe el catálogo completo de herramientas (`DirectorUnifiedAgentService::TOOLS`) y devuelve directamente un `ActionPlan` válido. Si OpenAI no está disponible, el servicio cae al extractor determinista `DirectorIntentExtractorService` y convierte su salida al mismo formato `ActionPlan`.

### Flujo en el controlador

1. `AICommandController::handle()` llama a `DirectorActionPlannerService::plan()`.
2. Si el plan tiene `status = needs_info`, se guarda en `DirectorConfirmationManager` y se pregunta por el primer slot pendiente.
3. Si el plan está completo, se convierte al formato `pending_actions` existente y se pide confirmación. El campo `ActionPlan.summary` se usa como mensaje de confirmación, por ejemplo: *"Voy a hacer: 1. ... 2. ..."*.
4. Al confirmar (o decir "sí"), `executePending()` ejecuta cada acción dentro de la transacción global.
5. En ambos casos, la respuesta JSON incluye el `ActionPlan` completo bajo la clave `action_plan`, para que el frontend pueda mostrar resumen, acciones numeradas, slots pendientes y botones de confirmar/cancelar.

### Cola de confirmación multi-acción

`DirectorConfirmationManager` mantiene el `ActionPlan` completo en sesión mientras se completan los slots y se espera la confirmación. Si una acción tiene slots pendientes, se pregunta solo por ese slot; el resto del plan y sus valores ya completados se conservan intactos.

### Auditoría por acción

Cada acción genera un registro en `director_ai_operation_logs` con:

- `action_plan_id`: ID del plan.
- `action_id`: ID de la acción dentro del plan.
- `intent`: tipo de acción ejecutada.
- `input_payload`: texto y datos de entrada.
- `result_payload` / `error_payload`: resultado o error.
- `status`: `pending_confirmation`, `confirmed`, `verified`, `failed`, `skipped`.

Si una acción pendiente no tiene log previo (por ejemplo, creada manualmente en sesión), `executePending` crea el log justo antes de ejecutarla.

### `create_students_batch` — alumnos en lote con grado por alumno

`params.students_data` es un array con UN item por alumno: `{name, grade, section?, subject_name?, teacher_name?}`. Cada alumno lleva su propio `grade`, así que una orden como "crea a Juan Pérez en 1ro y a María Gómez en 3ro con Matemática" se resuelve como UNA sola acción con dos items en `students_data`, sin forzar un grado común.

`DirectorActionService::createStudentsBatch` acepta `students_data` como formato canónico y sigue aceptando el formato legado plano `names` + `grade` (compartido para todo el lote) mediante un adaptador interno, para no romper el parser regex de `AICommandController`/`DirectorIntentExtractorService`. Internamente agrupa los alumnos por `(grade, section, subject_name, teacher_name)` para resolver o crear el curso una sola vez por grupo, y devuelve `courses: [{grade, section, course, enrolled_count, course_created, placement_note}, ...]` en vez de un `course`/`enrolled_count` únicos.

## Compatibilidad con el sistema actual

- El campo `Action.type` usa los mismos nombres que `DirectorUnifiedAgentService::TOOLS`.
- `Action.params` coincide con los argumentos que hoy devuelve OpenAI y que se pasan a `DirectorUnifiedAgentService::execute($director, $tool, $args)`.
- `DirectorConfirmationManager` ahora soporta planes multi-acción y mantiene compatibilidad con planes antiguos de una sola acción (`intent`/`data`/`slots`).
- Las acciones de lectura (`get_*`) se ejecutan inmediatamente si no tienen slots pendientes; las de escritura siempre requieren confirmación antes de ejecutarse.

### Bug conocido del flujo legado

El test `test_assign_two_teachers_in_one_phrase_produces_two_actions` de `DirectorAICommandTest` falla en el flujo legado (sin planificador). El extractor local no separa correctamente dos profesores en una sola frase y genera una sola acción `create_teacher` con ambos nombres concatenados.

**Solución implementada**: con el planificador activo, `DirectorActionPlannerService` identifica correctamente ambas asignaciones y genera dos acciones `assign_teacher` separadas. El test alternativo `test_assign_two_teachers_in_one_phrase_with_planner` en `DirectorActionPlannerFlowTest` demuestra que el caso de uso real sí funciona.

---

## Reglas de negocio

1. **Un plan puede mezclar escritura y lectura**, pero la respuesta final solo se genera después de ejecutar todas las acciones.
2. **Si hay slots pendientes**, se pregunta por el primero y se conserva el resto del plan intacto.
3. **Si una acción falla**:
   - `all_or_nothing = false`: se registra el error, se continúa con las demás, y al final se informa qué falló. Cada acción reporta `success`, `message`, `data` y `error`.
   - `all_or_nothing = true`: se hace rollback de las acciones ya ejecutadas dentro de la misma transacción.
4. **Si el planificador devuelve un tipo de acción desconocido o parámetros inválidos**, el controlador rechaza el plan con un mensaje claro (HTTP 422) en lugar de ejecutarlo o ignorarlo silenciosamente.
5. **Las acciones de lectura no requieren confirmación** y pueden ejecutarse antes que las de escritura para nutrir el contexto de la respuesta final.
6. **El resumen en lenguaje natural** se genera una sola vez por el planificador y se reutiliza para la confirmación.
