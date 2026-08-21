<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Extrae una representación textual de documentos escolares sin dependencias
 * externas: XLSX y DOCX se leen con ZipArchive + SimpleXML, CSV/TXT de forma
 * nativa, y PDF/imágenes se entregan como data-URI para el modelo con visión.
 */
class DocumentTextExtractor
{
    private const MAX_ROWS = 800;

    private const MAX_CELL_CHARS = 220;

    private const MAX_TEXT_CHARS = 24000;

    /**
     * @return array{text: string, mode: 'text'|'vision'|'pdf', data_uri: ?string, notes: array<int, string>}
     */
    public function extract(string $diskPath, string $mime, string $originalName): array
    {
        if (trim($diskPath) === '') {
            throw new RuntimeException('El archivo ya no está disponible en el almacenamiento.');
        }

        $path = Storage::disk('local')->path($diskPath);

        if (! is_file($path)) {
            throw new RuntimeException('El archivo ya no está disponible en el almacenamiento.');
        }

        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $notes = [];

        if (in_array($extension, ['xlsx'], true) || str_contains($mime, 'spreadsheetml')) {
            $text = $this->xlsxToText($path, $notes);

            return ['text' => $text, 'mode' => 'text', 'data_uri' => null, 'notes' => $notes];
        }

        if (in_array($extension, ['csv', 'txt', 'tsv'], true) || str_contains($mime, 'csv') || str_starts_with($mime, 'text/')) {
            $text = $this->csvToText($path, $notes);

            return ['text' => $text, 'mode' => 'text', 'data_uri' => null, 'notes' => $notes];
        }

        if (in_array($extension, ['docx'], true) || str_contains($mime, 'wordprocessingml')) {
            $text = $this->docxToText($path, $notes);

            return ['text' => $text, 'mode' => 'text', 'data_uri' => null, 'notes' => $notes];
        }

        if ($extension === 'pdf' || $mime === 'application/pdf') {
            return [
                'text' => '',
                'mode' => 'pdf',
                'data_uri' => $this->toDataUri($path, 'application/pdf'),
                'notes' => $notes,
            ];
        }

        if (in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true) || in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            $safeMime = match (true) {
                $extension === 'png' || $mime === 'image/png' => 'image/png',
                $extension === 'webp' || $mime === 'image/webp' => 'image/webp',
                default => 'image/jpeg',
            };

            return [
                'text' => '',
                'mode' => 'vision',
                'data_uri' => $this->toDataUri($path, $safeMime),
                'notes' => $notes,
            ];
        }

