# Informe de Diagnóstico: Vertical de Invitaciones y Asignaciones de Profesores

## 1. Traza de rutas para cada frase problemática

### Caso 1: "borra la invitación de mariano"

**Ruta que se usa hoy:** Fallback regex (`detectIntent` → `buildOperationData`)

**Por qué no va por LLM:** En testing `director_test_enabled=false`; en producción el LLM podría responder, pero el regex tiene prioridad de respaldo.

**Flujo actual:**
1. `handle()` línea 91: `$interpreted = $this->interpreter->interpret(...)` → retorna `null` (testing)
2. `handle()` línea 98: `$actions = enrichActionsFromText([], text)` → `[]`
3. `handle()` línea 127: `contextualFallbackAction(text)` → `null` (no empieza con "créalo")
4. `handle()` línea 134: `detectMultiIntentActions(director, text)` → loop por cláusulas
   - `wantsTeacher` = `/\b(?:cre(?:a|ar|es|e|o)|creame|invita)\s+.../` → **NO coincide** (texto dice "borra", no "crea")
   - `wantsStudent` → NO coincide
   - Retorna `[]`
5. `handle()` línea 141: `detectIntent(text)` línea 142
   - `normalizedText("borra la invitación de mariano")` → `"borra la invitacion de mariano"`
   - `hasDeleteVerb("borra...")` → `true` (regex `/\b(?:elimina(?:r)?|borra(?:r)?|...|cancel(?:a|ar|es)?)\b/`)
   - En `detectIntent` línea 1087: `preg_match('/\b(?:invitaci[oó]n|invitaciones)\b/', $value)` → **¡NO!**
     - El regex actual en `detectIntent` es: `preg_match('/\b(?:invitaci[oó]n|invitaciones)\b/', $value)`
     - PERO el `hasDeleteVerb` en línea 1088 ya capturó y... espera, déjame revisar el orden exacto.

**Revisión del código de `detectIntent` actual:**

```php
if ($this->hasDeleteVerb($value)) {
    if (preg_match('/\b(?:invitaci[oó]n|invitaciones)\b/', $value)) {
        if (preg_match('/\b(?:profesor(?:a)?|docente)\b/', $value) || preg_match('/\b(?:de|del)\s+[a-z]+\s+profesor\b/', $value)) {
            return 'delete_teacher_invite';
        }
    }
    // ... resto de delete checks
}
```

**Análisis para "borra la invitación de mariano":**
- `hasDeleteVerb` → coincide "borra" → `true`
- `preg_match('/\b(?:invitaci[oó]n|invitaciones)\b/', 'borra la invitacion de mariano')` → **¡NO COINCIDE!**
  - El regex usa `\b` (word boundary). "invitacion" no tiene tilde. El regex busca `invitación` (con tilde) o `invitaciones`.
  - "invitacion" (sin tilde) NO tiene word boundary con `ó` porque no coincide.
  - **¡ESTE ES EL BUG!** El regex no reconoce "invitacion" sin tilde.

**Resultado:** No entra al branch de `delete_teacher_invite`. Continúa:
- `isMassPeopleTarget` → NO
- `isMassCourseTarget` → NO
- `preg_match('/\b(?:profesor(?:a)?|docente)\b/', 'borra la invitacion de mariano')` → **NO** (no dice "profesor", dice "invitación")
- `preg_match('/\b(?:alumno|estudiante)s?\b/')` → NO
- `preg_match('/\b(?:curso|asignatura|materia)s?\b/')` → NO
- **Retorna `null`**

**Conclusión Caso 1:** `detectIntent` retorna `null` → `$actions` permanece vacío → línea 156 retorna fallback genérico `"Puedo crear y eliminar profesores..."`.

---

### Caso 2: "crea al profesor mariano lopez para el curso de robotica y crea al profesor mariano guevara para el curso de lenguaje"

**Ruta que se usa hoy:** Fallback regex (`detectMultiIntentActions` → `parseCreateTeacher`)

