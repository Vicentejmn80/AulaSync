# Sistema de Borrado con IA - Nova Academy

## Descripción General

El sistema de borrado ha sido completamente rediseñado para permitir a Nova identificar y eliminar actividades específicas por ID, además de borrar rangos de fechas.

## Arquitectura del Sistema

### 1. Herramientas Disponibles

#### `deleteResource` (Borrado por ID - PREFERIDO)
- **Uso**: Eliminar UNA actividad específica identificada por su ID
- **Cuándo usarla**: Usuario pide borrar una actividad concreta (ej: "borra la clase del jueves", "elimina la actividad de matemáticas")
- **Parámetros**:
  - `resource_type`: `'activity'` | `'course'` | `'student'`
  - `resource_id`: ID único del recurso (ej: 42)

#### `deleteActivities` (Borrado por rango)
- **Uso**: Eliminar MÚLTIPLES actividades en un rango de fechas
- **Cuándo usarla**: Usuario pide borrar varias actividades (ej: "borra todas las clases de marzo", "elimina la semana completa")
- **Parámetros**:
  - `course_id`: ID del curso
  - `start_date`: Fecha inicial (YYYY-MM-DD)
  - `end_date`: Fecha final (YYYY-MM-DD)

### 2. Flujo de Borrado por ID

```
Usuario: "Borra la clase del jueves"
    ↓
IA lee calendario inyectado:
    - 2026-04-17 | actividad_id 42 | Matemáticas 3ro | Fracciones | clase
    ↓
IA identifica: activity_id = 42
    ↓
IA llama: deleteResource(resource_type='activity', resource_id=42)
    ↓
Backend verifica ownership (teacher_id) y borra
    ↓
Frontend recibe evento 'ai-canvas-refresh'
    ↓
Calendario se actualiza automáticamente
```

### 3. System Prompt - Reglas de Borrado

El system prompt ahora incluye instrucciones claras:

```
REGLAS CRÍTICAS DE BORRADO:

1. IDENTIFICACIÓN DE ID: Cada línea del calendario inyectado incluye 'actividad_id XXXX'. 
   Cuando el usuario pida borrar una actividad específica, localiza su activity_id.

2. BORRADO POR ID (preferido para actividades específicas): 
   Si identificaste un activity_id único, usa deleteResource con resource_type='activity' 
   y resource_id=<el_id>.

3. BORRADO POR RANGO (para múltiples actividades): 
   Si el usuario pide borrar varias actividades, usa deleteActivities con course_id, 
   start_date y end_date.

4. CONFIRMACIÓN IMPLÍCITA: Si el calendario inyectado muestra claramente qué actividades 
   se borrarán, menciónalas al usuario con sus títulos y fechas antes de ejecutar.

5. SOLO SI EL USUARIO LO PIDIÓ: deleteResource o deleteActivities ÚNICAMENTE si el usuario 
   explícitamente pidió borrar/eliminar/limpiar/vaciar.
```

### 4. Contexto del Calendario Inyectado

El sistema inyecta automáticamente en cada mensaje de la IA:

```
Estado actual del calendario (próximas 2 semanas):
- 2026-04-15 | actividad_id 38 | Inglés Primero | Colors: Introducción | clase
- 2026-04-17 | actividad_id 39 | Inglés Primero | Colors: Ejercicios prácticos | actividad
- 2026-04-22 | actividad_id 40 | Inglés Primero | Numbers: Conceptos clave | clase
```

Este formato incluye:
- Fecha (YYYY-MM-DD)
- **activity_id** (identificador único)
- course_id
- Nombre del curso
- Título de la actividad
- Tipo (clase/actividad/tarea)

### 5. Seguridad del Backend

`doDeleteResource()` ahora verifica:
- Que el recurso exista
- Que pertenezca al teacher_id autenticado
- Manejo de errores con try-catch
- Logs detallados de cada operación

```php
$activity = Activity::where('id', $resourceId)
    ->where('teacher_id', $teacherId)
    ->first();

if (!$activity) {
    return ['success' => false, 'message' => '⚠️ No encontré esa actividad...'];
}
```

### 6. Actualización de UI

El frontend actualiza automáticamente sin recargar la página:

1. **Backend** retorna `action_type: 'delete'` con `success: true`
2. **Burbuja IA** detecta el borrado exitoso y dispara `ai-canvas-refresh`
3. **Hub** escucha el evento y recarga `loadCalendar()` si está en vista calendario
4. **Toast** muestra "✅ Actividades eliminadas correctamente"

### 7. Ejemplos de Uso

#### Ejemplo 1: Borrado de actividad específica

```
Usuario: "Borra la clase de colors del lunes 15"

IA busca en calendario:
- 2026-04-15 | actividad_id 38 | Inglés Primero | Colors: Introducción | clase

IA responde: "Voy a eliminar la clase «Colors: Introducción» del 15/04/2026. 
              ¿Procedo?"

Usuario: "Sí"

IA ejecuta: deleteResource(resource_type='activity', resource_id=38)

Resultado: "✅ ¡Listo! Eliminé la actividad «Colors: Introducción» del 15/04/2026. 
            ¿En qué más te ayudo?"
```

#### Ejemplo 2: Borrado de rango

```
Usuario: "Borra todas las clases de abril para inglés primero"

IA identifica:
- course_id: 5 (Inglés Primero)
- start_date: 2026-04-01
- end_date: 2026-04-30

IA ejecuta: deleteActivities(course_id=5, start_date='2026-04-01', end_date='2026-04-30')

Resultado: "✅ ¡Listo! Eliminé 8 actividades entre 01/04/2026 y 30/04/2026 
            para Inglés Primero. ¿En qué más te ayudo?"
```

## Archivos Modificados

### Backend
- `app/Http/Controllers/AICommandHandlerController.php`
  - System prompt actualizado (líneas 475-495)
  - `doDeleteResource()` mejorado con validaciones (líneas 1410-1565)
  - Tool definition de `deleteResource` más descriptivo (líneas 157-177)

### Frontend
- `resources/views/components/ai-assistant-bubble.blade.php`
  - `handleResponse()` mejorado para disparar `ai-canvas-refresh` en borrados (líneas 961-1014)
  - Toast específico para borrados exitosos

- `resources/views/teacher/hub.blade.php`
  - Listener `ai-canvas-refresh` recarga calendario automáticamente (líneas 2822-2832)

## Testing Manual

Para probar el sistema:

1. Crear algunas actividades con la IA:
   ```
   "Planifica la semana del 14 al 18 de abril con los temas colors y numbers"
   ```

2. Verificar que se muestren en el calendario con sus IDs

3. Borrar una actividad específica:
   ```
   "Borra la clase del jueves 17"
   ```

4. Verificar que:
   - La IA identifica el `activity_id` del calendario
   - Muestra mensaje de confirmación con título y fecha
   - Después de confirmar, la actividad desaparece del calendario
   - Se muestra toast "✅ Actividades eliminadas correctamente"
   - El calendario se actualiza sin recargar la página

5. Borrar un rango:
   ```
   "Borra todas las actividades de la semana del 14 al 18 de abril"
   ```

## Notas Importantes

- El calendario inyectado solo muestra las próximas 2 semanas por defecto
- Para borrar actividades fuera de ese rango, la IA puede llamar `getCalendarContext()` primero
- Los borrados son definitivos (no hay papelera de reciclaje)
- El sistema verifica ownership - un profesor solo puede borrar sus propias actividades