        throw new RuntimeException('Formato no soportado. Usa PDF, DOCX, XLSX, CSV o una imagen.');
    }

    /**
     * Convierte un XLSX en texto tabulado, resolviendo cadenas compartidas y
     * convirtiendo números seriales de Excel a fechas cuando la columna parece
     * de fechas según su encabezado.
     */
    public function xlsxToText(string $path, array &$notes = []): string
    {
        $zip = new \ZipArchive;

        if ($zip->open($path) !== true) {
            throw new RuntimeException('El archivo XLSX parece estar dañado.');
        }

        try {
            $shared = $this->xlsxSharedStrings($zip);
            $sheetNames = $this->xlsxSheetNames($zip);
            $lines = [];
            $sheetIndex = 0;

            while (true) {
                $sheetXml = $zip->getFromName('xl/worksheets/sheet'.($sheetIndex + 1).'.xml');

                if ($sheetXml === false) {
                    break;
                }

                $sheetName = $sheetNames[$sheetIndex] ?? ('Hoja '.($sheetIndex + 1));
                $lines[] = "### {$sheetName}";

                $sheetNotes = [];
                $rows = $this->xlsxSheetRows($sheetXml, $shared, $sheetNotes);
                foreach ($sheetNotes as $noteKey => $noteValue) {
                    $notes[$noteKey] = $noteValue;
                }
                foreach ($rows as $row) {
                    $lines[] = implode("\t", $row);
                }

                $sheetIndex++;
                if ($sheetIndex >= 10 || count($lines) >= self::MAX_ROWS) {
                    break;
                }
            }

            $text = implode("\n", $lines);

            return $this->truncate($text, $notes);
        } finally {
            $zip->close();
        }
    }

    /**
     * Convierte un DOCX en texto plano, respetando párrafos.
     */
    public function docxToText(string $path, array &$notes = []): string
    {
        $zip = new \ZipArchive;

        if ($zip->open($path) !== true) {
            throw new RuntimeException('El archivo DOCX parece estar dañado.');
        }

        try {
            $xml = $zip->getFromName('word/document.xml');

            if ($xml === false) {
                throw new RuntimeException('El archivo DOCX no contiene contenido legible.');
            }

            $dom = new \DOMDocument;
            libxml_use_internal_errors(true);
            $dom->loadXML($xml);
            libxml_clear_errors();

            $paragraphs = $dom->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'p');
            $lines = [];

            foreach ($paragraphs as $paragraph) {
                $parts = [];
                foreach ($paragraph->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 't') as $t) {
                    $parts[] = (string) $t->textContent;
                }
                $line = trim(implode('', $parts));
                if ($line !== '') {
                    $lines[] = mb_substr($line, 0, self::MAX_CELL_CHARS);
                }
            }

            $text = implode("\n", $lines);

            return $this->truncate($text, $notes);
        } finally {
            $zip->close();
        }
    }

    /**
     * Detecta el delimitador y normaliza CSV/TSV (incluye BOM UTF-16 de Excel).
     */
    public function csvToText(string $path, array &$notes = []): string
    {
        $raw = file_get_contents($path);

        if ($raw === false) {
            throw new RuntimeException('No se pudo leer el archivo.');
        }

        $encoding = mb_detect_encoding($raw, ['UTF-8', 'UTF-16LE', 'UTF-16BE', 'Windows-1252', 'ISO-8859-1'], true);

        if ($encoding !== false && $encoding !== 'UTF-8') {
            $raw = mb_convert_encoding($raw, 'UTF-8', $encoding);
        }

        $raw = str_replace(["\xEF\xBB\xBF", "\xFE\xFF", "\xFF\xFE"], '', $raw);
        $raw = str_replace(["\r\n", "\r"], "\n", $raw);

        $sample = collect(explode("\n", $raw))->take(5)->implode("\n");
        $delimiter = ',';
        $best = -1;

        foreach ([',', ';', "\t", '|'] as $candidate) {
            $count = substr_count($sample, $candidate);
            if ($count > $best) {
                $best = $count;
                $delimiter = $candidate;
            }
        }

        $lines = [];
        foreach (explode("\n", $raw) as $line) {
            if (trim($line) === '') {
                continue;
            }

            $cells = array_map(
                fn ($cell) => mb_substr(trim((string) $cell, " \t\x00\""), 0, self::MAX_CELL_CHARS),
                str_getcsv($line, $delimiter)
            );
            $cells = array_values(array_filter($cells, fn ($cell) => $cell !== ''));

            if ($cells !== []) {
                $lines[] = implode("\t", $cells);
            }

            if (count($lines) >= self::MAX_ROWS) {
                break;
            }
        }

        return $this->truncate(implode("\n", $lines), $notes);
    }

    /**
     * @return array<int, string>
     */
    private function xlsxSharedStrings(\ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');

        if ($xml === false) {
            return [];
        }

        $dom = new \DOMDocument;
        libxml_use_internal_errors(true);
        $loaded = $dom->loadXML($xml);
        libxml_clear_errors();

        if (! $loaded) {
            return [];
        }

        $strings = [];
        foreach ($dom->getElementsByTagNameNS('http://schemas.openxmlformats.org/spreadsheetml/2006/main', 'si') as $si) {
            $parts = [];
            foreach ($si->getElementsByTagNameNS('http://schemas.openxmlformats.org/spreadsheetml/2006/main', 't') as $t) {
                $parts[] = (string) $t->textContent;
            }
            $strings[] = implode('', $parts);
        }

        return $strings;
    }

    /**
     * @return array<int, string>
     */
    private function xlsxSheetNames(\ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/workbook.xml');

        if ($xml === false) {
            return [];
        }

        $names = [];
        if (preg_match_all('/<sheet[^>]*name="([^"]*)"/i', $xml, $matches)) {
            $names = array_map(fn ($name) => htmlspecialchars_decode($name, ENT_QUOTES), $matches[1]);
        }

        return $names;
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function xlsxSheetRows(string $sheetXml, array $shared, ?array &$notes): array
    {
        $dom = new \DOMDocument;
        libxml_use_internal_errors(true);
        $loaded = $dom->loadXML($sheetXml);
        libxml_clear_errors();

        if (! $loaded) {
            return [];
        }

        $ns = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
        $rows = [];
        $headerByColumn = [];
        $rowIndex = 0;

        foreach ($dom->getElementsByTagNameNS($ns, 'row') as $row) {
            $rowIndex++;
            $cells = [];
            $hasContent = false;

            foreach ($row->getElementsByTagNameNS($ns, 'c') as $cell) {
                $ref = (string) $cell->getAttribute('r');
                $columnIndex = $this->xlsxColumnIndex($ref);
                $type = (string) $cell->getAttribute('t');
                $value = null;

                $valueNode = $cell->getElementsByTagNameNS($ns, 'v');
                $valueText = $valueNode->length > 0 ? (string) $valueNode->item(0)->textContent : '';

                if ($type === 'inlineStr') {
                    $inlineParts = [];
                    foreach ($cell->getElementsByTagNameNS($ns, 't') as $t) {
                        $inlineParts[] = (string) $t->textContent;
                    }
                    $value = implode('', $inlineParts);
                } elseif ($type === 's') {
                    $index = (int) $valueText;
                    $value = $shared[$index] ?? '';
                } elseif ($type === 'b') {
                    $value = ((int) $valueText) === 1 ? 'VERDADERO' : 'FALSO';
                } elseif ($type === 'str' || $type === 'e') {
                    $value = $valueText;
                } else {
                    $value = $this->maybeSerialDate($valueText, $columnIndex, $headerByColumn, $notes);
                }

                $value = trim((string) $value);

                if ($value !== '') {
                    $hasContent = true;
                    $cells[$columnIndex] = mb_substr($value, 0, self::MAX_CELL_CHARS);
                    if ($rowIndex === 1) {
                        $headerByColumn[$columnIndex] = mb_strtolower($value);
                    }
                }
            }

            if (! $hasContent) {
                continue;
            }

            $maxIndex = $cells ? max(array_keys($cells)) : 0;
            $ordered = [];
            for ($i = 0; $i <= $maxIndex; $i++) {
                $ordered[] = $cells[$i] ?? '';
            }

            $rows[] = $ordered;
        }

        return $rows;
    }

    /**
     * Convierte seriales de Excel (1900) a fecha ISO solo cuando la columna
     * tiene encabezado de fecha. Devuelve el valor original en otro caso.
     */
    private function maybeSerialDate(string $valueText, int $columnIndex, array $headerByColumn, ?array &$notes): string
    {
        if ($valueText === '' || ! is_numeric($valueText)) {
            return $valueText;
        }

        $serial = (int) $valueText;

        if ($serial < 30000 || $serial > 60000) {
            return $valueText;
        }

        $header = $headerByColumn[$columnIndex] ?? '';
        $isDateColumn = (bool) preg_match('/fecha|fch|d[ií]a|entrega|inicio|fin|hasta|desde|mes|deadline/iu', $header);

        if (! $isDateColumn) {
            return $valueText;
        }

        $date = \DateTime::createFromFormat('!Y-m-d', '1899-12-30')
            ->modify("+{$serial} days");

        if ($date === false) {
            return $valueText;
        }

        if ($notes !== null) {
            $key = "Col{$columnIndex}";
            $notes["serial_{$key}"] = "Columna de fechas: serial {$serial} → ".$date->format('Y-m-d');
        }

        return $date->format('Y-m-d');
    }

    private function xlsxColumnIndex(string $ref): int
    {
        $letters = preg_replace('/[^A-Z]/', '', strtoupper($ref));

        if ($letters === '') {
            return 0;
        }

        $index = 0;
        foreach (str_split($letters) as $letter) {
            $index = $index * 26 + (ord($letter) - 64);
        }

        return $index - 1;
    }

    private function truncate(string $text, array &$notes): string
    {
        if (mb_strlen($text) <= self::MAX_TEXT_CHARS) {
            return $text;
        }

        $notes['truncated'] = 'El contenido fue truncado por tamaño; solo se analizaron los primeros '.self::MAX_TEXT_CHARS.' caracteres.';

        return mb_substr($text, 0, self::MAX_TEXT_CHARS);
    }

    private function toDataUri(string $path, string $mime): string
    {
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException('No se pudo leer el archivo.');
        }

        return 'data:'.$mime.';base64,'.base64_encode($contents);
    }
}