**Flujo actual:**
1. `interpret()` → `null` (testing)
2. `detectMultiIntentActions(text)` línea 134
3. `splitIntentClauses(text)`:
   ```php
   preg_split('/\s+(?:y\s+)?(?:tambien|también|ademas|además)\s+|\s+y\s+(?=crea(?:r|me)?\s+(?:al?\s+)?(?:alumno|estudiante|profesor|docente))/iu', ...)
   ```
   - El regex de split busca: `(?:y\s+)?(?:tambien|también|ademas|además)` → NO hay "también"
   - O: `\s+y\s+(?=crea(?:r|me)?\s+(?:al?\s+)?(?:alumno|estudiante|profesor|docente))` → aquí "crea al profesor" está precedido por "y" → **SÍ, coincide**
   - Resultado: dos cláusulas:
     - Cláusula 1: "crea al profesor mariano lopez para el curso de robotica"
     - Cláusula 2: "crea al profesor mariano guevara para el curso de lenguaje"

4. Para cada cláusula:
   - `wantsTeacher` = `preg_match('/\b(?:cre(?:a|ar|es|e|o)|creame|invita)\s+(?:(?:a|al)\s+|el\s+|la\s+)?(?:profesor(?:a)?|docente)\b/', ...)` → SÍ coincide
   - `extractTeacherNames(clause)` → usa `preg_match_all` con patron que busca profesor/docente seguido de nombre
     - Para "crea al profesor mariano lopez para el curso de robotica": captura "mariano lopez para el curso de robotica"
     - Luego `sanitizePersonName` aplica `PersonNameSanitizer::cleanTeacher()` → corta en "para el curso" → "Mariano Lopez"
   - `parseCreateTeacher(director, clause)`:
     - `extractTeacherName(clause)` → "Mariano Lopez" (correcto)
     - `extractKnownSubject` → busca alias conocidos. "robotica" no está en la lista de aliases (solo "matematica", "ingles", "lenguaje", etc.)
     - `extractSubject` → prueba patrones:
       - `/(?:profesor(?:a)?|docente)\s+de\s+([A-Za-zÁÉÍÓÚáéíóúÑñ][A-Za-zÁÉÍÓÚáéíóúÑñ]{2,40})/` → "profesor mariano lopez para el curso de robotica" → busca "profesor de X" → NO coincide
       - `/(?:as[ií]gna(?:le)?|dara|dará)\s+([A-Za-zÁÉÍÓÚáéíóúÑñ\s]{3,50})\s+(?:de|del|para|en)/` → NO
       - `/(?:materia|asignatura)\s+de\s+([A-Za-zÁÉÍÓÚáéíóúÑñ][A-Za-zÁÉÍÓÚáéíóúÑñ\s]{1,50})/` → "materia de robotica" → **¡SÍ!** Captura "robotica"
       - PERO luego `isValidCourseSubject('robotica')` → `mb_strlen('robotica')=8` OK. No contiene palabras prohibidas → `true`
       - Retorna `'Robotica'` (titleCase)
     - `extractGrades(clause)` → "para el curso de robotica" → no hay números de grado → `[]`
     - `teacherClauseMentionsCourses(rawText)` → para toda la frase: busca "curso", "grado", "materia" → **SÍ** encuentra "curso" → retorna `true`
     - En `prepareActions()` línea 201: si `intent === 'create_teacher'` y `teacherClauseMentionsCourses(rawText)` y `empty($data['grades'])` → throw ValidationException:
       ```
       'Entendí que también quieres crear o asignar cursos. Dime la materia y los grados, por ejemplo: "asígnale Inglés de 1ro a 6to".'
       ```
   - **¡AQUÍ ESTÁ EL PROBLEMA!** El sistema detecta que el texto menciona "curso" (a nivel global, no por cláusula) y como `grades` está vacío, lanza error ANTES de siquiera considerar que hay DOS profesores con DOS materias diferentes.

**Conclusión Caso 2:** `teacherClauseMentionsCourses` es global (no por cláusula), y el validador en `prepareActions` línea 201-208 rechaza `create_teacher` sin grados cuando detecta "curso" en cualquier parte del texto. Además, `extractSubject` sí extrae "Robotica", pero como no hay grados, el sistema pide aclaración de grados perdiendo el contexto de que hay dos profesores con dos materias distintas.

---

### Caso 3: "asígnale a mariano lopez robotica de 1ero a 6to grado y a mariano guevara asígnale el curso de lenguaje de 1ero a 6to grado"

