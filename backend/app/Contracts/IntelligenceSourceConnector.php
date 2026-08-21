<?php

namespace App\Contracts;

/**
 * Contrato para fuentes externas de documentos de "Inteligencia AulaSync".
 * Cada conector (Google Classroom, Google Drive, etc.) implementa esta
 * interfaz para que sus documentos puedan incorporarse al mismo flujo de
 * importación, extracción y aplicación.
 */
interface IntelligenceSourceConnector
{
    /** Nombre visible del conector (ej. "Google Classroom"). */
    public function label(): string;

    /** Descripción breve de qué aporta el conector. */
    public function description(): string;

    /** Clave única del conector (ej. "google_classroom"). */
    public function key(): string;

    /** Indica si el conector está configurado y listo para usarse. */
    public function isConfigured(): bool;
}
