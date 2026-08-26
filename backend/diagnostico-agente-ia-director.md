# DIAGNÓSTICO DEL AGENTE DE IA (Director)

**Alcance:** código real en `backend/` (no logs de producción de las conversaciones fallidas).  
**Fuentes principales:** `AICommandController.php`, `DirectorDataAgentService.php`, `DirectorAIInterpreterService.php`, `DirectorConversationContextService.php`, `DirectorActionService.php`, `SchoolRosterContextService.php`, `DirectorAnalyticsQueryService.php`, `ai-assistant-bubble.blade.php`.  
**Fecha del inventario:** 2026-08-25.

---

## 1. Resumen ejecutivo

### Hallazgos principales

1. **No hay un único “agente con tools”.** Hay dos orquestadores: el **Data Agent** (consultas de solo lectura) y el **intérprete LLM + parsers locales** (mutaciones CRUD). El enrutado decide cuál corre. Una frase de “dame el código de Manuel” **no entra** al Data Agent ni dispara `manage_invite_code`.
2. **`manage_invite_code` existe en el CRUD, pero no está registrado como tool del LLM.** El código DOC- se espera que el modelo lo lea del roster en texto, sin llamar herramientas. `get_teachers` **no devuelve** `invite_code`.
3. **El parser local no trata “alumna/alumnas” como alumno.** Eso hace que “Crea a la alumna Georgina e intégrala al curso” caiga en `create_course` por la palabra `curso`. El extractor de nombres de alumnos, si ve “alumno X y a la alumna Y”, **devuelve un solo nombre**.
4. **La confirmación “sí” solo funciona si Laravel guardó `director_ai_pending_actions`.** Pedir el grado con `needs_clarification` **no** guarda pending. `CHAT_HISTORY_KEY` está declarado y **nunca se usa**.
5. **El batch de mutaciones no hace rollback global.** `executePending` sigue con la siguiente acción si una falla; las fallidas quedan en sesión. Un `create_students_batch` sí va en **una** transacción DB (si explota un enroll, se revierte ese lote).

### Prioridades

1. Enrutar “código DOC- / código de [profesor]” a `manage_invite_code` o a una tool `get_teacher_invite_code`, y devolver el código aunque el profesor ya esté activo.
2. Incluir `alumna(s)` en detección y extracción; extraer **todos** los nombres de una frase; no exigir grado para armar pending (guardar names y preguntar grado).
3. Unificar pending: no preguntar confirmación en prosa del LLM sin `rememberPendingActions`; “sí” / “Si” debe reanudar el plan o el slot vacío (grado), no abrir otro flujo.

---

## 2. Inventario de herramientas

Hay **dos catálogos**. No son el mismo array.

### 2.1 Data Agent (`DirectorDataAgentService`)

Se registran en `toolDefinitions()` y se ejecutan en `execute()`. Constante `TOOLS` (líneas 20–41).

| Tool | Parámetros (schema) | Qué hace | Qué retorna |
|------|---------------------|----------|-------------|
| `get_students` | grade, section, sort | Lista alumnos del colegio | `{message, data}` vía analytics |
| `get_student` | student_name | Un alumno por nombre | idem |
| `get_courses` | grade, section, subject_name | Lista cursos | idem |
| `get_teachers` | (ninguno) | Lista profesores **activos** (`users.role=profesor`) | mensaje con nombre + cursos; **sin** `invite_code` |
| `verify_teacher` | teacher_name | ¿Está registrado como profesor? | exists / ambiguous / es alumno |
| `verify_student` | student_name | ¿Está registrado como alumno? | analog |
| `get_grades` | grade, section, subject_name, student_name | Calificaciones | idem |
| `get_attendance` | grade, section, student_name, days | Asistencia | idem |
| `get_evaluations` | grade, section, subject_name | Evaluaciones | idem |
| `get_assignments` | grade, section, subject_name | Tareas | idem |
| `get_student_performance` | student_name | Rendimiento de un alumno | idem |
| `get_course_performance` | grade, section, subject_name | Rendimiento de un curso | idem |
| `compare_courses` | grade, grade_b, section, section_b, subject_name | Compara cursos | idem |
| `get_at_risk_students` | grade, section, subject_name | Bajo rendimiento | idem |
| `get_declining_students` | grade, section | Bajaron promedio | idem |
| `get_academic_trends` | metric, weeks | Tendencias | idem |
| `generate_school_report` | grade, section | Informe | idem |
| `get_rankings` | metric, grade, section, limit, sort | Ranking | idem |
| `query_academic` | (legacy, no está en el schema de `toolDefinitions`, sí en `TOOLS` + `execute`) | Delega a `AICommandController::queryAcademic` | vistas profesor/alumno/colegio |
| `get_section_counts` | **en `TOOLS` y en `execute()`, no en `toolDefinitions()`** | Conteos por sección | el LLM **no puede elegirla** por function-calling |