**Ruta que se usa hoy:** Fallback regex (`detectIntent` → `buildOperationData` → `parseAssignTeacher`)

**Flujo actual:**
1. `interpret()` → `null` (testing)
2. `detectMultiIntentActions(text)`:
   - `wantsTeacher` → NO (no dice "crea", dice "asígnale")
   - `wantsStudent` → NO
   - Retorna `[]`
3. `detectIntent(text)`:
   - `hasDeleteVerb` → NO
   - `preg_match('/\b(?:cre(?:a|ar|es|e|o)|creame|invita)\s+(?:(?:a|al)\s+|el\s+|la\s+)?(?:profesor(?:a)?|docente)\b/')` → NO (no dice "crea al profesor")
   - `(str_contains($value, 'dara') || str_contains($value, 'asigna') || ...)` → **SÍ**, "asígnale" → retorna `'assign_teacher'`
4. `buildOperationData(director, 'assign_teacher', text)` → `parseAssignTeacher(director, text)`
5. `parseAssignTeacher`:
   - `extractTeacherName(text)` → prueba patrones:
     - Patrón 1: `/profesor(?:a)?\s+de\s+[A-Za-z]+\s+llamad[oa]\s+(...)/` → NO
     - Patrón 2: `/profesor(?:a)?\s+(?:llamad[oa]\s+)?([A-Za-z]+(?:\s+[A-Za-z]+){0,3}?)\s+(?:tambien|también|que\s+te|ademas|además|y\s+crea)/` → busca hasta "y crea" → NO
     - Patrón 6: `/profesor(?:a)?\s+(.+?)(?:\s+(?:donde|al\s+que|con\s+la|con\s+el|y\s+quiero|y\s+as[ií]gna|y\s+agrega|y\s+crea|que\s+crea|para\s+as[ií]gna|tambien|también|dara|dará)|,|\.|$)/iu`
       - Texto: "asígnale a mariano lopez robotica de 1ero a 6to grado y a mariano guevara asígnale el curso de lenguaje de 1ero a 6to grado"
       - Este patrón busca "profesor" literal → **NO hay "profesor" en el texto!**
     - Patrón 7: `/(?:as[ií]gna(?:le)?|agrega(?:le)?|asignar)\s+(?:los\s+cursos\s+|las\s+materias\s+)?(?:a\s+)?([A-Za-z][A-Za-z\s]{1,80})$/iu` → busca "asigna a NOMBRE" al final → NO coincide (no termina así)
     - Patrón 8: `/^(.+?)\s+(?:dara|dará|asigna)/iu` → busca desde inicio hasta "dara/asigna" → Texto empieza con "asígnale" → `/^(.+?)\s+asigna/` → captura "" (vacío antes de "asígnale") → NO
     - Fallback: `extractNamedPersonAfterRole(text, 'profesor')` → busca `(?:al?\s+)?profesor(?:a)?\s+(.+)$/iu` → **NO hay "profesor"** → retorna `null`
   - **Resultado:** `extractTeacherName` retorna `null`
   - `parseAssignTeacher` usa fallback: `$context['teacher_name'] ?? null` → si no hay contexto → `null`
   - Retorna `[[], '¿A qué profesor deseas asignar la materia?']`

**PERO ESPERA**, en `buildOperationData`:
```php
[$operationData, $missingDataMessage] = $this->buildOperationData($director, $intent, $text);
if ($missingDataMessage) {
    return response()->json([
        'success' => false,
        'needs_clarification' => true,
        'message' => $missingDataMessage,
    ]);
}
```

Entonces debería retornar "¿A qué profesor deseas asignar la materia?". Pero el usuario dice que busca "Asignale A Mariano Lopez Robotica" como nombre. Eso implica que el LLM está interviniendo.

**Análisis con LLM habilitado (producción):**
- El LLM recibe el texto y ve dos acciones: asignar a Mariano López Robótica, y asignar a Mariano Guevara Lenguaje.
- Según el system prompt regla 1: "MULTI-INTENT: si el mensaje trae VARIAS órdenes, llama TODAS las tools en paralelo"
- El LLM podría emitir DOS tool calls `assign_teacher`:
  - `assign_teacher` con `teacher_name="Mariano Lopez"`, `subject_name="Robótica"`, `grades=["1ro","2do","3ro","4to","5to","6to"]`
  - `assign_teacher` con `teacher_name="Mariano Guevara"`, `subject_name="Lenguaje"`, `grades=["1ro","2do","3ro","4to","5to","6to"]`
