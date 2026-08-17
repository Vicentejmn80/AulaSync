<x-app-layout>
    @push('styles')
    @include('partials.nova-theme')
    <style>
        :root {
            --hist-grad-dark:    linear-gradient(160deg, #1e0f3c 0%, #2d1569 60%, #4a1072 100%);
            --hist-grad-primary: linear-gradient(135deg, #7c3aed 0%, #c026d3 100%);
            --hist-grad-manual:  linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);
        }
        html.dark {
            --hist-grad-dark: linear-gradient(160deg, #0A0A1F 0%, #12122B 60%, #1E1A3A 100%);
        }
        /* Hide legacy white layout chrome for this screen */
        body > nav.navbar,
        body > footer {
            display: none !important;
        }

        main.min-h-screen {
            min-height: auto !important;
        }

        body { background: var(--bg-primary) !important; }
        html.dark body { background: var(--bg-primary) !important; }
        html.dark .historial-card { background: var(--bg-card); border-color: var(--nova-glass-border); box-shadow: var(--nova-shadow); }
        html.dark .historial-card h3 { color: var(--text-primary); }
        html.dark .hist-obj { color: var(--text-secondary); }
        html.dark .hist-meta { color: var(--text-tertiary); }
        html.dark .hist-badge:not(.bulk):not(.manual) { background: rgba(124,58,237,.12); color: var(--nova-violet); border-color: var(--nova-glass-border); }
        html.dark .hist-badge.ai { background: rgba(124,58,237,.12); color: var(--nova-violet); }
        html.dark .empty-card { background: var(--bg-card); border-color: var(--nova-glass-border); }
        html.dark .empty-card h2 { color: var(--text-primary); }
        html.dark .empty-card p { color: var(--text-secondary); }
        html.dark .historial-modal-card { background: var(--bg-secondary); }
        html.dark .hist-modal-body .text-muted { color: var(--text-secondary) !important; }
        html.dark .hist-modal-body .font-weight-bold { color: var(--text-primary); }
        html.dark .btn-cancel-hist { background: var(--bg-tertiary); color: var(--text-primary); }

        .hist-banner {
            background: var(--hist-grad-dark);
            border-radius: 1.5rem;
            padding: 2rem;
            margin-bottom: 2rem;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        @media (min-width: 768px) {
            .hist-banner { flex-direction: row; align-items: center; justify-content: space-between; }
        }
        .hist-banner h1 {
            font-size: 1.85rem; font-weight: 900; margin: 0;
            background: linear-gradient(90deg, #f9f5ff, #fde9ff);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }
        .hist-banner p {
            color: #d8c7ff;
            margin: .35rem 0 0;
            font-size: .95rem;
            max-width: 360px;
        }

        .btn-hist-primary,
        .btn-hist-secondary {
            display: inline-flex; align-items: center; gap: .5rem;
            font-weight: 700; font-size: .9rem;
            padding: .65rem 1.4rem; border-radius: 999px; border: none;
            text-decoration: none; cursor: pointer; white-space: nowrap;
            transition: transform .15s, box-shadow .15s, opacity .15s;
        }
        .btn-hist-primary {
            background: var(--hist-grad-primary); color: #fff !important;
            box-shadow: 0 12px 30px rgba(124,58,237,.25);
        }
        .btn-hist-primary:hover { opacity: .96; transform: translateY(-2px); box-shadow: 0 18px 38px rgba(124,58,237,.3); }
        .btn-hist-secondary {
            background: rgba(255,255,255,.15); border: 1px solid rgba(255,255,255,.4); color: #fff !important;
            box-shadow: 0 10px 20px rgba(15,23,42,.25);
        }
        .btn-hist-secondary:hover { opacity: .92; }

        .historial-card {
            background: #fff;
            border-radius: 1.5rem;
            padding: 1.5rem;
            min-height: 240px;
            display: flex;
            flex-direction: column;
            transition: transform .2s ease, box-shadow .2s ease;
            border: 1px solid rgba(124, 58, 237, .15);
            box-shadow: 0 20px 40px rgba(15, 23, 42, .06);
            position: relative;
            overflow: hidden;
        }
        .historial-card::before {
            content: '';
            position: absolute; top: 0; left: 0; right: 0; height: 6px;
            background: var(--hist-grad-primary);
        }
        .historial-card.is-manual::before {
            background: var(--hist-grad-manual);
        }

        .historial-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 26px 48px rgba(15, 23, 42, .16);
        }
        .hist-badge {
            display: inline-block; font-size: .72rem; font-weight: 700;
            padding: .25rem .7rem; border-radius: 999px; margin-bottom: .6rem;
            background: #f3f4f6; color: #374151;
        }
        .hist-badge.bulk { background: linear-gradient(135deg,#c026d3,#db2777); color: #fff; }
        .hist-badge.manual { background: var(--hist-grad-manual); color: #fff; }
        .hist-badge.ai { background: #f5f3ff; color: #7c3aed; border: 1px solid #ddd6fe; }
        .hist-status {
            display: inline-flex; align-items: center; gap: .35rem; font-size: .7rem; font-weight: 800;
            padding: .25rem .65rem; border-radius: 999px; margin-left: .35rem; border: 1px solid transparent;
        }
        .hist-status.pending { background: #fef3c7; color: #92400e; border-color: #fde68a; }
        .hist-status.revision { background: #cffafe; color: #155e75; border-color: #67e8f9; }
        .hist-status.approved { background: #d1fae5; color: #065f46; border-color: #6ee7b7; }
        .hist-status.rejected { background: #ffe4e6; color: #9f1239; border-color: #fecdd3; }

        .historial-card h3 {
            font-size: 1rem; font-weight: 800; color: #1e1b4b; margin: 0 0 .5rem;
        }
        .hist-obj {
            font-size: .85rem; color: #6b7280; flex: 1;
            display: -webkit-box; -webkit-line-clamp: 3;
            -webkit-box-orient: vertical; overflow: hidden;
            margin-bottom: .75rem;
        }
        .hist-meta { font-size: .78rem; color: #8b5cf6; margin-bottom: .85rem; }
        .hist-actions { display: flex; justify-content: space-between; align-items: center; gap: .5rem; }
        
        .btn-hist-delete {
            width: 32px; height: 32px; border-radius: .75rem; border: 1px solid rgba(192,38,211,.2);
            background: #fdf4ff; color: #c026d3; display: flex; align-items: center;
            justify-content: center; cursor: pointer; transition: background .15s;
        }
        .btn-hist-delete:hover { background: rgba(220, 38, 113, .12); }
        
        .btn-hist-open {
            display: inline-flex; align-items: center; gap: .4rem;
            background: var(--hist-grad-primary); color: #fff !important;
            font-weight: 700; font-size: .85rem; padding: .5rem 1.1rem;
            border-radius: .85rem; text-decoration: none; transition: opacity .15s;
        }
        .btn-hist-open.btn-manual { background: var(--hist-grad-manual); }
        .btn-hist-open:hover { opacity: .92; }

        .empty-card {
            max-width: 540px;
            margin: 0 auto;
            background: rgba(255,255,255,.9);
            border-radius: 1.25rem;
            border: 1px solid rgba(124, 58, 237, .12);
            padding: 2rem;
            text-align: center;
            box-shadow: 0 24px 48px rgba(30,15,60,.12);
        }
        .empty-card .icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 1rem;
            border-radius: 1rem;
            background: linear-gradient(135deg,#c026d3,#7c3aed);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: #fff;
        }
        .empty-card h2 {
            font-size: 1.35rem;
            font-weight: 700;
            margin-bottom: .45rem;
            color: #130a2b;
        }
        .empty-card p {
            color: #6b7280;
            margin-bottom: 1rem;
        }
        .empty-card .empty-helper {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            font-size: .85rem;
            color: #7c3aed;
        }

        /* Modal Styles */
        .historial-modal-backdrop {
            position: fixed; inset: 0;
            background: rgba(30,15,60,.55); backdrop-filter: blur(4px);
            display: none; align-items: center; justify-content: center;
            z-index: 11000; padding: 1rem;
        }
        .historial-modal-backdrop.show { display: flex; }
        .historial-modal-card {
            width: min(96vw, 420px); background: #fff;
            border-radius: 1.25rem; box-shadow: 0 24px 48px rgba(30,15,60,.25);
            overflow: hidden;
        }
        .hist-modal-header { background: var(--hist-grad-dark); padding: 1rem 1.25rem; color: #fff; font-weight: 800; }
        .hist-modal-body { padding: 1.25rem; }
        .hist-modal-actions { display: flex; justify-content: flex-end; gap: .5rem; margin-top: 1rem; }
        .btn-cancel-hist { background: #f3f4f6; color: #374151; font-weight: 600; padding: .5rem 1.1rem; border-radius: .625rem; border: none; cursor: pointer; }
        .btn-delete-hist { background: linear-gradient(135deg,#db2777,#9d174d); color: #fff; font-weight: 700; padding: .5rem 1.1rem; border-radius: .625rem; border: none; cursor: pointer; }
    </style>
    @endpush

    <nav class="sticky top-0 z-30" style="background:var(--hist-grad-dark); border-bottom:1px solid rgba(124,58,237,.22);">
        <div class="max-w-6xl mx-auto px-4 flex items-center gap-1 h-14">
            <a href="{{ route('teacher.hub') }}"
               class="text-purple-300 hover:text-white mr-3 transition text-sm flex items-center gap-1.5">
                <i class="fa-solid fa-arrow-left text-xs"></i> Hub
            </a>
            <span class="text-purple-700 mx-1">|</span>
            <a href="{{ route('teacher.courses.index') }}"
               class="px-4 py-2 rounded-xl text-sm font-medium text-purple-300 hover:text-white hover:bg-white/10 transition">
                <i class="fa-solid fa-chalkboard mr-1.5"></i> Cursos
            </a>
            <a href="{{ route('teacher.activities.index') }}"
               class="px-4 py-2 rounded-xl text-sm font-medium text-purple-300 hover:text-white hover:bg-white/10 transition">
                <i class="fa-solid fa-clipboard-list mr-1.5"></i> Actividades
            </a>
            <a href="{{ route('historial') }}"
               class="px-4 py-2 rounded-xl text-sm font-semibold text-white bg-white/15">
                <i class="fa-solid fa-folder-open mr-1.5"></i> Mis Planificaciones
            </a>
            <div class="ml-auto">
                @include('components.ai-assistant-bubble')
            </div>
        </div>
    </nav>

    <div class="container py-5">
        <div class="hist-banner">
            <div>
                <h1>📚 Historial de Planificaciones</h1>
                <p>Gestiona, visualiza y mejora tus clases guardadas bajo el sello AulaSync.</p>
            </div>
            <div class="flex flex-wrap gap-3">
                <a href="{{ route('teacher.hub') }}" class="btn-hist-secondary">
                    <i class="fas fa-arrow-left"></i> Volver al Hub
                </a>
                <a href="{{ route('teacher.planner.manual') }}" class="btn-hist-primary">
                    <i class="fas fa-plus"></i> Nueva Planificación
                </a>
                <button type="button" class="btn-hist-secondary" onclick="window.dispatchEvent(new CustomEvent('nova-lesson-template-picker'))">
                    <i class="fas fa-palette"></i> Estilo de clase
                </button>
            </div>
        </div>

        @if($plans->isEmpty())
            <div class="py-12">
                <div class="empty-card mx-auto">
                    <div class="icon">
                        <i class="fas fa-folder-open"></i>
                    </div>
                    <h2>No hay planes guardados</h2>
                    <p>Tus planificaciones aparecerán aquí en cuanto las guardes. Puedes crear una nueva planificación con el botón superior.</p>
                    <div class="empty-helper">
                        <i class="fas fa-info-circle"></i>
                        Mantén tu historial organizado con el clic del asistente.
                    </div>
                </div>
            </div>
        @else
            <div class="row g-4" id="historialGrid">
                @foreach($plans as $plan)
                    @php
                        // Aseguramos que el payload sea un array
                        $payload = is_array($plan->payload) ? $plan->payload : json_decode($plan->payload, true) ?? [];
                        $type = $payload['type'] ?? 'ai_plan';
                        $isManual = $type === 'manual_plan';
                        $isBulk = $type === 'bulk_plan';
                        $courseId = $payload['course_id'] ?? null;
                        $firstSessionDate = isset($payload['sessions'][0]['date'])
                            ? $payload['sessions'][0]['date']
                            : null;
                        $manualMonth = $firstSessionDate
                            ? \Illuminate\Support\Carbon::parse($firstSessionDate)->format('Y-m')
                            : null;
                        $status = $plan->status ?: 'pendiente';
                        $statusMeta = [
                            'pendiente' => ['class' => 'pending', 'icon' => 'fa-clock', 'label' => 'Pendiente Dirección'],
                            'pendiente_revision' => ['class' => 'revision', 'icon' => 'fa-rotate-right', 'label' => 'Reenviada a Dirección'],
                            'aprobado' => ['class' => 'approved', 'icon' => 'fa-check', 'label' => 'Aprobada'],
                            'rechazado' => ['class' => 'rejected', 'icon' => 'fa-xmark', 'label' => 'Requiere corrección'],
                        ][$status] ?? ['class' => 'pending', 'icon' => 'fa-clock', 'label' => ucfirst($status)];
                    @endphp

                    <div class="col-12 col-md-6 col-lg-4" data-plan-card-id="{{ $plan->id }}">
                        <div class="historial-card {{ $isManual ? 'is-manual' : '' }}">
                            
                            {{-- Badge Dinámico --}}
                            @if($isManual)
                                <span class="hist-badge manual"><i class="fas fa-hand-paper me-1"></i> Plan Manual</span>
                            @elseif($isBulk)
                                <span class="hist-badge bulk"><i class="fas fa-calendar-alt me-1"></i> Plan Mensual</span>
                            @else
                                <span class="hist-badge ai"><i class="fas fa-robot me-1"></i> Generado por IA</span>
                            @endif
                            <span class="hist-status {{ $statusMeta['class'] }}">
                                <i class="fas {{ $statusMeta['icon'] }}"></i>{{ $statusMeta['label'] }}
                            </span>

                            @if($status === 'rechazado' && !empty($payload['rechazo_feedback']))
                                <div class="mt-2 mb-2 rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-800">
                                    <strong>Feedback Dirección:</strong> {{ \Illuminate\Support\Str::limit($payload['rechazo_feedback'], 120) }}
                                </div>
                            @endif

                            <h3>{{ $plan->tema ?: 'Sin título' }}</h3>
                            
                            <p class="hist-obj">
                                {{ \Illuminate\Support\Str::limit($plan->objetivo ?: 'Sin objetivo definido', 120) }}
                            </p>

                            <div class="hist-meta">
                                <span><i class="far fa-calendar-alt me-1"></i> {{ $plan->created_at?->format('d/m/Y') }}</span>
                                @if(isset($payload['nivel_educativo']))
                                    <span><i class="fas fa-graduation-cap me-1"></i> {{ ucfirst($payload['nivel_educativo']) }}</span>
                                @endif
                            </div>

                            <div class="hist-actions">
                                {{-- Botón Eliminar --}}
                                <button type="button" 
                                        class="btn-hist-delete btn-delete-plan" 
                                        data-plan-id="{{ $plan->id }}" 
                                        data-plan-title="{{ $plan->tema }}"
                                        title="Eliminar">
                                    <i class="fas fa-trash-alt"></i>
                                </button>

                                {{-- Botón Abrir Dinámico --}}
                                @if($isManual)
                                    {{-- Enlace corregido a la ficha técnica --}}
                                <a href="{{ route('teacher.hub', ['plan_block' => $plan->id, 'month' => $manualMonth]) }}" class="btn-hist-open btn-manual">
                                        <i class="fas fa-eye me-1"></i> Ver Sesiones
                                    </a>
                                @elseif($isBulk && $courseId)
                                    <a href="{{ route('teacher.hub', ['course' => $courseId, 'plan_block' => $plan->id]) }}" class="btn-hist-open">
                                        <i class="fas fa-calendar-check me-1"></i> Calendario
                                    </a>
                                @else
                                    <a href="{{ route('dashboard', ['plan' => $plan->id]) }}" class="btn-hist-open">
                                        <i class="fas fa-folder-open me-1"></i> Abrir
                                    </a>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Modal de Confirmación de Eliminación --}}
    <div id="deletePlanModal" class="historial-modal-backdrop" aria-hidden="true">
        <div class="historial-modal-card">
            <div class="hist-modal-header">
                <i class="fas fa-exclamation-triangle me-2"></i> Confirmar eliminación
            </div>
            <div class="hist-modal-body">
                <p class="text-muted mb-2">¿Estás seguro de que deseas eliminar esta planificación? Esta acción es irreversible.</p>
                <p class="font-weight-bold" id="deletePlanModalTitle"></p>
                <div class="hist-modal-actions">
                    <button type="button" class="btn-cancel-hist" id="btnCancelDelete">Cancelar</button>
                    <button type="button" class="btn-delete-hist" id="btnConfirmDelete">Eliminar ahora</button>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        (function () {
            'use strict';
            let pendingDeleteId = null;
            const modal = document.getElementById('deletePlanModal');
            const btnConfirm = document.getElementById('btnConfirmDelete');
            const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

            // Abrir modal
            document.addEventListener('click', function (e) {
                const btn = e.target.closest('.btn-delete-plan');
                if (btn) {
                    pendingDeleteId = btn.dataset.planId;
                    document.getElementById('deletePlanModalTitle').textContent = btn.dataset.planTitle;
                    modal.classList.add('show');
                }
            });

            // Cerrar modal
            document.getElementById('btnCancelDelete').addEventListener('click', () => modal.classList.remove('show'));

            // Confirmar eliminación
            btnConfirm.addEventListener('click', function () {
                if (!pendingDeleteId) return;
                
                btnConfirm.disabled = true;
                btnConfirm.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

                fetch(`/planificaciones/${pendingDeleteId}`, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' }
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        document.querySelector(`[data-plan-card-id="${pendingDeleteId}"]`).remove();
                        modal.classList.remove('show');
                    }
                })
                .catch(() => alert('Error al eliminar'))
                .finally(() => {
                    btnConfirm.disabled = false;
                    btnConfirm.textContent = 'Eliminar ahora';
                });
            });
        })();
    </script>
    @endpush
</x-app-layout>