**Estado:** las 18 de `toolDefinitions()` son funcionales para consulta. `get_section_counts` está **a medias** (ejecutable, no anunciada al LLM). **No hay** tool de código DOC-.

Cómo se registran:

- Array PHP en `toolDefinitions()`, formato OpenAI `{type: function, function: {name, parameters}}`.
- Si el plan local no basta, `tryLlmPlan()` las manda con `tool_choice: auto`.
- El plan local (`planFromText`) elige tools **sin** LLM por regex.

### 2.2 Intérprete de mutaciones (`DirectorAIInterpreterService`)

`toolDefinitions()` (líneas 216–395) + **merge** de las tools del Data Agent (línea 395).

Mutaciones (intents que Laravel ejecuta tras confirmar):

| Intent / tool | Parámetros clave | Qué hace | Retorno al confirmar |
|---------------|------------------|----------|----------------------|
| `create_teacher` | teacher_name, subject_name, grades, section | Crea `TeacherInvite` + opcional cursos | JSON de confirmación / resultado |
| `create_course` | subject_name, grades, section, teacher_name | Crea o reusa curso (`was_existing`) | idem |
| `assign_teacher` | teacher_name, subject_name, grades | Asigna materia/grados | idem |
| `create_students_batch` | names[], **grade** (required en schema), section, subject_name, teacher_name | Crea alumnos y puede matricular | idem |
| `enroll_students_course` | names, subject_name, grade, all_in_grade | Matricula en curso existente (o lo resuelve) | idem |
| `unenroll_students_course` | names, subject_name, grade | Desmatricula | idem |
| `unassign_teacher` | teacher_name, subject… | Quita asignación | idem |
| `update_course` / `update_student` | según schema | Edita | idem |
| `delete_*` | según schema | Borrados | idem |
| `query_academic` | query_type enum | Consulta legacy | sin confirmación (no está en `intentRequiresConfirmation`) |

**No encontré `manage_invite_code` en `DirectorAIInterpreterService::toolDefinitions()` ni en `allowedIntents()`.**  
Sí existe en `AICommandController::runIntent()` y `parseManageInviteCode()`.

### 2.3 Herramientas que pediste vs código

| Caso | ¿Existe? | Evidencia |
|------|----------|-----------|
| Obtener código de invitación de un profesor | **Parcial.** `DirectorActionService::manageInviteCode` (`operation=query`) + parser local `parseManageInviteCode`. **No** es tool del LLM. `get_teachers` no trae DOC-. El roster markdown **sí** lista DOC- de invitaciones y, si hay `TeacherInvite` claimed, el del activo. | `manageInviteCode` ~1387; interpreter tools 216–395; `getTeachers` 78–116; roster 74–114 |
| Crear múltiples alumnos en batch | **Sí, con huecos.** Intent `create_students_batch` + `createStudentsBatch()`. Extracción local de **varios** nombres es frágil (ver Parte 7). Schema **exige `grade`**. | interpreter 247–256; ActionService 369+; `extractStudentNames` 3997–4051 |
| Matricular en curso existente sin crear curso | **Sí.** `enroll_students_course`. `createStudentsBatch` también busca curso y **puede crearlo** si hay materia+profesor (`resolveCourseForPlacement` con flag). | interpreter 258–268; ActionService 410–423 |
| Verificar si un curso existe antes de crearlo | **En backend, sí.** `findExistingCourse` / `was_existing` en `createCourse`. **No** hay tool `course_exists` para el LLM. El roster lista cursos (tope 120). | ActionService ~671, 1645 |

### 2.4 Cómo “sabe” la IA qué hay

