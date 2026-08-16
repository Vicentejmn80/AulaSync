<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Boleta Oficial · {{ $student->name }}</title>
    <style>
        @page { margin: 18mm 14mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1e293b; line-height: 1.6; }

        .header { border-bottom: 3px solid #7c3aed; padding-bottom: 14px; margin-bottom: 18px; }
        .header-inner { width: 100%; border-collapse: collapse; }
        .header-inner td { border: none; padding: 0; vertical-align: middle; }
        .logo-box { width: 56px; height: 56px; border-radius: 10px; background: #ede9fe;
                    text-align: center; line-height: 56px; font-size: 9px; color: #7c3aed; font-weight: 900; }
        .school-name { font-size: 20px; font-weight: 900; color: #0f172a; margin: 0 0 2px; }
        .doc-type { font-size: 10px; color: #7c3aed; font-weight: 700; text-transform: uppercase; letter-spacing: .1em; }
        .period-badge { display: inline-block; padding: 3px 10px; border-radius: 12px; background: #ede9fe; color: #7c3aed; font-weight: 700; font-size: 10px; }

        .meta-grid { width: 100%; margin-bottom: 18px; border-collapse: collapse; }
        .meta-grid td { padding: 0; border: none; }
        .meta-box { background: #f8fafc; border-radius: 8px; padding: 10px 14px; }
        .meta-label { font-size: 8px; text-transform: uppercase; letter-spacing: .09em; color: #94a3b8; font-weight: 700; }
        .meta-value { font-size: 13px; font-weight: 900; color: #0f172a; margin-top: 2px; }

        .section-title { font-size: 12px; font-weight: 900; color: #7c3aed; text-transform: uppercase;
                         letter-spacing: .08em; border-bottom: 1px solid #e2e8f0; padding-bottom: 6px; margin: 18px 0 10px; }

        table.grades { width: 100%; border-collapse: collapse; font-size: 10.5px; }
        table.grades thead th { background: #ede9fe; color: #5b21b6; font-weight: 700; padding: 6px 10px;
                                  text-align: left; font-size: 9px; text-transform: uppercase; letter-spacing: .06em; }
        table.grades tbody td { padding: 7px 10px; border-bottom: 1px solid #f1f5f9; color: #334155; }
        table.grades tbody tr:nth-child(even) td { background: #f8fafc; }

        .letter-a { color: #065f46; font-weight: 900; }
        .letter-b { color: #1e40af; font-weight: 900; }
        .letter-c { color: #92400e; font-weight: 900; }
        .letter-d { color: #c2410c; font-weight: 900; }
        .letter-f { color: #9f1239; font-weight: 900; }

        .avg-block { text-align: right; margin-top: 16px; padding: 14px 18px; background: #f8fafc; border-radius: 8px;
                     border-left: 4px solid #7c3aed; }
        .avg-label { font-size: 10px; color: #64748b; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; }
        .avg-value { font-size: 30px; font-weight: 900; margin-top: 2px; }

        .obs-box { margin-top: 16px; background: #fefce8; border: 1px solid #fde68a; border-radius: 8px; padding: 12px 14px; }
        .obs-label { font-size: 9px; color: #78350f; font-weight: 700; text-transform: uppercase; letter-spacing: .07em; margin-bottom: 4px; }
        .obs-text { font-size: 11px; color: #451a03; line-height: 1.6; }

        .signatures { margin-top: 32px; border-top: 1px solid #e2e8f0; padding-top: 20px; }
        .sig-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .sig-table td { border: none; text-align: center; padding: 0 10px; vertical-align: top; }
        .sig-line { height: 44px; border-bottom: 1px solid #64748b; margin: 0 auto; width: 80%; }
        .sig-name { margin-top: 6px; font-size: 11px; font-weight: 700; color: #334155; }
        .sig-role { margin-top: 2px; font-size: 9px; color: #94a3b8; text-transform: uppercase; letter-spacing: .07em; }

        .footer { text-align: center; font-size: 8.5px; color: #94a3b8; margin-top: 20px;
                  padding-top: 10px; border-top: 1px solid #e2e8f0; }

        @php
            function letterColorClass($letter) {
                return match($letter) {
                    'A'  => 'letter-a',
                    'B+' => 'letter-b',
                    'C+' => 'letter-c',
                    'D'  => 'letter-d',
                    default => 'letter-f',
                };
            }
            function avgColor($avg) {
                if ($avg >= 90) return '#065f46';
                if ($avg >= 80) return '#1e40af';
                if ($avg >= 70) return '#92400e';
                if ($avg >= 60) return '#c2410c';
                return '#9f1239';
            }
        @endphp
    </style>
</head>
<body>

    {{-- Header --}}
    <div class="header">
        <table class="header-inner">
            <tr>
                <td style="width:70px;">
                    <div class="logo-box">LOGO</div>
                </td>
                <td style="padding-left:14px;">
                    <p class="doc-type">Boleta Oficial de Calificaciones</p>
                    <p class="school-name">{{ $colegio?->name ?? 'AulaSync' }}</p>
                    <span class="period-badge">{{ $period->name }}</span>
                </td>
                <td style="width:130px;text-align:right;">
                    <div class="meta-label">Emitida el</div>
                    <div class="meta-value">{{ now()->format('d/m/Y') }}</div>
                    <div class="meta-label" style="margin-top:6px;">Estado</div>
                    <div style="font-size:10px;font-weight:700;color:#16a34a;">PUBLICADA</div>
                </td>
            </tr>
        </table>
    </div>

    {{-- Student meta --}}
    <table class="meta-grid">
        <tr>
            <td style="width:33%;padding-right:8px;">
                <div class="meta-box">
                    <div class="meta-label">Estudiante</div>
                    <div class="meta-value">{{ $student->name }}</div>
                </div>
            </td>
            <td style="width:22%;padding-right:8px;">
                <div class="meta-box">
                    <div class="meta-label">Grado / Sección</div>
                    <div class="meta-value">{{ $student->grade }} {{ $student->section ? '/ '.$student->section : '' }}</div>
                </div>
            </td>
            <td style="width:22%;padding-right:8px;">
                <div class="meta-box">
                    <div class="meta-label">Período</div>
                    <div class="meta-value" style="font-size:11px;">{{ $period->start_date?->format('d/m/Y') }} – {{ $period->end_date?->format('d/m/Y') }}</div>
                </div>
            </td>
            <td style="width:22%;">
                <div class="meta-box">
                    <div class="meta-label">C.I. / Expediente</div>
                    <div class="meta-value" style="font-size:11px;">{{ $student->document_id ?? '—' }}</div>
                </div>
            </td>
        </tr>
    </table>

    {{-- Grades --}}
    <div class="section-title">Calificaciones por asignatura</div>

    <table class="grades">
        <thead>
            <tr>
                <th>Asignatura</th>
                <th style="width:90px;text-align:center;">Nota (%)</th>
                <th style="width:60px;text-align:center;">Literal</th>
                <th>Observaciones del docente</th>
            </tr>
        </thead>
        <tbody>
            @forelse($grades as $grade)
                @php $letter = $grade->letter_grade ?? '—'; @endphp
                <tr>
                    <td style="font-weight:700;">{{ $grade->course_name }}</td>
                    <td style="text-align:center;font-weight:900;color:{{ avgColor((float)$grade->grade) }};">
                        {{ number_format((float)$grade->grade, 1) }}%
                    </td>
                    <td style="text-align:center;" class="{{ letterColorClass($letter) }}">{{ $letter }}</td>
                    <td style="color:#64748b;font-size:10px;">{{ $grade->teacher_observations ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="4" style="color:#94a3b8;font-style:italic;text-align:center;">Sin calificaciones registradas.</td></tr>
            @endforelse
        </tbody>
    </table>

    {{-- Global average --}}
    <div class="avg-block">
        <div class="avg-label">Promedio general del período</div>
        <div class="avg-value" style="color:{{ avgColor($global_average) }};">
            {{ number_format($global_average, 1) }}%
        </div>
    </div>

    {{-- Director observations --}}
    @if($card->observations)
    <div class="obs-box">
        <div class="obs-label">Observaciones del director</div>
        <div class="obs-text">{{ $card->observations }}</div>
    </div>
    @endif

    {{-- Signatures --}}
    <div class="signatures">
        <table class="sig-table">
            <tr>
                <td>
                    <div class="sig-line"></div>
                    <p class="sig-name">Director(a)</p>
                    <p class="sig-role">Firma y sello</p>
                </td>
                <td>
                    <div class="sig-line"></div>
                    <p class="sig-name">Representante</p>
                    <p class="sig-role">Recibido conforme</p>
                </td>
                <td>
                    <div class="sig-line"></div>
                    <p class="sig-name">Sello Oficial</p>
                    <p class="sig-role">Institución</p>
                </td>
            </tr>
        </table>
    </div>

    <div class="footer">
        <p>Documento oficial emitido por AulaSync · {{ now()->format('d/m/Y H:i') }}</p>
        <p>Boleta #{{ $card->id }} · Este documento es válido sin firma digital si lleva sello institucional.</p>
    </div>

</body>
</html>
