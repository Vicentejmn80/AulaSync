<?php

namespace App\Services\Connectors;

use App\Contracts\IntelligenceSourceConnector;

/**
 * Stub de conector de Google Drive. La arquitectura está lista; la
 * autenticación OAuth y la sincronización de archivos se implementarán
 * cuando se activen las credenciales de Google (GOOGLE_DRIVE_ENABLED).
 */
class GoogleDriveConnector implements IntelligenceSourceConnector
{
    public function label(): string
    {
        return 'Google Drive';
    }

    public function description(): string
    {
        return 'Conecta tu Drive para importar planificaciones, listas y notas guardadas en la nube.';
    }

    public function key(): string
    {
        return 'google_drive';
    }

    public function isConfigured(): bool
    {
        return (bool) config('services.google_drive.enabled', false);
    }
}