1. **Mutaciones:** system prompt del intérprete + lista OpenAI `tools`.
2. **Consultas Data Agent:** otro `toolDefinitions()` + plan regex; el intérprete **también** recibe las tools de datos (merge), pero el prompt dice que códigos DOC- se respondan **sin tools**, solo con el roster.
3. **Parsers locales** (`detectIntent`, `extractMultipleActions`) no pasan por el LLM.

---

## 3. Análisis del flujo de procesamiento

### Archivos que participan

| Archivo | Rol |
|---------|-----|
| `resources/views/components/ai-assistant-bubble.blade.php` | POST `prompt`, `conversation` (todo el array de mensajes del widget), `screen_context`, `confirmed` |
| `AICommandController::handle` | Orquestador único del director |
| `DirectorDataAgentService` | Routing `detectIntent`/`shouldUseDataAgent`, plan, execute tools, compose |
| `DirectorAnalyticsQueryService` | SQL de las tools get_* |
| `DirectorAIInterpreterService` | LLM mutaciones + narración |
| `DirectorConversationContextService` | Memoria de sesión (`director_ai_conversation_context`) |
| `DirectorActionService` | Ejecución CRUD |
| `SchoolRosterContextService` | Markdown de nómina inyectado al system prompt |
| `DirectorCommandFocusService` | Recorte “órdenes clave” antes del LLM |
| `AICommandHandlerController` | **Otro** chatbot (docente/planificaciones), no el de director |

No hay `DirectorDataAgentService` “llamando tools públicas” como API REST aparte: `execute()` es el despacho interno.

### Orden real (`handle`, ~73–360)

```
1. Auth + validación
2. Si confirmed=true → executePending (sesión, NO el pending_actions del cliente)
3. PRIORIDAD 1: si hay pending y texto afirmativo → executePending
                 si negativo → cancelar
4. PRIORIDAD 2: button_action (menú híbrido)
5. PRIORIDAD 3: si chat_mode ≠ main_menu Y hay chat_subject → handleInMode
6. PRIORIDAD 4: detectHybridIntent (creating/consulting/…) puede pedir subject
7. dataAgent.routeDecision (log)
8. Otra vez afirmativo/negativo vs PENDING
9. Si shouldUseDataAgent o isOutOfScope → respondWithDataAgent (plan + answer)
10. Si no: interpreter.interpret(conversation del cliente, memory de sesión)
    + enrich + extractMultipleActions + detectMultiIntent + detectIntent local
11. Si solo tools de datos → Data Agent
12. Si mutaciones → prepareActions (confirmación o ejecución inmediata)
```

**No hay un paso único llamado “planificación” para CRUD.** El LLM elige tools; Laravel hidrata y pide confirmación.

### Puntos de fallo (evidencia)

- **Paso 9 vs 10:** `looksLikeMutation` gana a consultas. `crea` mete la frase al CRUD aunque el resto sea “código” (el regex de mutación no incluye “código”, así que “proporciones el código” **no** es mutación).
- **Paso 9:** `looksLikeDataQuery` **no** incluye `codigo` ni `proporcion`. Esa frase queda `intent: unknown`, `agent: llm_fallback` y **no** usa Data Agent.
- **Paso 5:** modo `creating` + subject puede interceptar turnos cortos **si no hay pending**.
- **Confirmación:** `pending_actions` del cliente es “display-only” (`executePending` línea 749). Si la sesión se perdió, el botón Confirmar del UI tampoco salva el plan.

Diagrama de flujo (actual):

```
Usuario
  → bubble (conversation[], prompt)
  → handle()
       ├─ pending + "sí" → executePending (sesión)
       ├─ Data Agent? → plan/tools/analytics/compose
       └─ CRUD → LLM tools y/o regex
              → prepareActions
                    ├─ sin confirmación (query_academic, manage_invite_code)
                    └─ rememberPending → “¿confirmas?”
```

---

## 4. Análisis de contexto y memoria

### Qué hay en sesión (constantes en el controller)

| Clave | ¿Se usa? | Contenido |
|-------|----------|-----------|
| `director_ai_pending_actions` | Sí | Array `{intent, data, audit_log_id}` |
| `chat_pending` / `chat_pending_batch` | Sí, espejo resumido | items, total, type |
| `director_ai_batch_queue` | Sí | resto en modo uno-por-uno |
| `chat_mode` | Sí | main_menu, creating, consulting, … |
| `chat_subject` | Sí | students, teachers, … |
| `director_ai_conversation_context` | Sí | last_user_text, last_actions, teacher_name, student_names, grades, invite_code, focus, last_error |
| `chat_history` (`CHAT_HISTORY_KEY`) | **No.** Solo la constante. **No encontré** `session()->put(self::CHAT_HISTORY_KEY` | — |

