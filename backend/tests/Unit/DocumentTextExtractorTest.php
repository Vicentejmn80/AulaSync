<?php

namespace Tests\Unit;

use App\Services\DocumentTextExtractor;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class DocumentTextExtractorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public function test_extracts_xlsx_rows_with_shared_strings_and_sheet_name(): void
    {
        $path = $this->writeXlsx('Planificacion', [
            ['Fecha', 'Tema', 'Tipo'],
            ['2026-09-01', 'Fracciones: introducción', 'Clase'],
            ['2026-09-03', 'Comparación de fracciones', 'Clase'],
        ]);

        $extractor = new DocumentTextExtractor;
        $notes = [];
        $text = $extractor->xlsxToText($this->abs($path), $notes);

        $this->assertSame("### Planificacion\nFecha\tTema\tTipo\n2026-09-01\tFracciones: introducción\tClase\n2026-09-03\tComparación de fracciones\tClase", $text);
    }

    public function test_converts_excel_serial_dates_in_date_columns(): void
    {
        // 2026-09-01 = serial 46266 (base 1899-12-30).
        $path = $this->writeXlsx('Hoja1', [
            ['Fecha', 'Tema'],
            ['46266', 'Fracciones'],
        ], numeric: true);

        $extractor = new DocumentTextExtractor;
        $notes = [];
        $text = $extractor->xlsxToText($this->abs($path), $notes);

        $this->assertStringContainsString('2026-09-01', $text);
        $this->assertArrayHasKey('serial_Col0', $notes);
    }

    public function test_keeps_plain_numbers_outside_date_columns(): void
    {
        $path = $this->writeXlsx('Hoja1', [
            ['Alumno', 'Nota'],
            ['Ana Ruiz', '46266'],
        ], numeric: true);

        $extractor = new DocumentTextExtractor;
        $notes = [];
        $text = $extractor->xlsxToText($this->abs($path), $notes);

        $this->assertStringContainsString("Ana Ruiz\t46266", $text);
        $this->assertSame([], $notes);
    }

    public function test_extracts_csv_with_delimiter_detection(): void
    {
        $path = $this->writeFile('notas.csv', "Nombre;Nota 1;Nota 2\nAna Ruiz;18;17\nLuis Pérez;12;9\n");

        $extractor = new DocumentTextExtractor;
        $notes = [];
        $text = $extractor->csvToText($this->abs($path), $notes);

        $this->assertSame("Nombre\tNota 1\tNota 2\nAna Ruiz\t18\t17\nLuis Pérez\t12\t9", $text);
    }

    public function test_extracts_docx_paragraphs(): void
    {
        $path = $this->writeDocx([
            'Planificación de Matemáticas 4to A',
            'Semana 1: Fracciones',
        ]);

        $extractor = new DocumentTextExtractor;
        $notes = [];
        $text = $extractor->docxToText($this->abs($path), $notes);

        $this->assertSame("Planificación de Matemáticas 4to A\nSemana 1: Fracciones", $text);
    }

    public function test_extract_routes_by_extension(): void
    {
        $extractor = new DocumentTextExtractor;

        $csv = $this->writeFile('notas.csv', "Nombre,Nota\nAna,18\n");
        $result = $extractor->extract('testing/'.$csv, 'text/plain', 'notas.csv');
        $this->assertSame('text', $result['mode']);
        $this->assertSame("Nombre\tNota\nAna\t18", $result['text']);

        $png = $this->writeFile('foto.png', "\x89PNG\r\n\x1a\n".str_repeat("\0", 32));
        $result = $extractor->extract('testing/'.$png, 'image/png', 'foto.png');
        $this->assertSame('vision', $result['mode']);
        $this->assertStringStartsWith('data:image/png;base64,', (string) $result['data_uri']);
    }

    public function test_extract_rejects_unsupported_formats(): void
    {
        $extractor = new DocumentTextExtractor;

        $path = $this->writeFile('documento.exe', str_repeat('MZ', 64));

        $this->expectException(RuntimeException::class);
        $extractor->extract('testing/'.$path, 'application/x-msdownload', 'documento.exe');
    }

    public function test_extract_throws_when_file_is_missing(): void
    {
        $extractor = new DocumentTextExtractor;

        $this->expectException(RuntimeException::class);
        $extractor->extract('testing/no-existe.csv', 'text/plain', 'no-existe.csv');
    }

    // ─── Helpers: archivos reales mínimos ────────────────────────────────

    private function abs(string $name): string
    {
        return Storage::disk('local')->path('testing/'.$name);
    }

    private function writeFile(string $name, string $content): string
    {
        Storage::disk('local')->put('testing/'.$name, $content);

        return $name;
    }

    private function writeXlsx(string $sheetName, array $rows, bool $numeric = false): string
    {
        $zipPath = tempnam(sys_get_temp_dir(), 'xlsx').'.xlsx';
        $zip = new \ZipArchive;
        $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        $zip->addFromString('[Content_Types].xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'.
            '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'.
            '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'.
            '<Default Extension="xml" ContentType="application/xml"/>'.
            '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'.
            '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'.
            '</Types>');
        $zip->addFromString('_rels/.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'.
            '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.
            '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'.
            '</Relationships>');
        $zip->addFromString('xl/workbook.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'.
            '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'.
            '<sheets><sheet name="'.htmlspecialchars($sheetName, ENT_XML1).'" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'.
            '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.
            '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'.
            '</Relationships>');

        $rowXml = '';
        foreach ($rows as $r => $row) {
            $cells = '';
            foreach ($row as $c => $value) {
                $ref = chr(65 + $c).($r + 1);
                if ($numeric && $r > 0) {
                    $cells .= '<c r="'.$ref.'"><v>'.$value.'</v></c>';
                } else {
                    $cells .= '<c r="'.$ref.'" t="inlineStr"><is><t>'.htmlspecialchars((string) $value, ENT_XML1).'</t></is></c>';
                }
            }
            $rowXml .= '<row r="'.($r + 1).'">'.$cells.'</row>';
        }
        $zip->addFromString('xl/worksheets/sheet1.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'.
            '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.$rowXml.'</sheetData></worksheet>');
        $zip->close();

        Storage::disk('local')->put('testing/planificacion.xlsx', file_get_contents($zipPath));
        unlink($zipPath);

        return 'planificacion.xlsx';
    }

    private function writeDocx(array $paragraphs): string
    {
        $zipPath = tempnam(sys_get_temp_dir(), 'docx').'.docx';
        $zip = new \ZipArchive;
        $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'.
            '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'.
            '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'.
            '<Default Extension="xml" ContentType="application/xml"/>'.
            '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'.
            '</Types>');
        $zip->addFromString('_rels/.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'.
            '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.
            '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'.
            '</Relationships>');

        $body = '';
        foreach ($paragraphs as $paragraph) {
            $body .= '<w:p><w:r><w:t>'.htmlspecialchars($paragraph, ENT_XML1).'</w:t></w:r></w:p>';
        }
        $zip->addFromString('word/document.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'.
            '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>'.$body.'</w:body></w:document>');
        $zip->close();

        Storage::disk('local')->put('testing/informe.docx', file_get_contents($zipPath));
        unlink($zipPath);

        return 'informe.docx';
    }
}