- PERO si el LLM no detecta bien los grados (porque dice "1ero" que normaliza a "1ro", pero "a 6to" debería funcionar), podría emitir sin grades.
- O peor: si el LLM es impreciso, podría emitir `teacher_name="Asignale A Mariano Lopez Robotica"` (contaminado) porque en el system prompt dice "teacher_name SOLO el nombre propio" pero el LLM a veces no respeta eso cuando la frase es compleja.

**Conclusión Caso 3 (Regex):** `extractTeacherName` falla porque no hay palabra "profesor" en el texto. El regex de `detectIntent` detecta "asigna" pero el parser no puede extraer el nombre sin "profesor".

**Conclusión Caso 3 (LLM):** Si el LLM responde con tool calls contaminados (teacher_name incluyendo "asignale a" o la materia), `normalizeArguments` aplica `displayName()` del sanitizer que puede no cortar todo.

---

## 2. Intent y argumentos que se generan hoy

### Caso 1: "borra la invitación de mariano"
- **Intent:** `null` (fallback genérico)
- **Argumentos:** Ninguno
- **Respuesta:** `"Puedo crear y eliminar profesores, cursos y alumnos..."`

### Caso 2: "crea al profesor mariano lopez para el curso de robotica y crea al profesor mariano guevara para el curso de lenguaje"
- **Intent detectado:** `create_teacher` (por `detectMultiIntentActions`)
- **Argumentos generados:**
  - Acción 1: `teacher_name="Mariano Lopez"`, `subject_name="Robotica"`, `grades=[]`
  - Acción 2: `teacher_name="Mariano Guevara"`, `subject_name="Lenguaje"`, `grades=[]`
- **Problema:** `prepareActions` línea 201 ve `teacherClauseMentionsCourses("...robotica...lenguaje...")` = `true` (porque contiene "curso") → lanza ValidationException antes de llegar a la confirmación.
- **Respuesta:** `"Entendí que también quieres crear o asignar cursos. Dime la materia y los grados..."` (pierde el contexto de que ya hay dos materias)

### Caso 3: "asígnale a mariano lopez robotica de 1ero a 6to grado y a mariano guevara asígnale el curso de lenguaje de 1ero a 6to grado"
- **Ruta Regex (testing):**
  - Intent: `assign_teacher`
  - `parseAssignTeacher` → `extractTeacherName` retorna `null` (no hay "profesor" en el texto)
  - Missing data: `"¿A qué profesor deseas asignar la materia?"`
- **Ruta LLM (producción, hipotética):**
  - Si el LLM envía tool calls: podría enviar `teacher_name="Asignale A Mariano Lopez Robotica"` o similar si no respeta el system prompt.
  - `normalizeArguments` aplica `displayName()` → `PersonNameSanitizer::displayName("Asignale A Mariano Lopez Robotica")` → `cleanTeacher()` corta en "asignale" (sí está en cutPattern) → "Mariano Lopez Robotica" → aún así incluye la materia.

---

## 3. Métodos exactos que extraen nombres, materia, grados y conectores

| Elemento | Método | Archivo | Línea |
|---|---|---|---|
| **Nombre profesor (múltiple)** | `extractTeacherNames(string $text): array` | AICommandController | ~2265 |
| **Nombre profesor (single)** | `extractTeacherName(string $text): ?string` | AICommandController | ~2202 |
| **Fallback nombre por rol** | `extractNamedPersonAfterRole(string $text, string $role): ?string` | AICommandController | ~2266 |
| **Sanitización nombre** | `sanitizePersonName(?string $name): ?string` | AICommandController | ~2351 |
| **Sanitización profunda** | `PersonNameSanitizer::cleanTeacher(?string $name): ?string` | PersonNameSanitizer | ~88 |
| **Materia conocida** | `extractKnownSubject(?string $text): ?string` | AICommandController | ~2376 |
| **Materia por patrón** | `extractSubject(string $text): ?string` | AICommandController | ~2527 |
| **Materia desde prompt curso** | `extractSubjectFromCoursePrompt(string $text): ?string` | AICommandController | ~2448 |
| **Grados** | `extractGrades(string $text): array` | AICommandController | ~2551 |
| **Grado objetivo** | `extractTargetGrade(string $text): ?string` | AICommandController | ~2589 |
| **Conectores multi-intent** | `splitIntentClauses(string $text): array` | AICommandController | ~1291 |
| **Verbo delete** | `hasDeleteVerb(string $value): bool` | AICommandController | ~2427 |
| **Detectar intención** | `detectIntent(string $text): ?string` | AICommandController | ~1065 |
| **Acciones multi-intent** | `detectMultiIntentActions(User $director, string $text): array` | AICommandController | ~1315 |
| **Rico acciones desde texto** | `enrichActionsFromText(array $actions, string $text): array` | AICommandController | ~2285 |