`AiChatHistoryService::SESSION_KEY = ai_chat_history` es otro almacén (telemetría), no el hilo del director.

### Cómo se usa la historia

- El **widget** manda **todos** los mensajes `user`/`assistant` en `conversation`.
- El intérprete toma **los últimos 32** turnos (`array_slice($conversation, -32)`).
- Eso **sí** se pasa al LLM en cada `interpret()`.
- El Data Agent **no** recibe ese historial de chat; recibe `conversationContext->current()` (slots: grado, alumno, etc.) en `plan()`.
- Resolución de “ese alumno / ellos”: Data Agent `looksLikeFollowUp` + `planFollowUp` / memoria `last_student`. **No** hay resolución equivalente robusta en CRUD para “ella” = Georgina salvo lo que el LLM vea en los 32 turnos.

### Por qué se pierde el hilo a los 3–4 turnos

Evidencia, no suposición de “el modelo se olvida”:

1. **Pending no se escribe** en `needs_clarification` (falta de grado, materia, etc.). El siguiente “sí” no tiene plan.
2. **`detectHybridIntent`** pone `chat_mode=creating` en cualquier “crea…”. El turno 3 puede ir a `handleInMode` / pedir subject.
3. **CRUD y Data Agent no comparten el mismo memory object** con el historial de chat (solo el intérprete ve `conversation`).
4. Roster y memoria de slots **no** guardan “última persona mencionada que no llegó a pending”.

---

## 5. Confirmaciones y batch

### Cómo funciona

1. `prepareActions` → `rememberPendingActions` + `pendingConfirmationResponse`.
2. UI muestra botones; el usuario puede decir “sí” o `confirmed: true`.
3. `isAffirmativeText`: `"Si"` / `"Sí"` se normalizan a `si` y **sí están** en la lista corta (líneas 4180–4182). **La condición que falla no es el acento.**
4. Ejecución: solo `session(PENDING_SESSION_KEY)`. Si está vacío → 422 “No hay acciones pendientes”.

### ¿Múltiples confirmaciones?

Sí a nivel de **array de acciones** (varios `create_teacher`, o teacher+student si el detector armó varias).  
`extractMultipleNames` **solo corre en contexto de profesores** (`if (! $isTeacherContext) return []`).  
Varios alumnos **no** se expanden con esa función.

### Si una acción falla en el batch

`executePending` (762–862): try/catch **por acción**. Las que ya hicieron commit **no se deshacen**. Las fallidas se reponen en `PENDING_SESSION_KEY`.  
`createStudentsBatch` envuelve el foreach en **una** `DB::transaction`: un throw revierte **ese** lote de alumnos, no otras intents del mismo pending.

No hay “todos fallan” como transacción única del pending completo. La sensación de “todos fallan” encaja más con: **ninguna acción llegó a pending** (clarificación) o **una sola acción mal parseada**.

---

## 6. Lo que el LLM sabe

### System prompt del intérprete (`systemPrompt`)

- Identidad AulaSync, sándwich de respuesta, no Nova.
- Roster en markdown (conteos, profesores, invitaciones DOC-, cursos, alumnos; límites 80/80/120/200).
- `Memoria conversacional: {json de session context}`.
- Instrucciones explícitas: códigos DOC- **sin tools**; mutaciones **con tools**; analítica con get_*.
- MULTI-INTENT, listas de alumnos, “Crea al alumno X” → `create_students_batch`, **nunca `create_course` por mencionar curso** (regla 6 del prompt). El **parser local no obedece esa regla** (ver Caso 4).

### Datos de usuario

- Colegio: nombre en roster; `colegio_id` **no** lo debe enviar el modelo (el backend lo inyecta).
- Rol: implícito (prompt de director). No hay un campo `role=` aparte en el prompt.

### Base de datos en contexto

