@extends('super-admin.layout')
@section('title', 'Salud de colegios')
@section('content')
    <h1>Salud de colegios</h1>
    <p class="sub">Adopción por colegio con datos reales. Activo = entró en los últimos 7 días; en riesgo = 8–30 días; inactivo = sin acceso en 30 días.</p>

    @if (session('invitation_url'))
        <div class="card">
            <h3>Enlace mágico generado</h3>
            <p class="sub">Cópialo y envíaselo al director. Vence en 48 horas.</p>
            <input type="text" readonly value="{{ session('invitation_url') }}" style="width:100%;">
        </div>
    @endif

    <div class="card">
        <h3>Registrar colegio y enviar enlace al director</h3>
        <form method="POST" action="{{ route('super-admin.schools.store') }}" class="filters">
            @csrf
            <div>
                <label>Nombre del colegio</label>
                <input type="text" name="name" required maxlength="180" placeholder="U.E. Aula Nueva">
            </div>
            <div>
                <label>Correo del director</label>
                <input type="email" name="director_email" required placeholder="director@colegio.edu">
            </div>
            <button class="btn" type="submit"><i class="fa-solid fa-paper-plane"></i> Crear y enviar enlace</button>
        </form>
    </div>

    <div class="card" x-data="schoolPurge()">
        @if ($schools->isEmpty())
            <p class="empty">No hay colegios registrados.</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Colegio</th>
                        <th>Director</th>
                        <th>Usuarios</th>
                        <th>Docentes</th>
                        <th>Actividad</th>
                        <th>Último acceso</th>
                        <th>Funciones</th>
                        <th>Adopción</th>
                        <th>Errores</th>
                        <th>Estado</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($schools as $school)
                        <tr>
                            <td>
                                <span class="table-identity">
                                    <span class="table-avatar">{{ strtoupper(substr($school['name'], 0, 1)) }}</span>
                                    <strong>{{ $school['name'] }}</strong>
                                </span>
                            </td>
                            <td>{{ $school['director'] ?? '—' }}</td>
                            <td>{{ $school['usuarios'] }}</td>
                            <td>{{ $school['docentes'] }}</td>
                            <td><span class="status-badge neutral">{{ $school['actividad'] }}</span></td>
                            <td>{{ $school['ultimo_acceso']?->format('d/m/Y H:i') ?? 'Sin login' }}</td>
                            <td><span class="status-badge neutral">{{ $school['funciones'] }}</span></td>
                            <td><span class="status-badge success">{{ $school['adopcion'] }}</span></td>
                            <td><span class="status-badge {{ $school['errores'] > 0 ? 'failed' : 'success' }}">{{ $school['errores'] }}</span></td>
                            <td><span class="pill {{ $school['estado'] }}">{{ \App\Support\SuperAdminCopy::status($school['estado']) }}</span></td>
                            <td style="display:flex;gap:8px;align-items:center;">
                                <a class="btn btn-ghost" href="{{ url('/super-admin/colegios/'.$school['id']) }}">Ver</a>
                                <button type="button" class="btn btn-danger" @click="openFor(@js([
                                    'id' => $school['id'],
                                    'name' => $school['name'],
                                    'usuarios' => $school['usuarios'],
                                    'docentes' => $school['docentes'],
                                    'alumnos' => $school['alumnos'],
                                    'cursos' => $school['cursos'],
                                ]))">Eliminar</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <div class="sa-overlay" x-cloak x-show="open" @click.self="open = false" role="dialog" aria-modal="true">
            <div class="sa-dialog" style="max-width:520px;">
                <h3>Eliminar colegio</h3>
                <p>Vas a borrar <strong x-text="school.name"></strong> y todo lo asociado: usuarios, cursos, matrículas, notas, asistencia, actividades, evaluaciones y mensajes.</p>
                <p class="sub" style="margin-top:8px;">
                    <span x-text="school.usuarios"></span> usuarios ·
                    <span x-text="school.docentes"></span> docentes ·
                    <span x-text="school.alumnos"></span> alumnos ·
                    <span x-text="school.cursos"></span> cursos
                </p>
                <p class="sub">Primero descarga el respaldo. Después escribe el nombre exacto del colegio.</p>
                <button type="button" class="btn" style="margin:10px 0;" @click="download()">
                    <i class="fa-solid fa-download"></i> Descargar respaldo JSON
                </button>
                <p class="ok" x-show="downloaded" x-cloak>Respaldo descargado. Ya puedes confirmar el borrado.</p>
                <p class="err" x-show="error" x-text="error" x-cloak></p>
                <form method="POST" :action="'/super-admin/colegios/' + school.id" style="margin-top:12px;">
                    @csrf
                    @method('DELETE')
                    <label>Nombre exacto del colegio</label>
                    <input type="text" name="confirm_name" x-model="typed" autocomplete="off" :placeholder="school.name" style="width:100%;margin:6px 0 12px;">
                    <div class="sa-actions">
                        <button type="button" class="btn btn-ghost" @click="open = false">Cancelar</button>
                        <button class="btn btn-danger" type="submit" :disabled="!ready">Eliminar definitivamente</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @include('super-admin.partials.audit-log')

    <script>
    function schoolPurge() {
        return {
            open: false,
            school: { id: '', name: '', usuarios: 0, docentes: 0, alumnos: 0, cursos: 0 },
            typed: '',
            downloaded: false,
            error: '',
            openFor(school) {
                this.school = school;
                this.typed = '';
                this.downloaded = false;
                this.error = '';
                this.open = true;
            },
            get ready() {
                return this.downloaded && this.typed === this.school.name;
            },
            async download() {
                this.error = '';
                try {
                    const res = await fetch('/super-admin/colegios/' + this.school.id + '/respaldo', {
                        credentials: 'same-origin',
                        headers: { 'Accept': 'application/json' },
                    });
                    if (!res.ok) {
                        this.error = 'No pude generar el respaldo. Intenta de nuevo.';
                        return;
                    }
                    const blob = await res.blob();
                    const url = URL.createObjectURL(blob);
                    const link = document.createElement('a');
                    link.href = url;
                    link.download = 'colegio-' + this.school.id + '-respaldo.json';
                    document.body.appendChild(link);
                    link.click();
                    link.remove();
                    URL.revokeObjectURL(url);
                    this.downloaded = true;
                } catch (e) {
                    this.error = 'No pude descargar el respaldo.';
                }
            },
        };
    }
    </script>
@endsection
