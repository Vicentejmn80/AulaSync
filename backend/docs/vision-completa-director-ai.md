# Visión completa: AulaSync Director AI Assistant

## 1. Qué estamos construyendo (north star)

Un asistente de IA que reemplace la necesidad de que el director use formularios manuales para el día a día del colegio. El director le habla (texto o nota de voz) y el asistente:

- **Crea, modifica y elimina**: alumnos, profesores, cursos, matrículas, asignaciones de materia/grado/sección.
- **Consulta**: rendimiento de alumnos, cursos, profesores; asistencia; notas.
- **Analiza y recomienda**: tendencias, alumnos en riesgo, causas probables, panorama general del colegio.
- **Genera documentos**: boletas de calificaciones (pendiente de implementar).
- **Todo esto en un solo mensaje si el director lo pide así**, con varias acciones heterogéneas mezcladas: crear + modificar + eliminar + consultar en la misma orden, tal como hablaría con un asistente humano.

El caso de uso que más importa para el MVP es exactamente el que sigue fallando: **una nota de voz larga con 2-4 acciones distintas, con lenguaje natural desordenado** (correcciones a media frase, conectores como "adicional", "y también", nombres compuestos, rangos de grado). Si esto no funciona, el producto no tiene el diferencial que buscamos frente a un CRM tradicional con formularios.

## 2. Evidencia real de bugs vivos (última verificación en producción)

Estas transcripciones son la fuente de verdad. Cualquier fix debe probarse contra estas frases exactas, con el flujo real, no con un test que reformule la frase.

### Caso A — colapsa 3 acciones a 1

**Entrada:**
> "Quiero que me crees al profesor Jorge Luis. Él va a ser el profesor de biología desde 1ro hasta 6to grado. Quiero que me crees a los alumnos Vicente José y Jason David para el curso de 4to grado y los pongas en el grupo de biología del profesor Jorge Luis de 4to grado."

**Salida real:**
```
Voy a hacer:
1. Crear profesor Jorge Luis
```
Solo detectó 1 de 3 acciones (faltó: crear a los 2 alumnos, matricularlos).

### Caso B — error crudo sin manejar

**Entrada:**
> "Asignele al profesor Jorge Luis el curso de lenguaje de 1ro a 6to grado."

**Salida real:**
```
Ocurrió un error al preparar la operación.
```
Sin detalle, sin fallback conversacional, sin log accionable para el usuario.

### Caso C — nombre sigue arrastrando palabras (nueva variante del bug de "nombre + verbo/pronombre")

**Entrada:**
> "creale al profesor Jorge Luis el curso de lenguaje de 1ro a 6to grado. el va a dar lenguaje"

**Salida real:**
```
No encontré a "Jorge Luis El" en este colegio.
```
El extractor sigue capturando la primera palabra siguiente (en este caso el pronombre "el") como parte del nombre — el mismo patrón que ya se "arregló" dos veces antes con otras palabras ("Como").

### Casos previos ya reportados como "arreglados" que muestran el mismo patrón recurrente

- "Crea al profesor Junior Vázquez como profesor de biología. Adicional, crea a los alumnos Jason David y Vicente José..." → colapsó a "4 profesores" (arreglado supuestamente en commit `6b1db81`).
- Variante con "Giovanni Vázquez" → colapsó a 1 acción, y el mensaje final inventó acciones no ejecutadas (arreglado supuestamente en commit `3e22a28`, con test de integridad de mensaje).

**Conclusión honesta:** cada "fix" resolvió la frase exacta que se usó para probarlo, pero el patrón subyacente (segmentación de multi-acción poco confiable, límites de nombre poco confiables) sigue sin resolverse de raíz. Ver sección 4.

## 3. Arquitectura actual (resumen)

- `DirectorActionPlannerService`: debería ser el camino principal — LLM con JSON Schema estricto, reintentos con backoff, self-repair.
- `DirectorIntentExtractorService` + lógica en `AICommandController`: extractor legado por reglas, pensado como último recurso.
- `DirectorConfirmationManager`: sostiene el plan con slots pendientes.
- `DirectorUnifiedAgentService`: catálogo único de herramientas de lectura/escritura.
- `planner_source` se loguea/expone para saber si una respuesta vino del LLM o del fallback — **pero nadie ha confirmado con datos reales qué % de tráfico real cae en cada camino**. Esto es lo primero que hay que saber antes de seguir arreglando síntomas.

## 4. Prioridades (en este orden, no saltar pasos)

### Prioridad 1 — Instrumentación antes que más fixes

Antes de escribir una sola línea de fix nuevo: agrega logging que registre, para cada mensaje real de usuario, `planner_source` y, si cayó a fallback, la razón exacta (excepción, timeout, JSON inválido tras repair, deshabilitado). Corre los 3 casos reales de arriba y reporta con evidencia de log cuál fue la causa en cada uno. No asumas.

### Prioridad 2 — Resolver la causa raíz según lo que muestre la instrumentación

- Si el planner está cayendo a fallback en estos casos reales (lo más probable dado el patrón): el problema es de infraestructura/config del planner en el entorno real (no en el código de reintentos, que ya existe), o el prompt del planner no está generalizando a frases con esta estructura. Investiga cuál.
- Si el planner SÍ corrió pero devolvió un plan incompleto: el problema es de prompt engineering del planner o del schema, no del extractor legado. No sigas parchando el legado si el planner ya es el camino real.

### Prioridad 3 — Manejo de errores

Ningún mensaje crudo tipo "Ocurrió un error al preparar la operación" debe llegar al usuario. Todo error debe: loguearse con detalle técnico para debugging, y mostrarle al director un mensaje conversacional útil ("no pude preparar esa acción, ¿puedes decírmelo de otra forma o dividirlo en pasos?").

### Prioridad 4 — Boletas y funcionalidades pendientes del roadmap original

Una vez que multi-acción sea confiable de verdad (medido con el corpus real, no con una frase), retomar: generación de boletas descargables, notificaciones, dashboard.

## 5. Definición de "terminado" para cualquier fix futuro

Un fix no está terminado hasta que:
1. Se reprodujo el bug original con la frase real exacta, con evidencia (transcripción o log).
2. Se aplicó el fix.
3. Se volvió a correr la MISMA frase real por el flujo completo real (no un test aislado) y se muestra la transcripción de salida.
4. Se corrió además contra las otras frases reales de este documento, para confirmar que no se rompió nada más.
5. Se reporta honestamente si algo no se pudo verificar end-to-end en el entorno de desarrollo.