- **Sí, recorte:** profesores, invitaciones, cursos, alumnos (límites arriba).
- **No** notas/asistencia en el roster. Eso va por tools get_*.
- Data Agent: **no** inyecta el roster completo; consulta SQL al ejecutar tools.

---

## 7. Diagnóstico de casos fallidos

*No hay trazas de esas conversaciones en el repo. Lo siguiente es el camino que el código toma con esas frases exactas.*

### Caso 1: “Necesito que me proporciones el código de Manuel Vázquez”

| Pregunta | Respuesta con evidencia |
|----------|-------------------------|
| ¿Qué tool debería usarse? | `manage_invite_code` (query) **o** leer el roster. Ideal: tool `get_teacher_invite` / ampliar `get_teachers`. |
| ¿Existe? | Backend sí. Tool LLM **no**. `get_teachers` no incluye código. |
| ¿Por qué no se usó? | 1) `looksLikeMutation` es false (no hay crea/agrega…). 2) `detectIntent` Data Agent → `unknown` / `llm_fallback` (`looksLikeDataQuery` no tiene “codigo”/“proporcion”). 3) `detectIntent` CRUD de `manage_invite_code` exige `codigo` **y** (`consulta`\|`estado`\|`mostrar`\|`tiene`\|`dame`). Esta frase tiene `codigo` (normalizado) pero **no** esas segundas palabras (`proporciones` no cuenta). 4) Caé al intérprete, que tiene orden de **no llamar tools** para DOC- y responder del roster. Si Manuel es activo y el `TeacherInvite` no está en el recorte de 80, o el modelo no busca la línea, falla. `verify_teacher` tampoco devuelve DOC-. |

### Caso 2: “Crea al alumno Vicente José y a la alumna Georgina Vázquez”

| Pregunta | Respuesta |
|----------|-----------|
| ¿Se detectan 2 alumnos? | **En el parser local, no de forma fiable.** `detectIntent` ve `\balumno` → `create_students_batch`. `extractStudentNames`: no hay lista con `:`; cae a `extractNamedPersonAfterRole(..., 'alumno')` con regex `alumnos?` (no `alumna`). Captura **desde el primer “alumno” hasta el final**. Devuelve **un solo** elemento `[$single]`. |
| ¿Se creó el primero? | Solo si hubo grado + confirmación + ejecución. **Sin grado**, `parseCreateStudentsBatch` responde *“¿En qué grado debo crear a los estudiantes?”* y **`handle` sale con `needs_clarification` sin `rememberPendingActions`**. |
| ¿Por qué se pierde el segundo? | 1) Un solo string de nombre (Vicente + texto + Georgina). 2) `extractMultipleNames` **no corre** para alumnos. 3) `splitIntentClauses` parte por `y crea al alumno`, no por `y a la alumna`. 4) El LLM *podría* mandar `names: [Vicente, Georgina]` si `interpret` está enabled; en testing el intérprete está **apagado** salvo flag. En producción depende de OpenAI. El fallback local no duplica esa calidad. |

### Caso 3: “Si” (confirmando Georgina)

| Pregunta | Respuesta |
|----------|-----------|
| ¿`isAffirmativeText("Si")`? | **Sí.** Normaliza a `si`, está en `$short`. |
| ¿Se guardó pending? | **Solo si** el turno anterior pasó por `prepareActions` → `rememberPendingActions`. Un 422 de grado **no** guarda pending. Un mensaje del LLM “¿confirmas a Georgina?” **sin** tools **tampoco**. |
| ¿Qué había en pending? | Si se guardó: `{intent, data, audit_log_id}` completo. El espejo `chat_pending.items` solo copia `teacher_name` o `names[0]` (el **primero**). |
| ¿Qué condición falla? | La de `session()->has(PENDING)` / array no vacío. No el “Si”. Si pending vacío, “Si” sigue como prompt nuevo (`unknown`, follow-up débil: `looksLikeFollowUp` **no** incluye `^si$`). |

### Caso 4: “Crea a la alumna Georgina e intégrala al curso”

