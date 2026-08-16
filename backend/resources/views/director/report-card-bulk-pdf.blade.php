<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Boletas en bloque · {{ $period->name }}</title>
    <style>
        @page { margin: 18mm 14mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10.5px; color: #1e293b; line-height: 1.5; }
        .page-break { page-break-after: always; }
        .header { border-bottom: 3px solid #7c3aed; padding-bottom: 12px; margin-bottom: 16px; }
        .school-name { font-size: 18px; font-weight: 900; color: #0f172a; margin: 0 0 2px; }
        .doc-type { font-size: 9px; color: #7c3aed; font-weight: 700; text-transform: uppercase; letter-spacing: .1em; }
        .period-badge { display: inline-block; padding: 2px 9px; border-radius: 12px; background: #ede9fe; color: #7c3aed; font-weight: 700; font-size: 9px; }
        .student-box { background: #f8fafc; border-radius: 6px; padding: 10px 12px; margin-bottom: 14px; }
        .student-name { font-size: 15px; font-weight: 900; color: #0f172a; }
        .student-meta { font-size: 9px; color: #64748b; margin-top: 2px; }
        table { width: 100%; border-collapse: collapse; font-size: 10px; margin-bottom: 12px; }
        thead th { background: #ede9fe; color: #5b21b6; font-weight: 700; padding: 5px 8px; text-align: left; font-size: 8.5px; text-transform: uppercase; letter-spacing: .06em; }
        tbody td { padding: 6px 8px; border-bottom: 1px solid #f1f5f9; }
        .avg-row { text-align: right; font-weight: 900; font-size: 13px; margin-top: 6px; }
        .obs-block { margin-top: 8px; padding: 8px 10px; background: #fefce8; border-radius: 6px; font-size: 9.5px; color: #451a03; }
        .footer-line { margin-top: 14px; padding-top: 10px; border-top: 1px solid #e2e8f0; font-size: 8px; color: #94a3b8; text-align: center; }
        .sig-table { width: 100%; border-collapse: collapse; table-layout: fixed; margin-top: 24px; }
        .sig-table td { border: none; text-align: center; padding: 0; vertical-align: top; }
        .sig-line { height: 36px; border-bottom: 1px solid #64748b; width: 80%; margin: 0 auto; }
        .sig-label { font-size: 9px; font-weight: 700; margin-top: 4px; color: #334155; }

        @php
            function bulkAvgColor($avg) {
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
@foreach($cards as $idx => $data)
    @php
        $card    = $data['card'];
        $student = $data['student'];
        $grades  = $data['grades'];
        $globalAvg = $data['global_average'];
    @endphp

    <div class="header">
        <div class="doc-type">Boleta Oficial de Calificaciones</div>
        <div class="school-name">{{ $colegio?->name ?? 'AulaSync' }}</div>
        <span class="period-badge">{{ $period->name }}</span>
        <span style="font-size:9px;color:#94a3b8;margin-left:8px;">Emitida: {{ now()->format('d/m/Y') }}</span>
    </div>

    <div class="student-box">
        <div class="student-name">{{ $student->name }}</div>
        <div class="student-meta">
            Grado: {{ $student->grade }} {{ $student->section ? '/ '.$student->section : '' }} &nbsp;·&nbsp;
            C.I.: {{ $student->document_id ?? '—' }} &nbsp;·&nbsp;
            Período: {{ $period->start_date?->format('d/m/Y') }} – {{ $period->end_date?->format('d/m/Y') }}
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Asignatura</th>
                <th style="width:80px;text-align:center;">Nota</th>
                <th style="width:55px;text-align:center;">Literal</th>
                <th>Observaciones</th>
            </tr>
        </thead>
        <tbody>
            @forelse($grades as $grade)
                <tr>
                    <td style="font-weight:700;">{{ $grade->course_name }}</td>
                    <td style="text-align:center;font-weight:900;color:{{ bulkAvgColor((float)$grade->grade) }};">
                        {{ number_format((float)$grade->grade, 1) }}%
                    </td>
                    <td style="text-align:center;font-weight:900;">{{ $grade->letter_grade ?? '—' }}</td>
                    <td style="color:#64748b;font-size:9.5px;">{{ $grade->teacher_observations ?? '' }}</td>
                </tr>
            @empty
                <tr><td colspan="4" style="color:#94a3b8;font-style:italic;">Sin calificaciones.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="avg-row" style="color:{{ bulkAvgColor($globalAvg) }};">
        Promedio general: {{ number_format($globalAvg, 1) }}%
    </div>

    @if($card->observations)
    <div class="obs-block">
        <strong>Observaciones:</strong> {{ $card->observations }}
    </div>
    @endif

    <table class="sig-table">
        <tr>
            <td><div class="sig-line"></div><div class="sig-label">Director(a)</div></td>
            <td><div class="sig-line"></div><div class="sig-label">Representante</div></td>
            <td><div class="sig-line"></div><div class="sig-label">Sello Oficial</div></td>
        </tr>
    </table>

    <div class="footer-line">
        AulaSync · Boleta #{{ $card->id }} · {{ now()->format('d/m/Y H:i') }}
    </div>

    @if(!$loop->last)
        <div class="page-break"></div>
    @endif
@endforeach
</body>
</html>
