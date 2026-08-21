<?php

namespace App\Services\Connectors;

use App\Contracts\IntelligenceSourceConnector;

/**
 * Stub de conector de Google Classroom. La arquitectura está lista; la
 * autenticación OAuth y la sincronización se implementarán cuando se activen
 * las credenciales de Google (GOOGLE_CLASSROOM_ENABLED).
 */
class GoogleClassroomConnector implements IntelligenceSourceConnector
{
    public function label(): string
    {
        return 'Google Classroom';
    }

    public function description(): string
    {
        return 'Importa automáticamente tareas, anuncios y calificaciones de tus clases de Google Classroom.';
    }

    public function key(): string
    {
        return 'google_classroom';
    }

    public function isConfigured(): bool
    {
        return (bool) config('services.google_classroom.enabled', false);
    }
}