| Pregunta | Respuesta |
|----------|-----------|
| ¿Por qué `create_course`? | `detectIntent` (1776–1788): el bloque de alumnos exige `\b(?:alumno\|estudiante)s?\b`. **`alumna` no matchea.** Luego (1799–1803): hay `crea`, **no** hay alumno/estudiante, y `str_contains(..., 'curso')` → **`create_course`**. |
| ¿Qué palabras activaron curso? | `crea` + `curso`. “intégrala” no está en los verbos de matrícula de ese bloque. |
| ¿Cómo evitarlo? | Incluir `alumna(s)` en todos los regex de alumno; priorizar persona+crear sobre la palabra curso (como ya dice el prompt del LLM, regla 6); no tratar “al curso” como alta de oferta académica. |

---

## 8. Recomendaciones

### Las 3 causas principales

1. **Enrutado por regex incompleto** (alumna, código+proporciona/necesito, manage_invite_code con whitelist de verbos corta).
2. **Dos cerebros (Data Agent vs CRUD) y tools de código DOC- no cableadas al LLM.**
3. **Confirmación = sesión, no el diálogo.** Clarificaciones y prosa del modelo no serializan un plan ejecutable.

### Cambios urgentes (estimado)

| Cambio | Esfuerzo |
|--------|----------|
| Regex `alumna(s)` + `extractStudentNames` que parta por “y a la alumna/el alumno” | 0.5–1 día + tests |
| `manage_invite_code` / `get_teacher_invite_code` en tools del intérprete y en Data Agent; ampliar `detectIntent` (“codigo de X”, “proporciona el codigo”) | 1 día |
| Pending parcial: guardar `{names}` cuando falta grado; “3ro” o “sí” completa el slot | 1–1.5 días |
| `get_teachers` + roster: código DOC- del invite claimed; tool dedicada | 0.5 día |

### Tools nuevas (propuesta; hoy no están)

- `get_teacher_invite_code(teacher_name)` → `{invite_code, status, email, expires_at}`
- Opcional: `list_teacher_invites` (pendientes)
- No hace falta `course_exists` si `create_course` ya reusa; sí hace falta **no** crear curso cuando el usuario dijo alumna+curso.

### Confirmación

- Toda pregunta de slot (grado, materia) debe `rememberPendingActions` o un `pending_slots` en sesión.
- “Sí” solo confirma si hay pending; si hay slot abierto, “sí” no debe ir al Data Agent.
- No dejar que el LLM pida confirmación en texto sin tools.

### Contexto

- Usar `CHAT_HISTORY_KEY` o dejar de declararlo; la fuente de verdad ya es `conversation` del cliente + `DirectorConversationContextService`.
- Copiar `last_mentioned_people` al memory JSON cuando el parser extraiga nombres, aunque falte grado.
- Data Agent: pasar 4–6 últimos turnos o `last_user_text` ya está; ampliar a nombres mencionados.

### Largo plazo

- Un solo planner (un array de tools: get_* y mutate_*).
- Tests de contrato por frase (los 4 casos de este informe) en `DirectorAICommandTest` / `DirectorDataAgentIntentRoutingTest`.
- Telemetría `director.ai.routing` ya existe: añadir `pending_present`, `extracted_names`.

### Plan de implementación (orden)

1. Tests que fallen hoy con las 4 frases (medio día).  
2. Género alumna + extractor multi-nombre (1 día).  
3. Ruta + tool de código DOC- (1 día).  
4. Pending con slots (1–2 días).  
5. Alinear `get_section_counts` en `toolDefinitions` (hora).  
6. Unificación de agentes (1–2 semanas).

**Total urgente (1–4 + tests): ~4–6 días hábiles.** Unificación completa: 1–2 sprints.

---

## Apéndice: citas de código (líneas)

- Data Agent tools: `DirectorDataAgentService.php` 20–41, 1555–1613, 879–1001.  
- Intérprete tools: `DirectorAIInterpreterService.php` 124–208, 214–406.  
- Handle: `AICommandController.php` 73–360, 747–862, 1503–1536, 1726–1882, 3997–4051, 4147–4198, 4260–4281.  
- Roster DOC-: `SchoolRosterContextService.php` 74–114.  
- `getTeachers` sin código: `DirectorAnalyticsQueryService.php` 78–116.  
- `manageInviteCode`: `DirectorActionService.php` 1387–1405.  
- `createStudentsBatch` + posible alta de curso: 369–423.  
- `was_existing`: `createCourse` retorno ~668–673.  
- `CHAT_HISTORY_KEY` huérfana: solo línea 38 del controller.  
- Conversation al LLM: interpreter 32–40; bubble 1339–1355.
