<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>AulaSync · Familia</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@600;700;800;900&display=swap" rel="stylesheet">
    @include('partials.nova-theme')
    <style>
        :root {
            --font-display: 'Manrope', Inter, system-ui, sans-serif;
            --az-radius-lg: 26px;
            --az-radius-md: 18px;
        }
        [x-cloak] { display: none !important; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: -apple-system, BlinkMacSystemFont, 'Manrope', Inter, system-ui, sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            -webkit-font-smoothing: antialiased;
        }
        .fam-shell { display: grid; grid-template-columns: 280px 1fr; min-height: 100vh; }
        .fam-sidebar {
            background: var(--bg-sidebar, var(--bg-secondary));
            border-right: 1px solid var(--nova-glass-border);
            padding: 22px 16px;
            display: flex; flex-direction: column; gap: 8px;
        }
        .brand { display: flex; align-items: center; gap: 12px; padding: 8px 10px 18px; }
        .brand-icon {
            width: 42px; height: 42px; border-radius: 14px; color: #fff;
            display: grid; place-items: center; background: var(--nova-gradient);
            box-shadow: var(--az-shadow-glow, 0 12px 30px rgba(124,58,237,.28));
        }
        .brand-title { font-family: var(--font-display); font-weight: 900; letter-spacing: -.03em; }
        .brand-sub { font-size: 11px; color: var(--text-secondary); }
        .nav-item {
            display: flex; align-items: center; gap: 10px;
            width: 100%; border: 0; background: transparent; color: var(--text-secondary);
            padding: 11px 12px; border-radius: 14px; font-weight: 700; cursor: pointer; text-align: left;
        }
        .nav-item i { width: 18px; }
        .nav-item.active, .nav-item:hover { background: var(--nova-glass); color: var(--text-primary); }
        .fam-main { padding: 22px 28px 110px; min-width: 0; }
        .topbar { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 22px; flex-wrap: wrap; }
        .student-select {
            display: flex; align-items: center; gap: 10px; min-width: min(420px, 100%);
            background: var(--bg-card); border: 1px solid var(--nova-glass-border);
            border-radius: 18px; padding: 8px 12px; box-shadow: var(--nova-shadow);
        }
        .avatar {
            width: 40px; height: 40px; border-radius: 14px; display: grid; place-items: center;
            color: #fff; font-weight: 800; background: var(--nova-gradient);
        }
        .student-select select {
            flex: 1; border: 0; background: transparent; color: var(--text-primary);
            font-weight: 800; font-size: 15px; outline: none;
        }
        .icon-btn {
            width: 44px; height: 44px; border-radius: 14px; border: 1px solid var(--nova-glass-border);
            background: var(--nova-glass); color: var(--nova-violet); cursor: pointer; position: relative;
        }
        .badge {
            position: absolute; top: -4px; right: -4px; min-width: 18px; height: 18px; padding: 0 5px;
            border-radius: 99px; background: #EC4899; color: #fff; font-size: 10px; font-weight: 800;
            display: grid; place-items: center;
        }
        .kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 22px; }
        .kpi, .panel {
            background: var(--bg-card); border: 1px solid var(--nova-glass-border);
            border-radius: var(--az-radius-lg); box-shadow: var(--nova-shadow); padding: 18px;
        }
        .kpi-label { font-size: 12px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; color: var(--text-tertiary); }
        .kpi-value { font-family: var(--font-display); font-size: 32px; font-weight: 900; margin: 8px 0 4px; }
        .kpi-hint { font-size: 13px; color: var(--text-secondary); }
        .split { display: grid; grid-template-columns: 1.05fr .95fr; gap: 16px; }
        .section-title { font-family: var(--font-display); font-size: 18px; font-weight: 800; margin: 0 0 14px; }
        .cal-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; }
        .cal-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 6px; }
        .cal-dow { font-size: 10px; font-weight: 800; color: var(--text-tertiary); text-align: center; }
        .cal-day {
            min-height: 54px; border-radius: 12px; border: 1px solid transparent; background: var(--bg-tertiary);
            padding: 6px; cursor: pointer; text-align: left;
        }
        .cal-day.active { border-color: var(--nova-violet); background: var(--nova-glass); }
        .dots { display: flex; gap: 3px; margin-top: 4px; flex-wrap: wrap; }
        .dot { width: 6px; height: 6px; border-radius: 99px; }
        .dot.class { background: #2563eb; } .dot.evaluation { background: #dc2626; }
        .dot.task { background: #f59e0b; } .dot.activity { background: #7c3aed; }
        .dot.absence, .dot.tardy { background: #fb7185; }
        .subject-row {
            display: grid; grid-template-columns: 1.2fr .9fr .5fr 1fr 1fr; gap: 10px;
            padding: 12px 0; border-bottom: 1px solid var(--nova-glass-border); cursor: pointer; align-items: center;
        }
        .subject-row:hover { color: var(--nova-violet); }
        .tabs { display: flex; gap: 8px; margin-bottom: 14px; flex-wrap: wrap; }
        .tab { border: 1px solid var(--nova-glass-border); background: transparent; color: var(--text-secondary); border-radius: 999px; padding: 8px 14px; font-weight: 700; cursor: pointer; }
        .tab.active { background: var(--nova-gradient); color: #fff; border-color: transparent; }
        .feed-item { padding: 12px 0; border-bottom: 1px solid var(--nova-glass-border); cursor: pointer; }
        .unread { color: var(--nova-violet); }
        .fab {
            position: fixed; right: 24px; bottom: 24px; z-index: 40; display: flex; flex-direction: column; gap: 8px; align-items: flex-end;
        }
        .fab-btn {
            border: 0; border-radius: 16px; padding: 12px 16px; font-weight: 800; color: #fff;
            background: var(--nova-gradient); box-shadow: var(--az-shadow-glow); cursor: pointer;
        }
        .overlay { position: fixed; inset: 0; background: rgba(8,6,20,.55); z-index: 50; display: grid; place-items: center; padding: 16px; }
        .modal { width: min(640px, 100%); background: var(--bg-secondary); border: 1px solid var(--nova-glass-border); border-radius: 28px; padding: 22px; max-height: 86vh; overflow: auto; }
        .btn { border: 0; border-radius: 14px; padding: 11px 16px; font-weight: 800; cursor: pointer; }
        .btn-primary { background: var(--nova-gradient); color: #fff; }
        .btn-ghost { background: var(--nova-glass); color: var(--text-primary); border: 1px solid var(--nova-glass-border); }
        input, select, textarea {
            width: 100%; border-radius: 14px; border: 1px solid var(--nova-glass-border);
            background: var(--bg-tertiary); color: var(--text-primary); padding: 11px 12px; margin: 6px 0 12px;
        }
        .chat-bubble { max-width: 80%; padding: 10px 12px; border-radius: 16px; margin: 8px 0; }
        .chat-bubble.mine { margin-left: auto; background: var(--nova-gradient); color: #fff; }
        .chat-bubble.theirs { background: var(--bg-tertiary); }
        .empty { color: var(--text-secondary); font-size: 14px; padding: 18px 0; }
        .mobile-bar { display: none; }
        @media (max-width: 1100px) {
            .kpi-grid, .split, .subject-row { grid-template-columns: 1fr 1fr; }
            .subject-row { display: flex; flex-direction: column; align-items: flex-start; }
        }
        @media (max-width: 860px) {
            .fam-shell { grid-template-columns: 1fr; }
            .fam-sidebar { display: none; }
            .fam-sidebar.open { display: flex; position: fixed; inset: 0 auto 0 0; width: 280px; z-index: 30; }
            .mobile-bar { display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; }
            .kpi-grid { grid-template-columns: 1fr 1fr; }
            .fam-main { padding: 12px 16px 120px; }
        }
    </style>
</head>
<body x-data="familyHub">
    <div class="mobile-bar">
        <button class="icon-btn" @click="sidebarOpen = !sidebarOpen"><i class="fa-solid fa-bars"></i></button>
        <strong>AulaSync Familia</strong>
        <button class="icon-btn" @click="toggleTheme()"><i class="fa-solid" :class="isDark ? 'fa-sun' : 'fa-moon'"></i></button>
    </div>

    <div class="fam-shell">
        <aside class="fam-sidebar" :class="{ open: sidebarOpen }">
            <div class="brand">
                <div class="brand-icon"><i class="fa-solid fa-robot"></i></div>
                <div>
                    <div class="brand-title">AulaSync</div>
                    <div class="brand-sub">{{ $schoolName ?? 'Panel familiar' }}</div>
                </div>
            </div>
            <button class="nav-item" :class="{ active: view === 'home' }" @click="view = 'home'; sidebarOpen = false"><i class="fa-solid fa-house"></i> Inicio</button>
            <button class="nav-item" :class="{ active: view === 'calendar' }" @click="view = 'calendar'; sidebarOpen = false"><i class="fa-solid fa-calendar-days"></i> Calendario</button>
            <button class="nav-item" :class="{ active: view === 'subjects' }" @click="view = 'subjects'; sidebarOpen = false"><i class="fa-solid fa-book-open"></i> Materias</button>
            <button class="nav-item" :class="{ active: view === 'comms' }" @click="view = 'comms'; sidebarOpen = false">
                <i class="fa-solid fa-comments"></i> Comunicación
                <span x-show="unreadAnnouncements > 0" x-text="unreadAnnouncements" style="margin-left:auto;background:#EC4899;color:#fff;border-radius:99px;padding:1px 7px;font-size:11px;"></span>
            </button>
            <button class="nav-item" :class="{ active: view === 'docs' }" @click="view = 'docs'; sidebarOpen = false"><i class="fa-solid fa-folder-open"></i> Documentos</button>
            <div style="flex:1"></div>
            <button class="nav-item" @click="openProfile = true"><i class="fa-solid fa-user-gear"></i> Editar perfil</button>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="nav-item" type="submit"><i class="fa-solid fa-right-from-bracket"></i> Cerrar sesión</button>
            </form>
        </aside>

        <main class="fam-main">
            <div class="topbar">
                <div class="student-select">
                    <div class="avatar" x-text="currentStudent?.initials || 'A'"></div>
                    <div style="flex:1;min-width:0">
                        <div style="font-size:11px;color:var(--text-tertiary);font-weight:800;">ESTUDIANTE</div>
                        <select x-model="studentId" @change="refreshAll()">
                            <template x-for="s in students" :key="s.id">
                                <option :value="s.id" x-text="s.name + ' · ' + (s.grade || '')"></option>
                            </template>
                        </select>
                    </div>
                </div>
                <div style="display:flex;gap:8px;align-items:center">
                    <button class="icon-btn" @click="toggleNotif()" title="Notificaciones">
                        <i class="fa-solid fa-bell"></i>
                        <span class="badge" x-show="unreadNotif > 0" x-text="unreadNotif"></span>
                    </button>
                    <div class="student-select" style="min-width:auto;padding:8px 12px 8px 8px">
                        <div class="avatar" style="width:34px;height:34px">{{ $parent['initials'] }}</div>
                        <div>
                            <div style="font-weight:800;font-size:13px">{{ $parent['name'] }}</div>
                            <div style="font-size:11px;color:var(--text-secondary)">Representante</div>
                        </div>
                    </div>
                </div>
            </div>

            <div x-show="showNotif" @click.outside="showNotif = false" class="panel" style="position:absolute;right:28px;z-index:20;width:min(360px,90vw)">
                <div style="display:flex;justify-content:space-between;align-items:center">
                    <strong>Notificaciones</strong>
                    <button class="btn btn-ghost" style="padding:6px 10px" @click="markNotifRead()">Marcar leídas</button>
                </div>
                <template x-if="notifications.length === 0"><p class="empty">Sin notificaciones.</p></template>
                <template x-for="n in notifications" :key="n.id">
                    <div class="feed-item">
                        <div :class="{ unread: !n.read }" style="font-weight:800" x-text="n.title"></div>
                        <div style="font-size:12px;color:var(--text-secondary)" x-text="n.message"></div>
                    </div>
                </template>
            </div>

            <template x-if="students.length === 0">
                <div class="panel" style="text-align:center;padding:60px 20px">
                    <i class="fa-solid fa-link" style="font-size:36px;color:var(--nova-violet)"></i>
                    <h2>Aún no hay hijos vinculados</h2>
                    <p class="empty">Usa el código familiar NV- que te dio el colegio para completar el onboarding.</p>
                </div>
            </template>

            <div x-show="students.length > 0">
                <div x-show="view === 'home' || view === 'subjects'">
                    <div class="kpi-grid">
                        <article class="kpi">
                            <div class="kpi-label">Asistencia</div>
                            <div class="kpi-value" x-text="summary.attendance?.percent != null ? summary.attendance.percent + '%' : '—'"></div>
                            <div class="kpi-hint" x-text="summary.attendance?.label"></div>
                        </article>
                        <article class="kpi">
                            <div class="kpi-label">Promedio general</div>
                            <div class="kpi-value" x-text="summary.average?.value ?? '—'"></div>
                            <div class="kpi-hint" x-text="summary.average?.label"></div>
                        </article>
                        <article class="kpi">
                            <div class="kpi-label">Tareas pendientes</div>
                            <div class="kpi-value" x-text="summary.pending_tasks?.count ?? 0"></div>
                            <div class="kpi-hint" x-text="summary.pending_tasks?.next_title ? (summary.pending_tasks.next_title + ' · ' + fmt(summary.pending_tasks.next_date)) : 'Sin entregas próximas'"></div>
                        </article>
                        <article class="kpi">
                            <div class="kpi-label">Próximas evaluaciones</div>
                            <div class="kpi-value" x-text="summary.evaluations?.count ?? 0"></div>
                            <div class="kpi-hint" x-text="summary.evaluations?.next_title ? (summary.evaluations.next_title + ' · ' + fmt(summary.evaluations.next_date)) : 'Calendario libre'"></div>
                        </article>
                    </div>
                </div>

                <div class="split" x-show="view === 'home'">
                    <section class="panel">
                        <div class="cal-head">
                            <h2 class="section-title" style="margin:0" x-text="calendar.label || 'Calendario'"></h2>
                            <div>
                                <button class="btn btn-ghost" @click="shiftMonth(-1)"><i class="fa-solid fa-chevron-left"></i></button>
                                <button class="btn btn-ghost" @click="shiftMonth(1)"><i class="fa-solid fa-chevron-right"></i></button>
                            </div>
                        </div>
                        <div class="cal-grid">
                            <template x-for="d in ['L','M','X','J','V','S','D']"><div class="cal-dow" x-text="d"></div></template>
                            <template x-for="day in monthDays" :key="day.key">
                                <button class="cal-day" :class="{ active: selectedDay === day.date }" :style="day.blank ? 'visibility:hidden' : ''" @click="selectedDay = day.date">
                                    <div style="font-weight:800;font-size:12px" x-text="day.n"></div>
                                    <div class="dots">
                                        <template x-for="ev in (calendar.events?.[day.date] || []).slice(0,4)" :key="ev.id">
                                            <span class="dot" :class="ev.type"></span>
                                        </template>
                                    </div>
                                </button>
                            </template>
                        </div>
                        <div style="margin-top:14px">
                            <strong x-text="selectedDay ? 'Eventos del ' + fmt(selectedDay) : 'Selecciona un día'"></strong>
                            <template x-if="!(calendar.events?.[selectedDay] || []).length"><p class="empty">Sin eventos este día.</p></template>
                            <template x-for="ev in (calendar.events?.[selectedDay] || [])" :key="ev.id">
                                <div class="feed-item" style="cursor:default">
                                    <span class="dot" :class="ev.type" style="display:inline-block;margin-right:6px"></span>
                                    <strong x-text="ev.title"></strong>
                                    <span style="color:var(--text-secondary)" x-text="ev.course ? ' · ' + ev.course : ''"></span>
                                </div>
                            </template>
                        </div>
                        <button class="btn btn-ghost" style="margin-top:10px" @click="view = 'calendar'">Ver horario completo</button>
                    </section>

                    <section class="panel">
                        <h2 class="section-title">Materias</h2>
                        <template x-if="subjects.length === 0"><p class="empty">Este estudiante aún no está inscrito en cursos.</p></template>
                        <template x-for="sub in subjects" :key="sub.id">
                            <div class="subject-row" @click="openSubject(sub.id)">
                                <div>
                                    <div style="font-weight:800" x-text="sub.name"></div>
                                    <div style="font-size:12px;color:var(--text-secondary)" x-text="sub.teacher"></div>
                                </div>
                                <div>
                                    <div style="font-size:11px;color:var(--text-tertiary)">Promedio</div>
                                    <strong x-text="sub.average ?? '—'"></strong>
                                    <span x-text="sub.trend === 'up' ? ' ↑' : (sub.trend === 'down' ? ' ↓' : ' ↔')"></span>
                                </div>
                                <div style="font-size:12px;color:var(--text-secondary)" x-text="sub.next_activity ? (sub.next_activity.title + ' · ' + fmt(sub.next_activity.date)) : 'Sin próxima actividad'"></div>
                            </div>
                        </template>
                    </section>
                </div>

                <section class="panel" x-show="view === 'home' && (summary.absence_requests || []).length">
                    <h2 class="section-title">Tus reportes de ausencia</h2>
                    <template x-for="req in (summary.absence_requests || [])" :key="req.id">
                        <div class="feed-item" style="cursor:default">
                            <strong x-text="(req.kind === 'tardy' ? 'Retraso' : 'Ausencia') + ' · ' + (req.reason || '')"></strong>
                            <div style="font-size:12px;color:var(--text-secondary)" x-text="fmt(req.start) + (req.end !== req.start ? ' – ' + fmt(req.end) : '') + ' · ' + req.status"></div>
                        </div>
                    </template>
                </section>

                <section class="panel" x-show="view === 'calendar'">
                    <div class="cal-head">
                        <h2 class="section-title" style="margin:0">Horario y calendario</h2>
                        <div>
                            <button class="btn btn-ghost" @click="shiftMonth(-1)">Mes anterior</button>
                            <button class="btn btn-ghost" @click="shiftMonth(1)">Mes siguiente</button>
                        </div>
                    </div>
                    <p class="empty" x-text="calendar.label"></p>
                    <template x-for="sub in subjects" :key="'h'+sub.id">
                        <div class="feed-item" style="cursor:default">
                            <strong x-text="sub.name"></strong>
                            <div style="font-size:13px;color:var(--text-secondary)" x-text="sub.teacher + ' · ' + (sub.grade || '')"></div>
                        </div>
                    </template>
                    <template x-for="(group, date) in calendar.events" :key="date">
                        <div>
                            <div style="font-weight:800;margin-top:12px" x-text="fmt(date)"></div>
                            <template x-for="ev in group" :key="ev.id">
                                <div class="feed-item" style="cursor:default"><span class="dot" :class="ev.type" style="display:inline-block"></span> <span x-text="ev.title + (ev.course ? ' · ' + ev.course : '')"></span></div>
                            </template>
                        </div>
                    </template>
                </section>

                <section class="panel" x-show="view === 'subjects'">
                    <h2 class="section-title">Rendimiento por materia</h2>
                    <template x-for="sub in subjects" :key="'s'+sub.id">
                        <div class="subject-row" @click="openSubject(sub.id)">
                            <div><strong x-text="sub.name"></strong><div style="font-size:12px;color:var(--text-secondary)" x-text="sub.teacher"></div></div>
                            <div><strong x-text="sub.average ?? '—'"></strong> <span x-text="trendIcon(sub.trend)"></span></div>
                            <div style="font-size:12px" x-text="sub.last_evaluation ? sub.last_evaluation.title : 'Sin evaluaciones'"></div>
                            <div style="font-size:12px" x-text="sub.next_activity ? fmt(sub.next_activity.date) : '—'"></div>
                        </div>
                    </template>
                </section>

                <section class="panel" x-show="view === 'comms'">
                    <div class="tabs">
                        <button class="tab" :class="{ active: commTab === 'announcements' }" @click="commTab = 'announcements'">📢 Anuncios</button>
                        <button class="tab" :class="{ active: commTab === 'messages' }" @click="commTab = 'messages'">💬 Mensajes</button>
                        <button class="tab" :class="{ active: commTab === 'official' }" @click="commTab = 'official'">📋 Comunicados</button>
                    </div>
                    <div x-show="commTab === 'announcements'">
                        <template x-if="announcements.length === 0"><p class="empty">No hay anuncios por ahora.</p></template>
                        <template x-for="a in announcements" :key="a.id">
                            <div class="feed-item" @click="openAnnouncement(a)">
                                <div :class="{ unread: !a.read }" style="font-weight:800" x-text="a.title"></div>
                                <div style="font-size:12px;color:var(--text-secondary)" x-text="(a.author || '') + ' · ' + fmt(a.date)"></div>
                            </div>
                        </template>
                    </div>
                    <div x-show="commTab === 'messages'">
                        <div class="panel" style="padding:12px;margin-bottom:12px;box-shadow:none">
                            <strong>Escribirle a un docente</strong>
                            <select x-model="composeCourseId">
                                <option value="">Elige la materia</option>
                                <template x-for="sub in subjects" :key="'c'+sub.id">
                                    <option :value="sub.id" x-text="sub.name + ' · ' + sub.teacher"></option>
                                </template>
                            </select>
                            <textarea rows="2" x-model="newMessage" placeholder="Hola profesor, quería consultar…"></textarea>
                            <button class="btn btn-primary" @click="messageTeacher(composeCourseId)">Enviar</button>
                        </div>
                        <template x-if="threads.length === 0"><p class="empty">Aún no hay conversaciones. Escríbele al docente arriba o desde una materia.</p></template>
                        <template x-for="t in threads" :key="t.id">
                            <div class="feed-item" @click="openThread(t.id)">
                                <div style="font-weight:800" x-text="t.teacher"></div>
                                <div style="font-size:12px;color:var(--text-secondary)" x-text="t.preview"></div>
                                <span class="badge" style="position:static" x-show="t.unread > 0" x-text="t.unread"></span>
                            </div>
                        </template>
                    </div>
                    <div x-show="commTab === 'official'">
                        <template x-for="a in announcements.filter(x => x.official)" :key="'o'+a.id">
                            <div class="feed-item" @click="openAnnouncement(a)">
                                <div style="font-weight:800" x-text="a.title"></div>
                                <div style="font-size:12px" x-show="(a.attachments || []).length">
                                    <template x-for="att in a.attachments" :key="att.url || att.name">
                                        <a :href="att.url" target="_blank" x-text="att.name || 'Adjunto'"></a>
                                    </template>
                                </div>
                            </div>
                        </template>
                        <p class="empty" x-show="announcements.filter(x => x.official).length === 0">Sin comunicados oficiales.</p>
                    </div>
                </section>

                <section class="panel" x-show="view === 'docs'">
                    <h2 class="section-title">Documentos de <span x-text="currentStudent?.name"></span></h2>
                    <p class="empty">Descarga o previsualiza el boletín (las mismas notas que ve el docente y el director) y la constancia de estudio.</p>
                    <div style="display:flex;flex-wrap:wrap;gap:10px;margin-top:12px">
                        <button class="btn btn-primary" @click="openBoletin()">Ver boletín</button>
                        <a class="btn btn-ghost" :href="boletinUrl" style="text-decoration:none">Descargar PDF</a>
                        <a class="btn btn-ghost" :href="constanciaUrl" style="text-decoration:none">Constancia de estudio</a>
                    </div>
                    <div x-show="boletin" style="margin-top:18px">
                        <p><strong>Promedio global:</strong> <span x-text="(boletin?.globalAverage ?? 0) + '%'"></span></p>
                        <template x-for="course in (boletin?.courses || [])" :key="course.course_id || course.course_name">
                            <div class="feed-item" style="cursor:default">
                                <strong x-text="course.course_name"></strong>
                                <div style="font-size:12px;color:var(--text-secondary)" x-text="course.teacher_name + ' · ' + course.promedio + '%'"></div>
                                <template x-for="act in (course.activities || [])" :key="act.title">
                                    <div style="font-size:13px;padding:4px 0" x-text="act.title + (act.has_score ? (' · ' + act.score + '/' + act.max_score) : ' · pendiente')"></div>
                                </template>
                            </div>
                        </template>
                    </div>
                </section>
            </div>
        </main>
    </div>

    <div class="fab" x-show="students.length > 0">
        <button class="fab-btn" @click="openAbsence = true"><i class="fa-solid fa-calendar-xmark"></i> Reportar ausencia</button>
        <button class="fab-btn" @click="openBoletin()"><i class="fa-solid fa-file-arrow-down"></i> Ver boletín</button>
        <a class="fab-btn" :href="constanciaUrl" style="text-decoration:none"><i class="fa-solid fa-stamp"></i> Ver constancias</a>
        <button class="fab-btn" @click="openProfile = true"><i class="fa-solid fa-pen"></i> Editar perfil</button>
    </div>

    <div class="overlay" x-show="openAbsence" x-cloak @click.self="openAbsence = false">
        <div class="modal">
            <h3>Reportar ausencia o retraso</h3>
            <label>Estudiante</label>
            <select x-model="absence.student_id">
                <template x-for="s in students" :key="'ab'+s.id"><option :value="s.id" x-text="s.name"></option></template>
            </select>
            <label>Tipo</label>
            <select x-model="absence.kind">
                <option value="absence">Ausencia</option>
                <option value="tardy">Retraso</option>
            </select>
            <label>Motivo</label>
            <select x-model="absence.reason_id">
                <template x-for="r in reasons" :key="r.id"><option :value="r.id" x-text="r.label"></option></template>
            </select>
            <p class="empty" x-show="!reasons.length">No hay motivos configurados. Recarga o avisa al colegio.</p>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
                <div><label>Desde</label><input type="date" x-model="absence.start_date"></div>
                <div><label>Hasta</label><input type="date" x-model="absence.end_date"></div>
            </div>
            <label>Comentario</label>
            <textarea rows="3" x-model="absence.comment" placeholder="Opcional"></textarea>
            <p x-text="absenceError" style="color:#fb7185"></p>
            <div style="display:flex;gap:8px;justify-content:flex-end">
                <button class="btn btn-ghost" @click="openAbsence = false">Cancelar</button>
                <button class="btn btn-primary" @click="submitAbsence()">Enviar al colegio</button>
            </div>
        </div>
    </div>

    <div class="overlay" x-show="subjectModal" x-cloak @click.self="subjectModal = null">
        <div class="modal">
            <h3 x-text="subjectModal?.name"></h3>
            <p class="empty" x-text="(subjectModal?.teacher || '') + ' · Promedio ' + (subjectModal?.average ?? '—')"></p>
            <div style="display:flex;gap:4px;align-items:flex-end;height:90px;margin:12px 0">
                <template x-for="h in (subjectModal?.history || [])" :key="h.label">
                    <div :title="h.label + ': ' + h.score" :style="'flex:1;background:var(--nova-gradient);border-radius:8px 8px 0 0;height:' + Math.max(8, (h.score / (h.max_score || 20)) * 90) + 'px'"></div>
                </template>
            </div>
            <template x-for="item in (subjectModal?.items || [])" :key="item.id">
                <div class="feed-item" style="cursor:default">
                    <strong x-text="item.title"></strong>
                    <span x-text="item.score != null ? (' · ' + item.score + '/' + item.max_score) : ' · Pendiente'"></span>
                    <div style="font-size:12px;color:var(--text-secondary)" x-text="item.feedback || fmt(item.date)"></div>
                </div>
            </template>
            <div style="margin-top:12px">
                <textarea rows="2" x-model="newMessage" placeholder="Escribirle al docente…"></textarea>
                <button class="btn btn-primary" @click="messageTeacher(subjectModal.id)">Enviar mensaje</button>
            </div>
        </div>
    </div>

    <div class="overlay" x-show="announcementModal" x-cloak @click.self="announcementModal = null">
        <div class="modal">
            <h3 x-text="announcementModal?.title"></h3>
            <p class="empty" x-text="announcementModal?.author"></p>
            <p style="white-space:pre-wrap" x-text="announcementModal?.body"></p>
            <button class="btn btn-ghost" @click="announcementModal = null">Cerrar</button>
        </div>
    </div>

    <div class="overlay" x-show="chat" x-cloak @click.self="chat = null">
        <div class="modal">
            <h3 x-text="chat?.teacher"></h3>
            <div style="max-height:46vh;overflow:auto">
                <template x-for="m in (chat?.messages || [])" :key="m.id">
                    <div class="chat-bubble" :class="m.mine ? 'mine' : 'theirs'" x-text="m.body"></div>
                </template>
            </div>
            <div style="display:flex;gap:8px;margin-top:10px">
                <input x-model="chatBody" @keydown.enter="sendChat()" placeholder="Escribe un mensaje">
                <button class="btn btn-primary" @click="sendChat()">Enviar</button>
            </div>
        </div>
    </div>

    <div class="overlay" x-show="openProfile" x-cloak @click.self="openProfile = false">
        <div class="modal">
            <h3>Editar perfil</h3>
            <label>Nombre</label><input x-model="profile.name">
            <label>Teléfono</label><input x-model="profile.phone">
            <label>Dirección</label><input x-model="profile.address">
            <label>Número de emergencia</label><input x-model="profile.emergency">
            <p x-text="profileMsg" style="color:var(--nova-success)"></p>
            <div style="display:flex;gap:8px;justify-content:flex-end">
                <button class="btn btn-ghost" @click="openProfile = false">Cerrar</button>
                <button class="btn btn-primary" @click="saveProfile()">Guardar</button>
            </div>
        </div>
    </div>

    <div x-show="toast" x-cloak class="fab" style="left:24px;right:auto;bottom:24px;z-index:60">
        <div class="fab-btn" style="max-width:280px" x-text="toast"></div>
    </div>
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('familyHub', () => ({
                students: @json($students),
                reasons: @json($reasons),
                studentId: @json($students->first()['id'] ?? null),
                view: 'home',
                sidebarOpen: false,
                isDark: document.documentElement.classList.contains('dark'),
                summary: {},
                calendar: { events: {}, month: '{{ now()->format('Y-m') }}', label: '' },
                subjects: [],
                announcements: [],
                threads: [],
                notifications: [],
                unreadNotif: 0,
                selectedDay: '{{ now()->toDateString() }}',
                commTab: 'announcements',
                openAbsence: false,
                openProfile: false,
                showNotif: false,
                subjectModal: null,
                announcementModal: null,
                chat: null,
                chatBody: '',
                newMessage: '',
                composeCourseId: '',
                boletin: null,
                toast: '',
                absenceError: '',
                profileMsg: '',
                profile: {
                    name: @json($parent['name']),
                    phone: @json($parent['phone']),
                    address: @json($parent['address']),
                    emergency: @json($parent['emergency']),
                },
                absence: {
                    student_id: @json($students->first()['id'] ?? null),
                    kind: 'absence',
                    reason_id: @json($reasons->first()['id'] ?? null),
                    start_date: '{{ now()->toDateString() }}',
                    end_date: '{{ now()->toDateString() }}',
                    comment: '',
                },
                csrf() { return document.querySelector('meta[name=csrf-token]')?.content || ''; },
                get currentStudent() { return this.students.find(s => String(s.id) === String(this.studentId)); },
                get unreadAnnouncements() { return this.announcements.filter(a => !a.read).length; },
                get boletinUrl() { return this.studentId ? `/representante/boletin/${this.studentId}` : '#'; },
                get constanciaUrl() { return this.studentId ? `/representante/constancia/${this.studentId}` : '#'; },
                get monthDays() {
                    const [y, m] = (this.calendar.month || '{{ now()->format('Y-m') }}').split('-').map(Number);
                    const first = new Date(y, m - 1, 1);
                    const offset = (first.getDay() + 6) % 7;
                    const count = new Date(y, m, 0).getDate();
                    const days = [];
                    for (let i = 0; i < offset; i++) days.push({ key: 'b'+i, blank: true, n: '', date: '' });
                    for (let n = 1; n <= count; n++) {
                        const date = `${y}-${String(m).padStart(2,'0')}-${String(n).padStart(2,'0')}`;
                        days.push({ key: date, n, date, blank: false });
                    }
                    return days;
                },
                fmt(value) {
                    if (!value) return '';
                    const d = new Date(value);
                    if (Number.isNaN(d.getTime())) return String(value).slice(0, 10);
                    return d.toLocaleDateString('es-VE', { day: '2-digit', month: 'short' });
                },
                trendIcon(t) { return t === 'up' ? '↑' : (t === 'down' ? '↓' : '↔'); },
                async init() {
                    if (!this.studentId) return;
                    await this.refreshAll();
                    setInterval(() => this.refreshAll(true), 30000);
                },
                async refreshAll(silent = false) {
                    if (!this.studentId) return;
                    const id = this.studentId;
                    const month = this.calendar.month;
                    const [sum, cal, subs, anns, msgs, notif] = await Promise.all([
                        fetch(`/representante/api/${id}/resumen`, { headers: { Accept: 'application/json' } }).then(r => r.json()),
                        fetch(`/representante/api/${id}/calendario?month=${month}`, { headers: { Accept: 'application/json' } }).then(r => r.json()),
                        fetch(`/representante/api/${id}/materias`, { headers: { Accept: 'application/json' } }).then(r => r.json()),
                        fetch(`/representante/api/anuncios?estudiante_id=${id}`, { headers: { Accept: 'application/json' } }).then(r => r.json()),
                        fetch(`/representante/api/mensajes?estudiante_id=${id}`, { headers: { Accept: 'application/json' } }).then(r => r.json()),
                        fetch(`/representante/api/notificaciones`, { headers: { Accept: 'application/json' } }).then(r => r.json()),
                    ]);
                    this.summary = sum.summary || {};
                    this.calendar = cal.calendar || this.calendar;
                    this.subjects = subs.subjects || [];
                    this.announcements = anns.announcements || [];
                    this.threads = msgs.threads || [];
                    this.notifications = notif.items || [];
                    this.unreadNotif = notif.unread || 0;
                    this.absence.student_id = id;
                },
                async shiftMonth(delta) {
                    const [y, m] = this.calendar.month.split('-').map(Number);
                    const d = new Date(y, m - 1 + delta, 1);
                    this.calendar.month = `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}`;
                    const cal = await fetch(`/representante/api/${this.studentId}/calendario?month=${this.calendar.month}`, { headers: { Accept: 'application/json' } }).then(r => r.json());
                    this.calendar = cal.calendar || this.calendar;
                },
                async openSubject(id) {
                    const json = await fetch(`/representante/api/${this.studentId}/materia/${id}`, { headers: { Accept: 'application/json' } }).then(r => r.json());
                    this.subjectModal = json.subject;
                    this.newMessage = '';
                },
                async openAnnouncement(a) {
                    this.announcementModal = a;
                    await fetch(`/representante/api/anuncios/${a.id}/leer`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': this.csrf() },
                        body: JSON.stringify({ estudiante_id: this.studentId }),
                    });
                    a.read = true;
                },
                async openThread(id) {
                    const json = await fetch(`/representante/api/mensajes/${id}?estudiante_id=${this.studentId}`, { headers: { Accept: 'application/json' } }).then(r => r.json());
                    this.chat = json.thread;
                    this.chatBody = '';
                },
                async sendChat() {
                    if (!this.chatBody.trim() || !this.chat) return;
                    await fetch(`/representante/api/mensajes/${this.chat.id}`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': this.csrf() },
                        body: JSON.stringify({ estudiante_id: this.studentId, body: this.chatBody }),
                    });
                    this.chatBody = '';
                    this.showToast('Mensaje enviado.');
                    await this.openThread(this.chat.id);
                },
                async messageTeacher(courseId) {
                    if (!courseId) { this.showToast('Elige una materia.'); return; }
                    if (!this.newMessage.trim()) { this.showToast('Escribe un mensaje.'); return; }
                    const res = await fetch(`/representante/api/mensajes`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': this.csrf() },
                        body: JSON.stringify({ estudiante_id: this.studentId, course_id: courseId, body: this.newMessage }),
                    });
                    const json = await res.json().catch(() => ({}));
                    if (!res.ok) {
                        this.showToast(json.message || 'No se pudo enviar el mensaje.');
                        return;
                    }
                    this.newMessage = '';
                    this.composeCourseId = '';
                    this.subjectModal = null;
                    this.view = 'comms';
                    this.commTab = 'messages';
                    this.showToast('Mensaje enviado al docente.');
                    await this.refreshAll();
                    if (json.thread_id) await this.openThread(json.thread_id);
                },
                async submitAbsence() {
                    this.absenceError = '';
                    if (!this.absence.reason_id) {
                        this.absenceError = 'No hay motivos cargados. Recarga la página o avisa al colegio.';
                        return;
                    }
                    const res = await fetch(`/representante/api/ausencia`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': this.csrf() },
                        body: JSON.stringify(this.absence),
                    });
                    const json = await res.json().catch(() => ({}));
                    if (!res.ok) {
                        const firstError = json.errors ? Object.values(json.errors).flat()[0] : null;
                        this.absenceError = firstError || json.message || json.error || 'No se pudo enviar.';
                        return;
                    }
                    this.openAbsence = false;
                    this.absence.comment = '';
                    this.showToast('Ausencia reportada. El docente ya fue notificado.');
                    await this.refreshAll();
                },
                async openBoletin() {
                    this.view = 'docs';
                    const json = await fetch(`/representante/api/${this.studentId}/boletin`, { headers: { Accept: 'application/json' } }).then(r => r.json());
                    this.boletin = json;
                    this.showToast('Boletín actualizado con las notas del docente.');
                },
                showToast(text) {
                    this.toast = text;
                    setTimeout(() => { if (this.toast === text) this.toast = ''; }, 3200);
                },
                async saveProfile() {
                    const res = await fetch(`/representante/api/perfil`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': this.csrf() },
                        body: JSON.stringify(this.profile),
                    });
                    const json = await res.json();
                    this.profileMsg = json.message || 'Guardado.';
                    this.showToast('Perfil actualizado.');
                },
                toggleNotif() { this.showNotif = !this.showNotif; },
                async markNotifRead() {
                    await fetch(`/representante/api/notificaciones/leer`, { method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': this.csrf() } });
                    await this.refreshAll();
                },
                toggleTheme() {
                    document.documentElement.classList.toggle('dark');
                    this.isDark = document.documentElement.classList.contains('dark');
                    localStorage.setItem('nova-theme', this.isDark ? 'dark' : 'light');
                },
            }));
        });
    </script>
</body>
</html>