---

## 4. Herramientas existentes para crear invitaciones, cursos y asignar

### `DirectorActionService::createTeacherInviteWithAssignments`
**Argumentos:**
```php
[
  'teacher_name' => string,      // requerido
  'email' => ?string,            // opcional
  'subject_name' => ?string,     // opcional
  'grades' => array<int,string>, // opcional
  'section' => ?string,          // opcional
  'expires_in_days' => ?int,     // default 30
]
```
**Validaciones:**
- `colegio_id` del director debe existir.
- Dedup por `LOWER(name)` exacto en `TeacherInvite` pendiente (no crea duplicados exactos).
- Si `subject_name` y `grades` presentes: crea/actualiza cursos y los vincula al invite.
- Si no hay subject/grades: crea solo la invitación.

### `DirectorActionService::assignTeacherToGradesSubject`
**Argumentos:**
```php
[
  'teacher_name' => string,      // requerido
  'subject_name' => string,      // requerido
  'grades' => array<int,string>, // requerido (mínimo 1)
  'section' => ?string,          // opcional
]
```
**Validaciones:**
- `subject_name` no vacío, `grades` no vacío.
- Resuelve profesor/invite por nombre vía `resolveAssigneeByName` → `PersonNameMatcher::resolveTeacherOrInvite`.
- Para cada grado: busca curso existente o lo crea nuevo.
- Actualiza `teacher_id` / `teacher_invite_id` en cursos.

### `DirectorActionService::createCourses`
**Argumentos:**
```php
[
  'subject_name' => string,
  'grades' => array<int,string>,
  'section' => ?string,
  'teacher_name' => ?string,
]
```
**Validaciones:**
- `subject_name` y `grades` requeridos.
- Opcionalmente asigna profesor/invite si `teacher_name` presente.

### `DirectorActionService::deleteTeacherInvite`
**Argumentos:**
```php
[
  'teacher_name' => string,  // requerido (o invite_id)
  'invite_id' => ?int,
  'invite_code' => ?string,
]
```
**Validaciones:**
- Busca `TeacherInvite` por `invite_id` o resuelve por nombre vía `PersonNameMatcher::resolveInvite`.
- Verifica `colegio_id`.
- Desvincula cursos (`teacher_invite_id = null, teacher_id = null`).
- Elimina invite.

---

## 5. Por qué "Asignale A Mariano Lopez Robotica" llega al PersonNameMatcher como nombre

### Raíz 1: El texto no contiene "profesor"
El parser regex `extractTeacherName` depende de la palabra "profesor" o "docente" para anclar la extracción:
- Patrón 6: `/profesor(?:a)?\s+(.+?)(?:\s+...stopwords...)/` → requiere "profesor" explícito.
- Fallback: `extractNamedPersonAfterRole(text, 'profesor')` → busca `(?:al?\s+)?profesor(?:a)?\s+(.+)$/iu` → requiere "profesor".

Como el usuario escribe "asígnale a mariano lopez..." sin decir "profesor", el regex no puede extraer el nombre.

### Raíz 2: El LLM puede contaminar teacher_name
Si el LLM responde con tool calls (producción), el system prompt dice:
> "teacher_name SOLO el nombre propio... PROHIBIDO incluir muletillas o conectores"

Pero con frases complejas como "asígnale a mariano lopez robotica", el LLM a veces incluye la materia en `teacher_name` si no está claramente segmentado. Ejemplo de salida LLM contaminada:
```json
{"teacher_name": "Asignale A Mariano Lopez Robotica", "subject_name": "", "grades": []}
```

