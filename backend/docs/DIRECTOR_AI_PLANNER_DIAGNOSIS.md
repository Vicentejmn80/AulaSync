# Diagnóstico: Director AI Planner — llm_structured vs fallback

## Resumen

- **Fecha**: 2026-08-27
- **Causa raíz del 80% de los bugs**: el `DirectorIntentExtractorService` (regex legado) se usaba como camino principal en producción porque el planificador LLM (`DirectorActionPlannerService` con Structured Outputs) fallaba por causas de infraestructura y no tenía reintentos ni self-repair. Cada frase nueva con estructura distinta rompía el regex y requería un parche puntual.
- **Estrategia adoptada**: mover todo el tráfico posible al planificador LLM y relegar el extractor legado a *último recurso honesto* (pide aclaración si no está seguro).

## Estado del planificador LLM

| Componente | Antes | Después |
|---|---|---|
| Reintentos ante errores transitorios (timeout, 429, SSL, 5xx) | 0 (fallaba al primer error y caía a fallback) | **3 intentos con backoff exponencial** 500ms → 1000ms → 2000ms. Solo 429/500/502/503/504 y ConnectionException reintentan. 4xx no reintentan. |
| Respuesta inválida / JSON roto | Caía directo a fallback silencioso | **Self-repair**: segunda llamada al LLM con mensaje "tu respuesta anterior no fue un ActionPlan válido (razón X), corrígela" antes de degradar. |
| `planner_source` en respuesta | Solo `llm_structured` o sin campo | **5 valores**: `llm_structured`, `llm_structured_repaired`, `fallback_disabled`, `fallback_http_failed`, `fallback_invalid_json`, `fallback_exception`, `fallback_uncertain`. Visible en `action_plan.planner_source` y `planner_source` top-level. |
| Logging | Solo warning en http_failed | `Log::info('director.ai.planner_source')` en cada plan exitoso + warnings con attempt/reason. Permite tabular % en Kibana/Datadog. |
| SystemPrompt rangos de grado | "grade=null y rango en missing_slots" (texto libre, nunca se ejecutaba) | **Rangos expandidos**: "de 1ro a 6to" → `grades: ["1ro","2do","3ro","4to","5to","6to"]`. Deduplicación de nombres exigida al LLM. |
| Deduplicación | No | LLM instruido + `normalizePlan()->deduplicatePlanActions()` elimina repetidos globales. |

## Qué % de tráfico cae a fallback (cómo medirlo)

```sql
-- Última semana, agrupado por planner_source
SELECT
  JSON_EXTRACT(input_payload, '$.planner_source') as planner_source,
  COUNT(*) as n,
  ROUND(COUNT(*) * 100.0 / SUM(COUNT(*)) OVER (), 1) as pct
FROM director_ai_operation_logs
WHERE created_at >= NOW() - INTERVAL 7 DAY
GROUP BY planner_source
ORDER BY n DESC;
```

O en Laravel:
```php
DirectorAiOperationLog::where('created_at','>=',now()->subWeek())
  ->get()->groupBy(fn($l)=> $l->input_payload['planner_source'] ?? 'unknown')
  ->map->count();
```

**Objetivo**: fallback < 10-15%. Si supera ese umbral, el problema es infraestructura/config (OpenAI key, SSL, timeout, `director_enabled`/`director_test_enabled`) — no el parser de respaldo.

Causas reales observadas para `fallback_*`:
- `fallback_disabled`: `services.openai.key` vacío o contiene `your_openai`, o `director_enabled=false`, o tests sin `director_test_enabled=true`.
- `fallback_http_failed`: timeout 45s, SSL (curl error 35/60), 429/5xx tras 3 reintentos.
- `fallback_invalid_json`: LLM devolvió contenido sin clave `actions` y self-repair también falló.
- `fallback_exception`: excepción fuera de Http (ej. ConnectionException no capturada antes del fix).
- `fallback_uncertain`: fallback detectó texto complejo pero con menos acciones que verbos → pide aclaración en vez de adivinar.

## Fallback legado: de "adivina con confianza" a "honesto"

Nuevo método `DirectorIntentExtractorService::isUncertainExtraction($text, $actions)`:
- Detecta conectores de complejidad (`adicional`, `además`, `también`) + compara `verbCount` vs `actions.count`.
- Si `entityMentions >=3 && verbCount>=2 && actions<=1` → incierto.
- Si `segmentCount>=3 && actions<=1 && verbCount>=2` → incierto.

En `AICommandController::handle`:
- Si `planner_source` es `fallback_*` y `isUncertainExtraction==true` → **no** se muestra "Voy a hacer..." con confianza; se responde `needs_clarification` pidiendo al director que separe cada acción en líneas numeradas.
- Mismo check en el flujo 100% legado (`actionPlanner->enabled()==false`).

Deduplicación: `deduplicateActions()` global elimina nombres repetidos ("a Vicente José, al alumno Vicente José" → una sola vez) tanto en extractor como en `DirectorActionPlannerService::deduplicatePlanActions()`.

## Rangos de grado: antes y después

- **Extractor**: `extractGrades()` ya expandía rangos vía `expandGradeRange()` (regex `desde|de ... hasta|a`). Se mantiene. Bug de "para desde 1ro a 6to" seguía funcionando porque el regex busca `desde 1ro a 6to` dentro del texto, ignorando el "para" previo.
- **Planificador LLM**: prompt corregido para expandir siempre a array completo, no a `grade=null`.
- **Verificación en DB**: `DirectorActionService::createTeacherInviteWithAssignments`, `createCourses`, `createCourse`, `assignTeacherToGradesSubject` ya hacían `findExistingCourse()` antes de crear. Si el curso ya existe, lo **reasignan** en vez de duplicar y no lo cuentan como creado. El mensaje post-ejecución (`verifyCreateCourses` / `verifyCreateCourse`) distingue `created_count` vs `existing_count` ("2 curso(s) creado(s), 4 ya existente(s) y actualizado(s)") y no dice "he creado" cuando ya existía.

## Prioridad 0 — mensaje final anclado a ejecución real

`DirectorAIInterpreterService::narrate()` ahora **solo** serializa `['executed_results' => $results]` al LLM, sin el texto original del usuario. System prompt incluye: *"Describe únicamente las acciones en executed_results. No agregues, asumas ni infieras ninguna acción que no esté en esa lista."* Fallback `composeReply()` itera solo sobre `$results`. Test `DirectorFinalMessageIntegrityTest` verifica que un plan de 1 acción (crear profesor) no menciona alumnos del texto original en el mensaje final.

## Cómo validar el fix

```bash
php artisan test --filter DirectorFinalMessageIntegrityTest
php artisan test --filter DirectorActionPlannerFlowTest
php artisan test --filter DirectorAICommandTest
php artisan test --filter DirectorCorpusRegressionTest   # 25 frases, ver tabla abajo
```

Si `fallback` supera 15% en logs, revisar `config/services.php` → `openai.key`, `director_model`, y conectividad a `api.openai.com` (SSL, proxy).
