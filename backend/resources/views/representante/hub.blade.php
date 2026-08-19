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
            min-height: 100dvh;
            font-family: -apple-system, BlinkMacSystemFont, 'Manrope', Inter, system-ui, sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            -webkit-font-smoothing: antialiased;
            overflow-x: hidden;
        }
        .fam-shell { display: grid; grid-template-columns: 280px 1fr; min-height: 100vh; min-height: 100dvh; }
        .fam-sidebar {
            background: var(--bg-sidebar, var(--bg-secondary));
            border-right: 1px solid var(--nova-glass-border);
            padding: 22px 16px;
            display: flex; flex-direction: column; gap: 8px;
        }
        .fam-overlay {
            display: none;
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
        .cal-day.today { box-shadow: inset 0 0 0 1.5px var(--nova-violet); }
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
        .feed-item {
            padding: 12px 0;
            border-bottom: 1px solid var(--nova-glass-border);
            cursor: pointer;
        }
        .event-card {
            padding: 12px 14px;
            border: 1px solid var(--nova-glass-border);
            border-radius: 14px;
            background: color-mix(in oklab, var(--bg-card) 86%, white 14%);
            margin-top: 10px;
        }
        .event-card:hover { border-color: color-mix(in oklab, var(--nova-violet) 45%, var(--nova-glass-border)); }
        .event-head { display: flex; align-items: center; justify-content: space-between; gap: 10px; }
        .event-meta { font-size: 12px; color: var(--text-secondary); margin-top: 2px; }
        .event-desc { margin-top: 8px; font-size: 13px; line-height: 1.45; color: var(--text-secondary); }
        .event-pill {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 3px 10px;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .02em;
            border: 1px solid var(--nova-glass-border);
            background: var(--nova-glass);
            color: var(--text-primary);
        }
        .unread { color: var(--nova-violet); }
        .fab {
            position: fixed;
            right: 24px;
            bottom: 24px;
            z-index: 40;
            display: flex;
            flex-direction: column;
            gap: 10px;
            align-items: flex-end;
            width: min(300px, 90vw);
        }
        .fab-toggle {
            display: none;
        }
        .fab-btn {
            width: 100%;
            display: inline-flex;
            align-items: center;
            justify-content: flex-start;
            gap: 10px;
            border: 1px solid color-mix(in oklab, var(--nova-violet) 45%, transparent);
            border-radius: 16px;
            padding: 12px 14px;
            font-weight: 800;
            color: #fff;
            background: linear-gradient(135deg, #7c3aed, #ec4899 52%, #22d3ee);
            box-shadow: 0 16px 36px rgba(124, 58, 237, .35);
            cursor: pointer;
            transition: transform .2s ease, box-shadow .2s ease;
        }
        .fab-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 20px 44px rgba(124, 58, 237, .4);
        }
        .fab-btn i {
            width: 20px;
            text-align: center;
        }
        .overlay { position: fixed; inset: 0; background: rgba(8,6,20,.55); z-index: 50; display: grid; place-items: center; padding: max(12px, env(safe-area-inset-top)) 16px max(16px, env(safe-area-inset-bottom)); overflow-y: auto; }
        .modal { width: min(640px, 100%); background: var(--bg-secondary); border: 1px solid var(--nova-glass-border); border-radius: 28px; padding: 22px; max-height: min(86dvh, 86vh); overflow: auto; -webkit-overflow-scrolling: touch; }
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
        .desktop-day-events { display: block; }
        input, select, textarea { font-size: 16px; }
        @media (max-width: 1100px) {
            .kpi-grid, .split, .subject-row { grid-template-columns: 1fr 1fr; }
            .subject-row { display: flex; flex-direction: column; align-items: flex-start; }
        }
        @media (max-width: 860px) {
            .fam-shell { grid-template-columns: 1fr; }
            .fam-overlay {
                display: block;
                position: fixed;
                inset: 0;
                background: rgba(8,6,20,.5);
                z-index: 28;
            }
            .fam-sidebar {
                display: flex;
                position: fixed;
                inset: 0 auto 0 0;
                width: min(280px, 86vw);
                z-index: 30;
                transform: translateX(-110%);
                transition: transform .22s ease;
                padding-top: max(18px, env(safe-area-inset-top));
                overflow-y: auto;
            }
            .fam-sidebar.open { transform: translateX(0); }
            .mobile-bar {
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: max(10px, env(safe-area-inset-top)) 16px 10px;
                position: sticky;
                top: 0;
                z-index: 20;
                background: color-mix(in srgb, var(--bg-primary) 88%, transparent);
                backdrop-filter: blur(12px);
                border-bottom: 1px solid var(--nova-glass-border);
            }
            .kpi-grid { grid-template-columns: 1fr 1fr; gap: 10px; }
            .kpi-value { font-size: 24px; }
            .fam-main { padding: 12px 16px calc(108px + env(safe-area-inset-bottom)); }
            .topbar { gap: 10px; margin-bottom: 14px; }
            .student-select { min-width: 0; width: 100%; }
            .parent-chip { display: none; }
            .topbar { position: relative; }
            .notif-panel { right: 12px !important; left: 12px; width: auto !important; }
            .cal-day { min-height: 42px; padding: 4px 2px; border-radius: 10px; text-align: center; }
            .cal-day .dots { justify-content: center; }
            .desktop-day-events { display: none; }
            .split { grid-template-columns: 1fr; }
            .fab {
                right: 14px;
                bottom: calc(14px + env(safe-area-inset-bottom));
                width: auto;
                align-items: flex-end;
            }
            .fab-actions {
                display: none;
                flex-direction: column;
                gap: 8px;
                width: min(240px, calc(100vw - 40px));
            }
            .fab.open .fab-actions { display: flex; }
            .fab-toggle {
                display: inline-flex;
                width: 56px;
                height: 56px;
                border-radius: 18px;
                border: 0;
                align-items: center;
                justify-content: center;
                color: #fff;
                background: var(--nova-gradient);
                box-shadow: 0 16px 36px rgba(124, 58, 237, .4);
                cursor: pointer;
            }
            .fab-btn { font-size: 13px; padding: 11px 12px; }
            .overlay { align-items: end; }
            .modal { border-radius: 24px 24px 16px 16px; padding: 18px 16px; }
        }
    </style>
</head>
<body x-data="familyHub">
    <div class="mobile-bar">
        <button class="icon-btn" @click="sidebarOpen = !sidebarOpen" aria-label="Menú"><i class="fa-solid fa-bars"></i></button>
        <strong>AulaSync Familia</strong>
        <button class="icon-btn" @click="toggleTheme()"><i class="fa-solid" :class="isDark ? 'fa-sun' : 'fa-moon'"></i></button>
    </div>

    <div class="fam-overlay" x-show="sidebarOpen" x-cloak @click="sidebarOpen = false"></div>

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
            <button class="nav-item" :class="{ active: view === 'docs' }" @click="view = 'docs'; sidebarOpen = false; loadBoletasOficiales()"><i class="fa-solid fa-folder-open"></i> Documentos</button>
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
                    <div class="student-select parent-chip" style="min-width:auto;padding:8px 12px 8px 8px">
                        <div class="avatar" style="width:34px;height:34px">{{ $parent['initials'] }}</div>
                        <div>
                            <div style="font-weight:800;font-size:13px">{{ $parent['name'] }}</div>
                            <div style="font-size:11px;color:var(--text-secondary)">Representante</div>
                        </div>
                    </div>
                </div>
            </div>

            <div x-show="showNotif" @click.outside="showNotif = false" class="panel notif-panel" style="position:absolute;right:28px;z-index:20;width:min(360px,90vw)">
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
                                <button class="cal-day" :class="{ active: selectedDay === day.date, today: isToday(day.date) }" :style="day.blank ? 'visibility:hidden' : ''" @click="pickDay(day.date)">
                                    <div style="font-weight:800;font-size:12px" x-text="day.n"></div>
                                    <div class="dots">
                                        <template x-for="ev in (calendar.events?.[day.date] || []).slice(0,3)" :key="ev.id">
                                            <span class="dot" :class="ev.type"></span>
                                        </template>
                                    </div>
                                </button>
                            </template>
                        </div>
                        <div class="desktop-day-events" style="margin-top:14px">
                            <strong x-text="selectedDay ? 'Eventos del ' + fmt(selectedDay) : 'Selecciona un día'"></strong>
                            <template x-if="!(calendar.events?.[selectedDay] || []).length"><p class="empty">Sin eventos este día.</p></template>
                            <template x-for="ev in (calendar.events?.[selectedDay] || [])" :key="ev.id">
                                <div class="event-card" @click="openEvent(ev)">
                                    <div class="event-head">
                                        <div>
                                            <div style="display:flex;align-items:center;gap:8px;">
                                                <span class="dot" :class="ev.type" style="display:inline-block"></span>
                                                <strong x-text="ev.title"></strong>
                                            </div>
                                            <div class="event-meta" x-text="[ev.type_label, ev.course].filter(Boolean).join(' · ')"></div>
                                        </div>
                                        <span class="event-pill">Ver detalle</span>
                                    </div>
                                </div>
                            </template>
                            <div class="event-card" x-show="selectedEvent" x-cloak style="margin-top:14px;border-color:color-mix(in oklab, var(--nova-violet) 35%, var(--nova-glass-border));">
                                <div style="font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.08em;color:var(--nova-cyan);margin-bottom:6px;">Detalle de la clase/evento</div>
                                <h3 style="margin:0;font-size:17px;font-weight:900;" x-text="selectedEvent?.title"></h3>
                                <div class="event-meta" x-text="[selectedEvent?.type_label, selectedEvent?.course].filter(Boolean).join(' · ')"></div>
                                <p class="event-desc" x-text="selectedEvent?.description || 'Sin descripción por ahora.'"></p>
                            </div>
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
                        <h2 class="section-title" style="margin:0" x-text="calendar.label || 'Horario y calendario'"></h2>
                        <div style="display:flex;gap:6px">
                            <button class="btn btn-ghost" @click="shiftMonth(-1)" aria-label="Mes anterior"><i class="fa-solid fa-chevron-left"></i></button>
                            <button class="btn btn-ghost" @click="shiftMonth(1)" aria-label="Mes siguiente"><i class="fa-solid fa-chevron-right"></i></button>
                        </div>
                    </div>
                    <div class="cal-grid" style="margin-top:8px">
                        <template x-for="d in ['L','M','X','J','V','S','D']"><div class="cal-dow" x-text="d"></div></template>
                        <template x-for="day in monthDays" :key="'c'+day.key">
                            <button class="cal-day" :class="{ active: selectedDay === day.date, today: isToday(day.date) }" :style="day.blank ? 'visibility:hidden' : ''" @click="pickDay(day.date)">
                                <div style="font-weight:800;font-size:12px" x-text="day.n"></div>
                                <div class="dots">
                                    <template x-for="ev in (calendar.events?.[day.date] || []).slice(0,3)" :key="'c'+ev.id">
                                        <span class="dot" :class="ev.type"></span>
                                    </template>
                                </div>
                            </button>
                        </template>
                    </div>
                    <div class="desktop-day-events" style="margin-top:16px">
                        <strong x-text="selectedDay ? 'Eventos del ' + fmt(selectedDay) : 'Selecciona un día'"></strong>
                        <template x-if="!(calendar.events?.[selectedDay] || []).length"><p class="empty">Sin eventos este día.</p></template>
                        <template x-for="ev in (calendar.events?.[selectedDay] || [])" :key="'list'+ev.id">
                            <div class="event-card" @click="openEvent(ev)">
                                <div class="event-head">
                                    <div>
                                        <div style="display:flex;align-items:center;gap:8px;">
                                            <span class="dot" :class="ev.type" style="display:inline-block"></span>
                                            <strong x-text="ev.title"></strong>
                                        </div>
                                        <div class="event-meta" x-text="[ev.type_label, ev.course].filter(Boolean).join(' · ')"></div>
                                    </div>
                                    <span class="event-pill">Ver detalle</span>
                                </div>
                            </div>
                        </template>
                    </div>
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

                    {{-- Boletas oficiales publicadas --}}
                    <div style="margin-bottom:20px">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">
                            <strong style="font-size:15px">Boletas oficiales</strong>
                            <button @click="loadBoletasOficiales()" style="font-size:12px;color:var(--accent);background:none;border:none;cursor:pointer">
                                <i class="fa-solid fa-rotate-right"></i> Actualizar
                            </button>
                        </div>

                        <div x-show="loadingBoletas" style="text-align:center;padding:20px;color:var(--text-secondary);font-size:13px">
                            <i class="fa-solid fa-circle-notch fa-spin"></i> Cargando…
                        </div>

                        <div x-show="!loadingBoletas && boletasOficiales.length === 0" style="padding:18px;background:var(--bg-secondary);border-radius:12px;text-align:center;color:var(--text-secondary);font-size:13px">
                            <i class="fa-solid fa-file-circle-question" style="font-size:24px;opacity:.4;display:block;margin-bottom:8px"></i>
                            <p>El director aún no ha publicado boletas oficiales para este período.</p>
                            <p style="margin-top:4px;font-size:12px">Mientras tanto, puedes ver el boletín con las notas actuales del docente.</p>
                        </div>

                        <template x-for="boleta in boletasOficiales" :key="boleta.id">
                            <div style="background:var(--bg-secondary);border-radius:14px;padding:14px 16px;margin-bottom:10px;border:1px solid rgba(124,58,237,.15)">
                                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">
                                    <div>
                                        <strong x-text="boleta.period?.name ?? 'Período'"></strong>
                                        <span style="margin-left:8px;font-size:11px;padding:2px 8px;border-radius:9999px;background:rgba(52,211,153,.15);color:#34d399;font-weight:700">PUBLICADA</span>
                                    </div>
                                    <div style="font-size:12px;color:var(--text-secondary)" x-text="boleta.published_at ?? ''"></div>
                                </div>
                                <div style="font-size:13px;color:var(--text-secondary);margin-bottom:10px">
                                    Promedio general:
                                    <strong :style="'color:' + gradeColorHex(boleta.global_average)" x-text="boleta.global_average + '%'"></strong>
                                </div>
                                <div style="overflow-x:auto">
                                    <table style="width:100%;border-collapse:collapse;font-size:12px">
                                        <thead>
                                            <tr style="color:var(--text-secondary)">
                                                <th style="text-align:left;padding:4px 8px;font-size:10px;text-transform:uppercase;letter-spacing:.06em">Asignatura</th>
                                                <th style="text-align:center;padding:4px 8px;font-size:10px;">Nota</th>
                                                <th style="text-align:center;padding:4px 8px;font-size:10px;">Literal</th>
                                                <th style="padding:4px 8px;font-size:10px;">Obs.</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <template x-for="g in (boleta.grades ?? [])" :key="g.course_id">
                                                <tr style="border-top:1px solid rgba(255,255,255,.05)">
                                                    <td style="padding:5px 8px;font-weight:600" x-text="g.course_name"></td>
                                                    <td style="text-align:center;font-weight:900;padding:5px 8px" :style="'color:' + gradeColorHex(g.grade)" x-text="g.grade + '%'"></td>
                                                    <td style="text-align:center;font-weight:900;padding:5px 8px" :style="'color:' + gradeColorHex(g.grade)" x-text="g.letter_grade ?? '—'"></td>
                                                    <td style="padding:5px 8px;color:var(--text-secondary);font-size:11px" x-text="g.teacher_observations || '—'"></td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>
                                <div x-show="boleta.observations" style="margin-top:10px;padding:8px 10px;background:rgba(251,191,36,.08);border-radius:8px;font-size:12px;color:#fbbf24">
                                    <i class="fa-solid fa-comment-dots"></i>
                                    <span x-text="boleta.observations"></span>
                                </div>
                                <div style="margin-top:10px;display:flex;gap:8px">
                                    <a :href="'/director/api/report-cards/' + boleta.id + '/pdf'"
                                       style="font-size:12px;font-weight:700;color:#f87171;text-decoration:none;display:flex;align-items:center;gap:4px">
                                        <i class="fa-solid fa-file-pdf"></i> Descargar PDF
                                    </a>
                                </div>
                            </div>
                        </template>
                    </div>

                    {{-- Separator --}}
                    <div style="border-top:1px solid rgba(255,255,255,.08);margin:16px 0;"></div>

                    {{-- Live report card --}}
                    <p style="font-size:13px;font-weight:700;margin-bottom:10px">Boletín en tiempo real (notas actuales)</p>
                    <p class="empty" style="margin-bottom:12px">Notas que han cargado los docentes hasta hoy.</p>
                    <div style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:14px">
                        <button class="btn btn-primary" @click="openBoletin()">Ver boletín</button>
                        <a class="btn btn-ghost" :href="boletinUrl" style="text-decoration:none">Descargar PDF</a>
                        <a class="btn btn-ghost" :href="constanciaUrl" style="text-decoration:none">Constancia de estudio</a>
                    </div>
                    <div x-show="boletin" style="margin-top:8px">
                        <p style="font-size:13px"><strong>Promedio global:</strong> <span x-text="(boletin?.globalAverage ?? 0) + '%'"></span></p>
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

    <div class="fab" :class="{ open: fabOpen }" x-show="students.length > 0">
        <div class="fab-actions">
        <button class="fab-btn" @click="openAbsence = true; fabOpen = false" title="Notificar una ausencia o retraso al colegio">
            <i class="fa-solid fa-calendar-xmark"></i>
            <span>Reportar ausencia</span>
        </button>
        <button class="fab-btn" @click="openBoletin(); fabOpen = false" title="Ver notas y rendimiento actual">
            <i class="fa-solid fa-file-arrow-down"></i>
            <span>Ver boletín</span>
        </button>
        <a class="fab-btn" :href="constanciaUrl" style="text-decoration:none" title="Descargar constancia de estudio">
            <i class="fa-solid fa-stamp"></i>
            <span>Ver constancias</span>
        </a>
        <button class="fab-btn" @click="openProfile = true; fabOpen = false" title="Actualizar datos del representante">
            <i class="fa-solid fa-pen"></i>
            <span>Editar perfil</span>
        </button>
        </div>
        <button type="button" class="fab-toggle" @click="fabOpen = !fabOpen" :aria-expanded="fabOpen" aria-label="Acciones rápidas">
            <i class="fa-solid" :class="fabOpen ? 'fa-xmark' : 'fa-plus'"></i>
        </button>
    </div>

    <div class="overlay" x-show="daySheetOpen" x-cloak @click.self="daySheetOpen = false" @keydown.escape.window="daySheetOpen = false">
        <div class="modal" style="max-width:480px">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;margin-bottom:8px">
                <div>
                    <p style="margin:0 0 4px;font-size:11px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:var(--nova-violet)">Pendientes del día</p>
                    <h3 style="margin:0;text-transform:capitalize" x-text="selectedDay ? fmt(selectedDay) : 'Día'"></h3>
                </div>
                <button class="icon-btn" @click="daySheetOpen = false" aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <template x-if="!(calendar.events?.[selectedDay] || []).length"><p class="empty">Sin eventos este día.</p></template>
            <template x-for="ev in (calendar.events?.[selectedDay] || [])" :key="'sheet'+ev.id">
                <div class="event-card" @click="openEvent(ev); daySheetOpen = false">
                    <div class="event-head">
                        <div>
                            <div style="display:flex;align-items:center;gap:8px;">
                                <span class="dot" :class="ev.type" style="display:inline-block"></span>
                                <strong x-text="ev.title"></strong>
                            </div>
                            <div class="event-meta" x-text="[ev.type_label, ev.course].filter(Boolean).join(' · ')"></div>
                        </div>
                        <span class="event-pill">Ver</span>
                    </div>
                    <div class="event-desc" x-text="preview(ev.description, 160)"></div>
                </div>
            </template>
        </div>
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

            <div x-show="subjectModal?.attendance" style="margin-top:16px;padding:12px;border-radius:12px;border:1px solid var(--nova-glass-border);background:var(--bg-secondary)">
                <div style="font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.06em;color:var(--nova-cyan);margin-bottom:6px">Asistencia en esta materia</div>
                <div style="font-size:20px;font-weight:900" x-text="(subjectModal?.attendance?.percentage != null ? subjectModal.attendance.percentage + '%' : 'Sin registros')"></div>
                <div style="font-size:12px;color:var(--text-secondary)" x-text="`${subjectModal?.attendance?.present ?? 0} presentes · ${subjectModal?.attendance?.tardy ?? 0} tarde · ${subjectModal?.attendance?.absent ?? 0} ausentes`"></div>
            </div>

            <div x-show="(subjectModal?.evaluation_plan || []).length > 0" style="margin-top:16px">
                <div style="font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.06em;color:var(--nova-cyan);margin-bottom:8px">Plan de evaluación</div>
                <template x-for="unit in (subjectModal?.evaluation_plan || [])" :key="unit.unit_name + unit.weight_percentage + (unit.due_date || '')">
                    <div class="feed-item" style="cursor:default">
                        <strong x-text="unit.unit_name"></strong>
                        <span x-text="' · ' + unit.assessment_type + ' · ' + (unit.category === 'formative' ? 'Formativa' : 'Sumativa')"></span>
                        <div style="font-size:12px;color:var(--text-secondary)" x-text="`Peso: ${unit.weight_percentage}%` + (unit.due_date ? ' · ' + fmt(unit.due_date) : '')"></div>
                    </div>
                </template>
            </div>

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
                selectedEvent: null,
                daySheetOpen: false,
                fabOpen: false,
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
                boletasOficiales: [],
                loadingBoletas: false,
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
                    this.ensureSelectedDay();
                },
                async shiftMonth(delta) {
                    const [y, m] = this.calendar.month.split('-').map(Number);
                    const d = new Date(y, m - 1 + delta, 1);
                    this.calendar.month = `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}`;
                    const cal = await fetch(`/representante/api/${this.studentId}/calendario?month=${this.calendar.month}`, { headers: { Accept: 'application/json' } }).then(r => r.json());
                    this.calendar = cal.calendar || this.calendar;
                    this.ensureSelectedDay();
                },
                ensureSelectedDay() {
                    const days = Object.keys(this.calendar.events || {}).sort();
                    if (!this.selectedDay || !this.calendar.events?.[this.selectedDay]) {
                        this.selectedDay = days[0] || this.selectedDay;
                    }
                    const list = this.calendar.events?.[this.selectedDay] || [];
                    this.selectedEvent = list.length ? list[0] : null;
                },
                pickDay(date) {
                    if (!date) return;
                    this.selectedDay = date;
                    const list = this.calendar.events?.[date] || [];
                    this.selectedEvent = list.length ? list[0] : null;
                    if (window.matchMedia('(max-width: 860px)').matches) {
                        this.daySheetOpen = true;
                    }
                },
                isToday(date) {
                    if (!date) return false;
                    return date === '{{ now()->toDateString() }}';
                },
                openEvent(ev) {
                    this.selectedEvent = ev || null;
                },
                preview(text, max = 140) {
                    const raw = String(text || '').trim();
                    if (!raw) return 'Sin descripción por ahora.';
                    return raw.length > max ? raw.slice(0, max - 1) + '…' : raw;
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
                    await this.loadBoletasOficiales();
                    this.showToast('Boletín actualizado con las notas del docente.');
                },
                async loadBoletasOficiales() {
                    if (!this.studentId) return;
                    this.loadingBoletas = true;
                    try {
                        const json = await fetch(`/representante/api/${this.studentId}/boletas-oficiales`, { headers: { Accept: 'application/json' } }).then(r => r.json());
                        this.boletasOficiales = json.boletas ?? [];
                    } catch { this.boletasOficiales = []; }
                    this.loadingBoletas = false;
                },
                gradeColorHex(avg) {
                    if (avg >= 90) return '#34d399';
                    if (avg >= 80) return '#60a5fa';
                    if (avg >= 70) return '#fbbf24';
                    if (avg >= 60) return '#fb923c';
                    return '#f87171';
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