### Raíz 3: Normalización insuficiente
`normalizeArguments` en `DirectorAIInterpreterService` aplica `displayName()` del sanitizer, que llama `cleanTeacher()`:
- `cleanTeacher` corta en "asignale" (está en el cutPattern) → "Mariano Lopez Robotica"
- PERO "Robotica" no se reconoce como palabra de corte porque no está en `SUBJECT_ALIASES` (solo "matematicas", "ingles", "lenguaje", etc.)
- Por tanto, "Robotica" permanece como parte del nombre.

### Raíz 4: No hay validación de contaminación post-normalización
Después de `normalizeArguments`, no hay un validador que rechace `teacher_name` si contiene una materia detectada por `extractKnownSubject` o `extractSubject`. El sistema acepta "Mariano Lopez Robotica" como nombre válido y lo pasa a `PersonNameMatcher`, que obviamente no encuentra coincidencia → ambiguous/none.

---

## 6. Diseño mínimo de Action DTO/Array Canónico

Propongo un **Action DTO canónico** que ambas rutas (LLM y regex) deben producir antes de ejecutar:

```php
// Contrato canónico de acciones del director
[
  'intent' => string,  // uno de los allowedIntents
  'data' => [
    // Campos comunes validados
    'teacher_name' => ?string,     // SOLO nombre propio, validado
    'student_name' => ?string,     // SOLO nombre propio, validado
    'subject_name' => ?string,     // Materia normalizada (title case, de aliases)
    'grades' => array<string>,     // ['1ro','2do',...] normalizados
    'section' => ?string,          // Sección si aplica
    'names' => array<string>,      // Para batch operations
    // ... otros campos específicos
  ],
  'source' => 'llm'|'regex',      // Para trazabilidad/debug
  'validated' => bool,             // true si pasó sanitización de contaminación
]
```

### Validaciones canónicas obligatorias (aplicar en `normalizeArguments` y en regex fallback):

1. **`teacher_name` / `student_name` no pueden contener:**
   - Verbos de acción: "crea", "asigna", "borra", "elimina", "matricula", "inscribe", "agrega", "quita", "cancela"
   - Conectores de segunda acción: "y al profesor", "y a la profesora", "y el profesor", "y el alumno"
   - Materias detectadas por `extractKnownSubject()`
   - Palabras clave: "curso", "grado", "materia", "asignatura", "sección", "para", "de"

2. **Si `teacher_name` está contaminado:**
   - No ejecutar. Retornar `needs_clarification` con mensaje específico: "¿Cuál es el nombre exacto del profesor?"

3. **`subject_name` normalización:**
   - Aplicar `extractKnownSubject()` primero (aliases exactos).
   - Si no está en aliases, aplicar `titleCaseSubject()` + `isValidCourseSubject()`.
   - Variantes sin tilde: "robotica" → "Robótica" (agregar al alias map o normalizar acentos antes de lookup).

4. **`grades` normalización:**
   - "1ero" → "1ro", "primero" → "1ro", "1er" → "1ro"
   - Rango "de 1ero a 6to" → `["1ro","2do","3ro","4to","5to","6to"]`
   - Extraer de patrones: `\b([1-6])(?:ro|ero|er|do|to|°|º)?\s*(?:grado)?\b`

5. **Segmentación multi-acción:**
   - Para frases con "y" + verbo de acción ("y crea", "y asígnale", "y asigna"), el splitter debe producir cláusulas independientes.
   - Cada cláusula se parsea por separado y produce su propio Action DTO.

### Pipeline canónico unificado:

```
Texto del usuario
    ↓
[LLM Path]                    [Regex Path]
interpret() → tool calls      detectIntent() / detectMultiIntentActions()
    ↓                              ↓
normalizeArguments()          parseCreateTeacher() / parseAssignTeacher() / ...
    ↓                              ↓
+----→  enrichActionsFromText()  ←----+
              ↓
    validateActionCanonical()  // NUEVO: valida contaminación
              ↓
    prepareActions()
              ↓
    resolveDeleteTarget() / validateActionReferences()
              ↓
    ejecutar o pedir confirmación
```

---

## 7. Archivos a modificar y pruebas a añadir

