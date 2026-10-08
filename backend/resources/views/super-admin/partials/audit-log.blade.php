@if (! empty($auditLogs) && $auditLogs->isNotEmpty())
    <div class="card">
        <h3>Registro de acciones del Founder Center</h3>
        <p class="sub">Solo visible aquí. Cada eliminación de colegio y cada edición de usuario queda con fecha, autor y qué cambió.</p>
        <table>
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Quién</th>
                    <th>Acción</th>
                    <th>Detalle</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($auditLogs as $log)
                    <tr>
                        <td>{{ $log->created_at?->format('d/m/Y H:i') }}</td>
                        <td>{{ $log->actor?->name ?? 'Sistema' }}</td>
                        <td>{{ $log->action === 'school.deleted' ? 'Eliminar colegio' : ($log->action === 'user.updated' ? 'Editar usuario' : $log->action) }}</td>
                        <td>
                            {{ $log->summary }}
                            @if (is_array($log->changes) && $log->changes !== [])
                                <div class="sub" style="margin:4px 0 0;">
                                    @foreach ($log->changes as $field => $change)
                                        @if ($field === 'password')
                                            Contraseña reemplazada.
                                        @elseif ($field === 'counts' && is_array($change))
                                            Usuarios {{ $change['usuarios'] ?? 0 }}, docentes {{ $change['docentes'] ?? 0 }}, alumnos {{ $change['alumnos'] ?? 0 }}, cursos {{ $change['cursos'] ?? 0 }}.
                                        @elseif (is_array($change) && array_key_exists('from', $change))
                                            {{ $field }}: {{ $change['from'] ?? '—' }} → {{ $change['to'] ?? '—' }}.
                                        @endif
                                    @endforeach
                                </div>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
