<?php

namespace App\Services;

use App\Contracts\IntelligenceSourceConnector;
use App\Services\Connectors\GoogleClassroomConnector;
use App\Services\Connectors\GoogleDriveConnector;
use Illuminate\Support\Collection;

/**
 * Registro de fuentes de documentos para "Inteligencia AulaSync". Hoy está
 * activa la subida local; los conectores externos (Google Classroom, Google
 * Drive) se activarán cuando estén configurados sin cambiar el flujo central.
 */
class IntelligenceConnectorRegistry
{
    /**
     * @return Collection<int, array{key: string, label: string, description: string, configured: bool, coming_soon: bool}>
     */
    public function available(): Collection
    {
        return collect([
            $this->describe(new class implements IntelligenceSourceConnector
            {
                public function label(): string
                {
                    return 'Subir archivos';
                }

                public function description(): string
                {
                    return 'PDF, Word, Excel, CSV e imágenes desde tu computadora o celular.';
                }

                public function key(): string
                {
                    return 'local_upload';
                }

                public function isConfigured(): bool
                {
                    return true;
                }
            }),
            $this->describe(app(GoogleClassroomConnector::class), comingSoon: true),
            $this->describe(app(GoogleDriveConnector::class), comingSoon: true),
        ]);
    }

    /**
     * Conectores externos configurados (hoy siempre vacío: aún no hay
     * credenciales de Google, pero el contrato ya existe).
     *
     * @return Collection<int, IntelligenceSourceConnector>
     */
    public function configuredExternal(): Collection
    {
        return collect([
            app(GoogleClassroomConnector::class),
            app(GoogleDriveConnector::class),
        ])->filter(fn (IntelligenceSourceConnector $connector) => $connector->isConfigured());
    }

    private function describe(IntelligenceSourceConnector $connector, bool $comingSoon = false): array
    {
        return [
            'key' => $connector->key(),
            'label' => $connector->label(),
            'description' => $connector->description(),
            'configured' => $connector->isConfigured(),
            'coming_soon' => $comingSoon,
        ];
    }
}