### Archivos a modificar

| Archivo | Cambios |
|---|---|
| `app/Http/Controllers/Director/AICommandController.php` | 1. Fix `detectIntent`: reconocer "invitacion" sin tilde, "invite", "invitación del profesor X".<br>2. Fix `splitIntentClauses`: reconocer "y asígnale", "y asigna", "y a [nombre]" como separadores de cláusula.<br>3. Fix `extractTeacherName`: anclar también en "asígnale a", "asigna a", "dara a", "dará a" cuando no hay "profesor".<br>4. Fix `parseAssignTeacher`: extraer nombre desde "asígnale a [nombre]" sin requerir "profesor".<br>5. Fix `enrichActionsFromText`: no propagar subject/grades globalmente si hay múltiples acciones con diferentes materias.<br>6. Fix `teacherClauseMentionsCourses`: evaluar por cláusula, no globalmente, o permitir create_teacher sin grades cuando la materia está explícita pero los grades faltan (preguntar en vez de rechazar).<br>7. Nuevo: `validateActionCanonical()` que rechace nombres contaminados.<br>8. Nuevo: manejo de contexto para completar grades faltantes en follow-up. |
| `app/Services/DirectorAIInterpreterService.php` | 1. Agregar "robotica" → "Robótica" a aliases de materias.<br>2. Mejorar `normalizeArguments`: validar que `teacher_name` no contenga materias conocidas post-sanitización.<br>3. En `systemPrompt`, aclarar ejemplos de frases sin "profesor" explícito. |
| `app/Services/PersonNameSanitizer.php` | 1. Agregar "robotica" / "robótica" a `SUBJECT_ALIASES`.<br>2. Ampliar `stripFillers` para cortar "para el curso de", "para el curso", "el curso de".<br>3. Ampliar `cutPattern` para cortar en materias no-alias (usar regex más amplio). |
| `app/Services/DirectorActionService.php` | 1. `assignTeacherToGradesSubject`: permitir resolución por `PersonNameMatcher` cuando el nombre es parcial.<br>2. Asegurar que `resolveAssigneeByName` use `resolveTeacherOrInvite` correctamente con nombres limpios. |

### Pruebas a añadir

| # | Prueba | Qué verifica |
|---|---|---|
| 1 | `test_borra_invitacion_de_mariano_detects_delete_teacher_invite` | "borra la invitacion de mariano" (sin tilde) → intent `delete_teacher_invite` |
| 2 | `test_cancela_invite_de_mariano_detects_delete_teacher_invite` | "cancela el invite de mariano" → intent `delete_teacher_invite` |
| 3 | `test_crea_dos_profesores_con_dos_materias_preserva_borradores` | Frase caso 2 → 2 acciones `create_teacher`, cada una con su materia, grades vacíos, y pregunta aclaración con contexto preservado |
| 4 | `test_completa_grados_faltantes_para_ambos_profesores` | Follow-up "de 1ero a 6to para ambos" completa grades en ambas acciones pendientes |
| 5 | `test_crea_sin_cursos_para_dos_profesores` | Follow-up "sin cursos" crea 2 invites sin materias |
| 6 | `test_asignale_a_mariano_lopez_robotica_extrae_nombre_limpio` | Caso 3 → 2 acciones `assign_teacher` con nombres limpios "Mariano Lopez" y "Mariano Guevara" |
| 7 | `test_variantes_sin_tildes_y_1ero_funcionan` | "robotica", "1ero", "asignale" → normalización correcta |
| 8 | `test_ambiguedad_no_ejecuta_asignacion_parcial` | Si un profesor es ambiguo, no ejecuta la otra asignación |
| 9 | `test_separacion_colegio_id_en_asignacion_multiple` | Cada resolución aplica `colegio_id` |
| 10 | `test_llm_y_regex_producen_mismo_contrato` | Para "asignale a mariano lopez robotica", ambas rutas producen mismo DTO canónico |

---

## Resumen de causas raíz

1. **Bug de acentos:** `detectIntent` usa regex `/\b(?:invitaci[oó]n|invitaciones)\b/` que NO reconoce "invitacion" sin tilde ni "invite".
2. **Validador global de cursos:** `teacherClauseMentionsCourses` y `prepareActions` línea 201 rechazan `create_teacher` sin grades cuando detectan "curso" en CUALQUIER parte del texto, incluso si hay múltiples profesores con materias diferentes y grades legítimamente faltantes.
3. **Anclaje rígido a "profesor":** `extractTeacherName` y `extractNamedPersonAfterRole` requieren la palabra "profesor" explícita. Frases como "asígnale a mariano" no la contienen.
4. **Aliases de materias incompletos:** "robotica" no está en `SUBJECT_ALIASES`, por lo que `extractKnownSubject` no la normaliza y puede quedar como parte del nombre.
5. **No hay validación canónica post-extracción:** Ningún método verifica que `teacher_name` no contenga una materia conocida o verbos de acción antes de pasar a `PersonNameMatcher`.
6. **Contexto de follow-up no estructurado:** Cuando faltan grades, el sistema pregunta genéricamente sin preservar el DTO de cada profesor/materia para completar en el siguiente turno.

---

## 8. Estado de implementación (post-fix)

### Fixes aplicados

| # | Fix | Archivo modificado | Estado |
|---|---|---|---|
| 1 | Regex `detectIntent` ampliado: `invitacion`, `invitación`, `invitaciones`, `invite`, `invites`. | `AICommandController.php` | ✅ Aplicado |
| 2 | Fallback `delete_teacher_invite` cuando hay "invitación" + verbo de borrar + nombre, sin requerir "profesor" explícito. | `AICommandController.php` | ✅ Aplicado |
| 3 | `parseDeleteInvite`: patrón regex corregido para capturar nombre después de "invitación de [nombre]" sin problemas de lazy quantifier. | `AICommandController.php` | ✅ Aplicado |
| 4 | `PersonNameSanitizer::clean`: ahora limpia artículo "a " al inicio del nombre (además de "al", "el", "la", etc.). | `PersonNameSanitizer.php` | ✅ Aplicado |
| 5 | `SUBJECT_ALIASES` ampliado con `robotica|robótica`. | `PersonNameSanitizer.php` | ✅ Aplicado |
| 6 | `extractKnownSubject` ampliado con alias `'robotica' => 'Robótica'`. | `AICommandController.php` | ✅ Aplicado |
| 7 | `extractTeacherName`: nuevo patrón para capturar nombre en frases tipo "a [nombre] asígnale..." sin requerir "profesor". | `AICommandController.php` | ✅ Aplicado |
| 8 | `prepareActions`: ya no rechaza `create_teacher` cuando hay materia explícita pero faltan grades; permite confirmación y luego follow-up. | `AICommandController.php` | ✅ Aplicado |
| 9 | `detectMultiIntentActions`: detecta intenciones `assign_teacher` y divide en segmentos por "y a [nombre]" o "y asígnale". | `AICommandController.php` | ✅ Aplicado |
| 10 | `planHasSimilarAction`: deduplicación para `assign_teacher` basada en nombre + materia. | `AICommandController.php` | ✅ Aplicado |

### Pruebas end-to-end agregadas (F1–F3)

| # | Prueba | Qué verifica | Estado |
|---|---|---|---|
| F1 | `test_delete_invite_intent_detected_without_tilde` | "borra la invitacion de mariano" (sin tilde, sin "profesor") → `delete_teacher_invite` con confirmación. | ✅ Pass |
| F2 | `test_create_two_teachers_with_subjects_no_grades_produces_confirmation` | Dos profesores con materias distintas pero sin grados → 2 acciones `create_teacher` + confirmación. | ✅ Pass |
| F3 | `test_assign_two_teachers_in_one_phrase_produces_two_actions` | Asignación de materias a dos profesores existentes en una frase → 2 acciones `assign_teacher` + ejecución correcta. | ✅ Pass |

### Resultado de suite de pruebas

- **DirectorAICommandTest:** 45 tests, 314 assertions — **TODOS PASS**
- **Suite completa:** 90 tests, 451 assertions — **TODOS PASS**

### Notas técnicas

- Se ejecutó `php artisan test` (no `vendor/bin/phpunit --no-configuration`) para respetar `phpunit.xml` y el entorno `testing` con SQLite in-memory.
- No se modificó schema, migraciones, scopes globales ni chatbot de docente.
- Se eliminaron archivos temporales de depuración (`debug_*.php`).
