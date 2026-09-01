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
    @include('partials.theme-system')
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
        .fam-drawer-dim {
            display: none !important;
            pointer-events: none !important;
        }
        @media (max-width: 860px) {
            .fam-drawer-dim.is-open {
                display: block !important;
                pointer-events: auto !important;
                position: fixed;
                inset: 0;
                background: rgba(8,6,20,.5);
                z-index: 28;
            }
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
        .event-desc.is-preview {
            display: -webkit-box;
            -webkit-line-clamp: 4;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .event-body { white-space: pre-wrap; word-break: break-word; font-size: 14px; line-height: 1.6; color: var(--text-primary); margin: 12px 0 0; overflow: visible; max-height: none; }
        .event-full { margin-top: 8px; overflow: visible; }
        .event-hint { margin: 8px 0 0; font-size: 11px; font-weight: 700; color: var(--nova-violet); }
        .event-toggle { margin-top: 10px; }
        .event-md .lesson-sections { display: flex; flex-direction: column; gap: 8px; margin-top: 8px; }
        .event-md .lesson-section { border-radius: 12px; padding: 10px 12px; }
        .event-md .lesson-section-title { font-size: 11px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; margin-bottom: 6px; }
        .event-md .lesson-section-content { font-size: 14px; line-height: 1.55; color: var(--text-primary); }
        .event-md .lesson-section-content p { margin: 0 0 .45em; }
        .event-md .lesson-section-content p:last-child { margin-bottom: 0; }
        .event-md .lesson-section-content ul, .event-md .lesson-section-content ol { padding-left: 1.2rem; margin: 0 0 .45em; }
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
        .nav-kicker {
            margin: 14px 8px 4px;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: var(--text-tertiary);
        }
        .nav-item.as-link { text-decoration: none; }
        .fam-toast {
            position: fixed;
            left: 24px;
            bottom: 24px;
            z-index: 90;
            max-width: min(320px, calc(100vw - 32px));
            border-radius: 14px;
            padding: 12px 14px;
            font-size: 13px;
            font-weight: 700;
            color: var(--text-primary);
            background: var(--bg-card);
            border: 1px solid var(--nova-glass-border);
            box-shadow: var(--nova-shadow);
        }
        .fam-backdrop {
            display: none !important;
            pointer-events: none !important;
            position: fixed;
            inset: 0;
            background: rgba(15, 17, 23, .48);
            z-index: 80;
            align-items: center;
            justify-content: center;
            padding: max(12px, env(safe-area-inset-top)) 16px max(16px, env(safe-area-inset-bottom));
            overflow-y: auto;
        }
        .fam-backdrop.is-open {
            display: flex !important;
            pointer-events: auto !important;
        }
        .fam-dialog {
            display: none;
            position: relative !important;
            top: auto !important;
            left: auto !important;
            width: min(640px, 100%) !important;
            height: auto !important;
            max-height: min(92dvh, 92vh);
            overflow: auto;
            -webkit-overflow-scrolling: touch;
            background: var(--bg-card, var(--bg-secondary));
            color: var(--text-primary);
            border: 1px solid var(--nova-glass-border);
            border-radius: 28px;
            padding: 22px;
            box-shadow: 0 24px 80px rgba(15, 17, 23, .28);
            z-index: 81;
        }
        .fam-dialog.is-visible { display: block !important; }
        .btn { border: 0; border-radius: 14px; padding: 11px 16px; font-weight: 800; cursor: pointer; }
        .btn-primary { background: var(--nova-gradient); color: #fff; }
        .btn-ghost { background: var(--nova-glass); color: var(--text-primary); border: 1px solid var(--nova-glass-border); }
        input, select, textarea {
            width: 100%; border-radius: 14px; border: 1px solid var(--nova-glass-border);
            background: var(--bg-tertiary); color: var(--text-primary); padding: 11px 12px; margin: 6px 0 12px;
        }
        .chat-bubble { max-width: 80%; padding: 10px 12px; border-radius: 16px; margin: 8px 0; white-space: pre-wrap; word-break: break-word; }
        .chat-bubble.mine { margin-left: auto; background: var(--nova-gradient); color: #fff; }
        .chat-bubble.theirs { background: var(--bg-tertiary); }
        .chat-meta { font-size: 10px; font-weight: 700; opacity: .65; margin-top: 4px; }
        .empty { color: var(--text-secondary); font-size: 14px; padding: 18px 0; }
        .hello { margin: 0 0 18px; }
        .hello h1 { font-family: var(--font-display); font-size: 28px; font-weight: 900; letter-spacing: -.04em; margin: 0 0 6px; }
        .hello p { margin: 0; color: var(--text-secondary); font-size: 14px; }
        .kid-pills { display:flex; gap:8px; flex-wrap:wrap; margin: 12px 0 18px; }
        .kid-pill {
            border: 1px solid var(--nova-glass-border);
            background: var(--bg-card);
            color: var(--text-primary);
            border-radius: 999px;
            padding: 8px 14px;
            font-weight: 800;
            font-size: 13px;
            cursor: pointer;
        }
        .kid-pill.is-on {
            background: var(--nova-gradient);
            color: #fff;
            border-color: transparent;
        }
        .week-strip { display:grid; grid-template-columns: repeat(7, minmax(0,1fr)); gap: 8px; margin-bottom: 18px; }
        .week-card {
            border: 1px solid var(--nova-glass-border);
            background: var(--bg-card);
            border-radius: 16px;
            padding: 10px 8px;
            min-height: 88px;
            cursor: pointer;
            text-align: left;
        }
        .week-card.is-today { box-shadow: inset 0 0 0 1.5px var(--nova-fuchsia); }
        .week-card.is-on { border-color: var(--nova-violet); background: color-mix(in srgb, var(--nova-violet) 10%, var(--bg-card)); }
        .week-card small { display:block; font-size:10px; font-weight:800; letter-spacing:.06em; text-transform:uppercase; color:var(--text-tertiary); }
        .week-card b { display:block; font-size:18px; margin: 2px 0 6px; }
        .week-card span { display:block; font-size:11px; color:var(--text-secondary); line-height:1.35; }
        .cal-day { min-height: 72px; }
        .cal-titles { margin-top: 4px; display:flex; flex-direction:column; gap:2px; }
        .cal-chip {
            font-size: 9px; font-weight: 800; border-radius: 6px; padding: 1px 4px;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
            background: color-mix(in srgb, var(--nova-violet) 14%, transparent);
            color: var(--text-primary);
        }
        .cal-chip.task { background: color-mix(in srgb, #f59e0b 22%, transparent); }
        .cal-chip.evaluation { background: color-mix(in srgb, #dc2626 18%, transparent); }
        .cal-chip.class { background: color-mix(in srgb, #2563eb 16%, transparent); }
        .insight {
            border: 1px solid var(--nova-glass-border);
            background: color-mix(in srgb, var(--nova-violet) 7%, var(--bg-card));
            border-radius: 18px;
            padding: 14px 16px;
            margin-bottom: 16px;
        }
        .theme-toggle-wrap { position: relative; z-index: 30; display: flex; align-items: center; gap: 8px; }
        .theme-picker {
            position: fixed;
            left: 16px;
            bottom: 72px;
            width: min(260px, calc(100vw - 24px));
            max-height: min(60vh, 420px);
            overflow-y: auto;
            overflow-x: hidden;
            background: var(--bg-card);
            border: 1px solid var(--nova-glass-border);
            border-radius: 16px;
            box-shadow: var(--nova-shadow);
            padding: 12px;
            z-index: 2000;
        }
        .theme-picker h4 { margin: 0 0 8px; font-size: 11px; text-transform: uppercase; letter-spacing: .06em; color: var(--text-tertiary); }
        .theme-picker-option {
            width: 100%; display: flex; align-items: center; gap: 10px; border: 0; background: transparent;
            color: var(--text-primary); padding: 8px; border-radius: 12px; cursor: pointer; font-size: 13px; font-weight: 700; text-align: left;
        }
        .theme-picker-option:hover, .theme-picker-option.active { background: color-mix(in srgb, var(--nova-violet) 12%, transparent); }
        .theme-picker-dot { width: 16px; height: 16px; border-radius: 50%; flex-shrink: 0; box-shadow: inset 0 0 0 1px rgba(0,0,0,.08); }
        .theme-toggle {
            width: 44px; height: 44px; border-radius: 14px; background: var(--nova-glass);
            border: 1px solid var(--nova-glass-border); color: var(--nova-violet); cursor: pointer;
            display: flex; align-items: center; justify-content: center;
        }
        .sidebar-theme { position: relative; display: flex; align-items: center; gap: 8px; padding: 8px 4px 14px; }
        .upcoming-list { display: flex; flex-direction: column; }
        .upcoming-empty { color: var(--text-tertiary); font-size: 13px; margin: 4px 0 8px; }
        .upcoming-row {
            display: flex; align-items: center; gap: 10px; width: 100%;
            padding: 8px 4px; border: 0; background: transparent; color: inherit;
            border-bottom: 1px solid color-mix(in srgb, var(--nova-violet) 8%, transparent);
            border-radius: 12px; cursor: pointer; text-align: left;
        }
        .upcoming-row:last-child { border-bottom: 0; }
        .upcoming-row:hover { background: color-mix(in srgb, var(--nova-violet) 8%, transparent); }
        .upcoming-date {
            width: 42px; height: 42px; border-radius: 12px; flex-shrink: 0;
            background: color-mix(in srgb, var(--grade-accent, #7C3AED) 14%, transparent);
            color: var(--grade-accent, #7C3AED);
            display: flex; flex-direction: column; align-items: center; justify-content: center;
        }
        html.dark .upcoming-date { background: color-mix(in srgb, var(--grade-accent, #7C3AED) 22%, transparent); }
        .upcoming-date strong { font-size: 14px; line-height: 1; }
        .upcoming-date span { font-size: 8px; font-weight: 800; letter-spacing: .06em; }
        .upcoming-copy { min-width: 0; flex: 1; }
        .upcoming-copy p { font-size: 13px; font-weight: 700; margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .upcoming-copy small { color: var(--text-tertiary); font-size: 11px; }
        .status-pill {
            font-size: 10px; font-weight: 700; padding: 4px 8px; border-radius: 999px;
            background: #ECFDF5; color: #059669; white-space: nowrap;
        }
        html.dark .status-pill { background: rgba(16,185,129,.15); color: #6EE7B7; }
        .panel-week-chip {
            font-size: 11px; font-weight: 800; border-radius: 999px; padding: 4px 10px;
            background: color-mix(in srgb, var(--nova-violet) 12%, transparent); color: var(--nova-violet);
        }
        .calendar-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; flex-wrap: wrap; margin-bottom: 18px; }
        .calendar-title h2 { font-size: 24px; font-weight: 800; margin: 0 0 4px; }
        .calendar-title p { margin: 0; color: var(--text-tertiary); font-size: 14px; text-transform: capitalize; }
        .calendar-nav { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .calendar-nav-btn {
            width: 40px; height: 40px; border-radius: 14px; border: 1px solid var(--nova-glass-border);
            background: var(--nova-glass); color: var(--text-primary); cursor: pointer; font-weight: 800;
        }
        .calendar-nav-btn.today-btn { width: auto; padding: 0 14px; }
        .calendar-stats { font-size: 12px; font-weight: 800; color: var(--text-tertiary); }
        .cal-legend { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 16px; }
        .cal-legend span { display: inline-flex; align-items: center; gap: 6px; font-size: 11px; font-weight: 700; color: var(--text-secondary); }
        .cal-legend i { width: 10px; height: 10px; border-radius: 4px; display: inline-block; }
        .weekdays { display: grid; grid-template-columns: repeat(7, 1fr); gap: 8px; margin-bottom: 8px; }
        .weekday { text-align: center; font-size: 12px; font-weight: 700; color: var(--text-tertiary); text-transform: uppercase; letter-spacing: .04em; }
        .calendar-days { display: grid; grid-template-columns: repeat(7, 1fr); gap: 8px; }
        .calendar-day {
            background: var(--bg-secondary); border: 1px solid var(--nova-glass-border); border-radius: 16px;
            min-height: 118px; max-height: 196px; padding: 8px; position: relative; overflow: hidden;
            display: flex; flex-direction: column; cursor: pointer; text-align: left; color: inherit;
        }
        .calendar-day.has-events { border-color: color-mix(in srgb, var(--nova-violet) 18%, var(--nova-glass-border)); }
        .calendar-day:hover { border-color: var(--nova-violet); background: var(--nova-glass); }
        .calendar-day.today { border-color: var(--nova-fuchsia); background: var(--nova-glass); }
        .calendar-day.empty { background: transparent; border-color: transparent; cursor: default; min-height: 40px; }
        .day-number { position: absolute; top: 6px; right: 8px; font-size: 12px; font-weight: 700; color: var(--text-tertiary); }
        .calendar-day.today .day-number { color: var(--nova-fuchsia); font-weight: 800; }
        .day-content { margin-top: 20px; display: flex; flex-direction: column; gap: 4px; min-height: 0; overflow-y: auto; }
        .cal-grade-event {
            display: flex; flex-direction: column; gap: 1px; width: 100%; border: 0; border-radius: 9px;
            padding: 5px 6px; text-align: left; color: #fff; cursor: pointer;
            box-shadow: 0 6px 14px -10px rgba(15, 23, 42, .55);
        }
        .cal-grade-event-line { display: flex; justify-content: space-between; font-size: 9px; font-weight: 800; letter-spacing: .04em; text-transform: uppercase; opacity: .92; }
        .cal-grade-event-title { font-size: 10px; font-weight: 700; line-height: 1.25; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .more-events { font-size: 9px; color: var(--nova-cyan); cursor: pointer; margin-top: 2px; background: none; border: 0; padding: 0; text-align: left; }
        .day-event-card {
            border: 1px solid var(--nova-glass-border); border-radius: 16px; padding: 14px; margin-top: 10px;
            background: var(--bg-card); cursor: pointer;
        }
        .day-event-card.is-selected {
            border-color: color-mix(in srgb, var(--nova-violet) 50%, var(--nova-glass-border));
            background: color-mix(in srgb, var(--nova-violet) 7%, var(--bg-card));
        }
        .day-event-card:hover { border-color: color-mix(in srgb, var(--nova-violet) 40%, var(--nova-glass-border)); }
        .day-event-meta { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
        .inbox { display: grid; grid-template-columns: minmax(220px, 280px) 1fr; gap: 12px; min-height: 420px; }
        .inbox-list, .inbox-pane {
            border: 1px solid var(--nova-glass-border); border-radius: 16px; background: var(--bg-secondary);
        }
        .inbox-list { padding: 8px; overflow: auto; max-height: 64vh; }
        .inbox-pane { padding: 14px; display: flex; flex-direction: column; min-height: 380px; }
        .inbox-row { border-radius: 12px; padding: 10px; cursor: pointer; margin-bottom: 4px; }
        .inbox-row.active { background: color-mix(in srgb, var(--nova-violet) 12%, transparent); }
        .inbox-row .preview { font-size: 12px; color: var(--text-secondary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .inbox-thread { flex: 1; overflow: auto; max-height: 46vh; padding-right: 4px; }
        .compose-box { border: 1px solid var(--nova-glass-border); border-radius: 14px; padding: 12px; margin-bottom: 12px; background: var(--bg-card); }
        .meta-chip {
            font-size: 11px; font-weight: 800; border-radius: 999px; padding: 3px 9px;
            background: var(--nova-glass); border: 1px solid var(--nova-glass-border); color: var(--text-primary);
        }
        .mobile-bar { display: none; }
        .desktop-day-events { display: block; }
        input, select, textarea { font-size: 16px; }
        @media (max-width: 1100px) {
            .kpi-grid, .split, .subject-row { grid-template-columns: 1fr 1fr; }
            .subject-row { display: flex; flex-direction: column; align-items: flex-start; }
        }
        @media (max-width: 860px) {
            .fam-shell { grid-template-columns: 1fr; }
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
            .week-strip { grid-template-columns: repeat(4, minmax(0,1fr)); }
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
            .calendar-day { min-height: 78px; max-height: 120px; padding: 6px; }
            .cal-grade-event-line { display: none; }
            .calendar-nav { width: 100%; justify-content: flex-start; }
            .split { grid-template-columns: 1fr; }
            .inbox { grid-template-columns: 1fr; }
            .fam-toast { left: 14px; bottom: calc(14px + env(safe-area-inset-bottom)); }
            .fam-backdrop.is-open { align-items: end; }
            .fam-dialog { border-radius: 24px 24px 16px 16px; padding: 18px 16px; }
        }
    </style>
</head>
<body x-data="familyHub" @keydown.escape.window="closeAnyModal()">
    @csrf
    <div class="mobile-bar">
        <button class="icon-btn" @click="sidebarOpen = !sidebarOpen" aria-label="Menú"><i class="fa-solid fa-bars"></i></button>
        <strong>AulaSync Familia</strong>
        <span style="width:44px"></span>
    </div>

    <div class="fam-drawer-dim" :class="{ 'is-open': sidebarOpen }" x-cloak @click="sidebarOpen = false"></div>

    <div class="fam-shell">
        <aside class="fam-sidebar" :class="{ open: sidebarOpen }">
            <div class="brand">
                <div class="brand-icon"><i class="fa-solid fa-robot"></i></div>
                <div>
                    <div class="brand-title">AulaSync</div>
                    <div class="brand-sub">{{ $schoolName ?? 'Panel familiar' }}</div>
                </div>
            </div>
            <button class="nav-item" :class="{ active: view === 'home' }" @click="setView('home')"><i class="fa-solid fa-house"></i> Inicio</button>
            <button class="nav-item" :class="{ active: view === 'calendar' }" @click="setView('calendar')"><i class="fa-solid fa-calendar-days"></i> Calendario</button>
            <button class="nav-item" :class="{ active: view === 'subjects' }" @click="setView('subjects')"><i class="fa-solid fa-book-open"></i> Materias</button>
            <button class="nav-item" :class="{ active: view === 'comms' }" @click="setView('comms')">
                <i class="fa-solid fa-comments"></i> Comunicación
                <span x-show="(unreadAnnouncements + unreadMessages) > 0" x-text="unreadAnnouncements + unreadMessages" style="margin-left:auto;background:#EC4899;color:#fff;border-radius:99px;padding:1px 7px;font-size:11px;"></span>
            </button>
            <button class="nav-item" :class="{ active: view === 'docs' }" @click="setView('docs')"><i class="fa-solid fa-folder-open"></i> Documentos</button>
            <div class="nav-kicker">Acciones</div>
            <button class="nav-item" @click="openAbsenceModal()"><i class="fa-solid fa-calendar-xmark"></i> Reportar ausencia</button>
            <button class="nav-item" @click="openBoletin(); sidebarOpen = false"><i class="fa-solid fa-file-lines"></i> Ver boletín</button>
            <a class="nav-item as-link" :href="constanciaUrl"><i class="fa-solid fa-stamp"></i> Constancia</a>
            <div style="flex:1"></div>
            <div class="sidebar-theme" @click.outside="showThemePicker = false">
                @include('components.theme-toggle')
                <button type="button" class="theme-toggle" x-ref="themePaletteBtn" @click.stop="toggleThemePicker()" title="Cambiar colores del tema">
                    <i class="fa-solid fa-palette"></i>
                </button>
                <div class="theme-picker" x-ref="themePicker" x-show="showThemePicker" x-cloak x-transition.opacity @click.stop>
                    <h4>Colores del tema</h4>
                    <template x-for="theme in themeOptions" :key="theme.id">
                        <button type="button" class="theme-picker-option" :class="{ active: currentThemeId === theme.id }" @click="applyTheme(theme.id)">
                            <span class="theme-picker-dot" :style="`background:${theme.dot}`"></span>
                            <span x-text="theme.label"></span>
                        </button>
                    </template>
                </div>
            </div>
            <button class="nav-item" @click="openProfileModal()"><i class="fa-solid fa-user-gear"></i> Editar perfil</button>
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
                    <div class="feed-item" @click="openNotification(n)">
                        <div :class="{ unread: !n.read }" style="font-weight:800" x-text="n.title"></div>
                        <div style="font-size:12px;color:var(--text-secondary)" x-text="n.message"></div>
                    </div>
                </template>
            </div>

            <template x-if="students.length === 0">
                <div class="panel" style="text-align:center;padding:60px 20px">
                    <i class="fa-solid fa-link" style="font-size:36px;color:var(--nova-violet)"></i>
                    <h2>Aún no hay hijos vinculados</h2>
                    <p class="empty">Pide al colegio el enlace familiar. Al abrirlo, tus hijos aparecen aquí.</p>
                </div>
            </template>

            <div x-show="students.length > 0">
                <div class="hello" x-show="view === 'home'">
                    <h1 x-text="'Hola, ' + parentFirstName"></h1>
                    <p x-text="homeSubtitle"></p>
                </div>
                <div class="kid-pills" x-show="students.length > 1">
                    <template x-for="s in students" :key="'pill'+s.id">
                        <button type="button" class="kid-pill" :class="{ 'is-on': String(studentId) === String(s.id) }" @click="studentId = s.id; refreshAll()" x-text="s.name"></button>
                    </template>
                </div>
                <div class="week-strip" x-show="view === 'home'">
                    <template x-for="day in weekStrip" :key="'w'+day.date">
                        <button type="button" class="week-card" :class="{ 'is-today': day.isToday, 'is-on': selectedDay === day.date }" @click="openDay(day.date)">
                            <small x-text="day.dow"></small>
                            <b x-text="day.n"></b>
                            <span x-text="day.label"></span>
                        </button>
                    </template>
                </div>
                <div class="insight" x-show="view === 'home' && homeInsight" x-text="homeInsight"></div>
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
                            <div class="kpi-label">Materias</div>
                            <div class="kpi-value" x-text="summary.courses_count ?? subjects.length ?? 0"></div>
                            <div class="kpi-hint" x-text="(summary.courses_count ?? subjects.length ?? 0) === 1 ? 'Curso inscrito' : 'Cursos inscritos'"></div>
                        </article>
                        <article class="kpi">
                            <div class="kpi-label">Ausencias del mes</div>
                            <div class="kpi-value" x-text="summary.attendance?.absences ?? 0"></div>
                            <div class="kpi-hint" x-text="(summary.attendance?.tardies ?? 0) + ' retraso' + ((summary.attendance?.tardies ?? 0) === 1 ? '' : 's')"></div>
                        </article>
                    </div>
                </div>

                <div class="split" x-show="view === 'home'">
                    <section class="panel">
                        <div class="cal-head">
                            <h2 class="section-title" style="margin:0">Próximas entregas</h2>
                            <span class="panel-week-chip" x-show="(summary.pending_tasks?.count || 0) > 0" x-text="(summary.pending_tasks?.count || 0) + ' pendientes'"></span>
                        </div>
                        <template x-if="(summary.pending_tasks?.items || []).length">
                            <div class="upcoming-list">
                                <template x-for="item in (summary.pending_tasks?.items || [])" :key="item.id || item.title">
                                    <button type="button" class="upcoming-row" :style="`--grade-accent: ${item.color || eventColor(item.type)}`" @click="openReminder(item)">
                                        <div class="upcoming-date">
                                            <strong x-text="dueParts(item.due_date || item.date).day"></strong>
                                            <span x-text="dueParts(item.due_date || item.date).mon"></span>
                                        </div>
                                        <div class="upcoming-copy">
                                            <p x-text="item.title"></p>
                                            <small x-text="[item.course, item.weight_percentage != null ? (item.weight_percentage + '% del lapso') : null].filter(Boolean).join(' · ')"></small>
                                        </div>
                                        <span class="status-pill" x-text="item.type_label || 'Tarea'"></span>
                                    </button>
                                </template>
                            </div>
                        </template>
                        <template x-if="!(summary.pending_tasks?.items || []).length">
                            <p class="upcoming-empty">No hay entregas próximas. Cuando el docente asigne una tarea, aparecerá aquí.</p>
                        </template>

                        <div class="cal-head" style="margin-top:18px">
                            <h2 class="section-title" style="margin:0">Recordatorios</h2>
                            <span class="panel-week-chip" x-show="(summary.evaluations?.count || 0) > 0" x-text="(summary.evaluations?.count || 0) + ' evaluaciones'"></span>
                        </div>
                        <template x-if="(summary.evaluations?.items || []).length">
                            <div class="upcoming-list">
                                <template x-for="item in (summary.evaluations?.items || [])" :key="item.id || item.title">
                                    <button type="button" class="upcoming-row" :style="`--grade-accent: ${item.color || eventColor('evaluation')}`" @click="openReminder(item)">
                                        <div class="upcoming-date">
                                            <strong x-text="dueParts(item.date).day"></strong>
                                            <span x-text="dueParts(item.date).mon"></span>
                                        </div>
                                        <div class="upcoming-copy">
                                            <p x-text="item.title"></p>
                                            <small x-text="[item.course, item.topic, item.weight_percentage != null ? (item.weight_percentage + '%') : null, item.max_score != null ? (item.max_score + ' pts') : null].filter(Boolean).join(' · ')"></small>
                                        </div>
                                        <span class="status-pill" style="background:#FEF2F2;color:#DC2626">Evaluación</span>
                                    </button>
                                </template>
                            </div>
                        </template>
                        <template x-if="!(summary.evaluations?.items || []).length">
                            <p class="upcoming-empty">Sin evaluaciones marcadas. El calendario se actualiza cuando el docente publica una.</p>
                        </template>
                        <button class="btn btn-ghost" style="margin-top:12px" @click="view = 'calendar'">Ver calendario</button>
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

                <section x-show="view === 'calendar'">
                    <div class="calendar-header">
                        <div class="calendar-title">
                            <h2><i class="fa-solid fa-calendar-days" style="color:var(--nova-violet);margin-right:10px"></i>Calendario académico</h2>
                            <p x-text="calendar.label || 'Agenda de tu hijo'"></p>
                        </div>
                        <div class="calendar-nav">
                            <button type="button" class="calendar-nav-btn" @click="shiftMonth(-1)" aria-label="Mes anterior"><i class="fa-solid fa-chevron-left"></i></button>
                            <button type="button" class="calendar-nav-btn today-btn" @click="goToday()">Hoy</button>
                            <button type="button" class="calendar-nav-btn" @click="shiftMonth(1)" aria-label="Mes siguiente"><i class="fa-solid fa-chevron-right"></i></button>
                            <span class="calendar-stats" x-text="(calendar.total_events ?? monthEventCount) + ' eventos'"></span>
                        </div>
                    </div>
                    <div class="cal-legend">
                        <span><i style="background:#2563EB"></i>Clase</span>
                        <span><i style="background:#F59E0B"></i>Tarea</span>
                        <span><i style="background:#7C3AED"></i>Actividad</span>
                        <span><i style="background:#DC2626"></i>Evaluación</span>
                        <span><i style="background:#EC4899"></i>Unidad</span>
                        <span><i style="background:#FB7185"></i>Asistencia</span>
                    </div>
                    <div class="weekdays">
                        <template x-for="d in ['Lun','Mar','Mié','Jue','Vie','Sáb','Dom']" :key="d"><div class="weekday" x-text="d"></div></template>
                    </div>
                    <div class="calendar-days">
                        <template x-for="day in monthDays" :key="'c'+day.key">
                            <div role="button"
                                    :class="{
                                        'calendar-day': !day.blank,
                                        'calendar-day empty': day.blank,
                                        today: isToday(day.date),
                                        'has-events': !day.blank && eventsFor(day.date).length
                                    }"
                                    @click="!day.blank && openDay(day.date)">
                                <template x-if="!day.blank">
                                    <div style="display:flex;flex-direction:column;min-height:0;height:100%">
                                        <span class="day-number" x-text="day.n"></span>
                                        <div class="day-content">
                                            <template x-for="ev in eventsFor(day.date).slice(0,3)" :key="'chip'+ev.id">
                                                <span class="cal-grade-event" :style="`background:${ev.color || eventColor(ev.type)}`" @click.stop="openDay(day.date, ev)">
                                                    <span class="cal-grade-event-line">
                                                        <span x-text="ev.type_label || ev.course || ''"></span>
                                                        <span x-text="ev.time_label || ''"></span>
                                                    </span>
                                                    <span class="cal-grade-event-title" x-text="ev.title"></span>
                                                </span>
                                            </template>
                                            <span class="more-events" x-show="eventsFor(day.date).length > 3"
                                                    x-text="'+' + (eventsFor(day.date).length - 3) + ' más'"></span>
                                        </div>
                                    </div>
                                </template>
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
                        <div class="inbox">
                            <div class="inbox-list">
                                <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;padding:4px 4px 8px">
                                    <strong>Chats</strong>
                                    <button type="button" class="btn btn-primary" style="padding:8px 12px" @click="showCompose = !showCompose">+</button>
                                </div>
                                <div class="compose-box" x-show="showCompose" x-cloak>
                                    <strong>Nuevo mensaje al docente</strong>
                                    <select x-model="composeCourseId">
                                        <option value="">Elige la materia</option>
                                        <template x-for="sub in subjects" :key="'c'+sub.id">
                                            <option :value="sub.id" x-text="sub.name + ' · ' + sub.teacher"></option>
                                        </template>
                                    </select>
                                    <textarea id="fam-compose-body" rows="3" x-model="newMessage" placeholder="Escribe el primer mensaje…"></textarea>
                                    <button class="btn btn-primary" @click="messageTeacher(composeCourseId)">Enviar</button>
                                </div>
                                <template x-if="threads.length === 0 && !showCompose"><p class="empty" style="padding:12px">Aún no hay conversaciones. Pulsa + para escribirle a un docente.</p></template>
                                <template x-for="t in threads" :key="t.id">
                                    <button type="button" class="inbox-row" :class="{ active: chat?.id === t.id }" @click="openThread(t.id)">
                                        <div style="display:flex;justify-content:space-between;gap:8px;align-items:center">
                                            <strong x-text="t.teacher"></strong>
                                            <span class="badge" style="position:static" x-show="t.unread > 0" x-text="t.unread"></span>
                                        </div>
                                        <div class="preview" x-text="t.preview || 'Sin mensajes'"></div>
                                        <div style="font-size:11px;color:var(--text-tertiary);margin-top:4px" x-text="[t.course, fmtTime(t.last_at)].filter(Boolean).join(' · ')"></div>
                                    </button>
                                </template>
                            </div>
                            <div class="inbox-pane">
                                <template x-if="!chat">
                                    <p class="empty">Elige un chat o pulsa + para escribirle al docente.</p>
                                </template>
                                <template x-if="chat">
                                    <div style="display:flex;flex-direction:column;min-height:100%">
                                        <strong x-text="chat.teacher"></strong>
                                        <div class="inbox-thread" x-ref="famMsgs">
                                            <template x-for="m in (chat.messages || [])" :key="m.id">
                                                <div class="chat-bubble" :class="m.mine ? 'mine' : 'theirs'">
                                                    <div x-text="m.body"></div>
                                                    <div class="chat-meta" x-text="(m.mine ? 'Tú' : chat.teacher) + (fmtTime(m.at) ? ' · ' + fmtTime(m.at) : '')"></div>
                                                </div>
                                            </template>
                                        </div>
                                        <div style="display:flex;gap:8px;margin-top:10px">
                                            <input x-model="chatBody" @keydown.enter="sendChat()" placeholder="Mensaje">
                                            <button class="btn btn-primary" @click="sendChat()">Enviar</button>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
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

    <div class="fam-backdrop" :class="{ 'is-open': !!modalName }" x-cloak @click.self="closeAnyModal()">
        <div class="fam-dialog" :class="{ 'is-visible': modalName === 'day' }" x-cloak @click.stop style="width:min(760px,100%) !important">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;margin-bottom:8px">
                <div>
                    <p style="margin:0 0 4px;font-size:11px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:var(--nova-violet)">Agenda de tu hijo</p>
                    <h3 style="margin:0;text-transform:capitalize" x-text="selectedDay ? fmtLong(selectedDay) : 'Día'"></h3>
                    <p class="empty" style="padding:6px 0 0;margin:0">Pulsa «Ver actividad completa» para leer todo el plan. Vuelve a pulsar para cerrarlo.</p>
                </div>
                <button class="icon-btn" @click="closeAnyModal()" aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <template x-if="modalName === 'day' && !eventsFor(selectedDay).length"><p class="empty">Sin clases, tareas ni evaluaciones este día.</p></template>
            <template x-for="ev in (modalName === 'day' ? eventsFor(selectedDay) : [])" :key="'sheet'+ev.id">
                <article class="day-event-card" :class="{ 'is-selected': isExpanded(ev) }" @click="toggleEvent(ev)">
                    <div class="event-head">
                        <div>
                            <div style="display:flex;align-items:center;gap:8px;">
                                <span class="dot" style="display:inline-block;width:8px;height:8px" :style="`background:${ev.color || eventColor(ev.type)}`"></span>
                                <strong x-text="ev.title"></strong>
                            </div>
                            <div class="event-meta" x-text="[ev.type_label, ev.course, ev.teacher].filter(Boolean).join(' · ')"></div>
                        </div>
                        <span class="event-pill" x-text="ev.time_label || ev.type_label"></span>
                    </div>
                    <div class="day-event-meta">
                        <span class="meta-chip" x-show="ev.topic" x-text="'Tema: ' + (ev.topic || '')"></span>
                        <span class="meta-chip" x-show="ev.weight_percentage != null" x-text="(ev.weight_percentage ?? 0) + '% del lapso'"></span>
                        <span class="meta-chip" x-show="ev.max_score != null" x-text="(ev.max_score ?? 0) + ' pts'"></span>
                        <span class="meta-chip" x-show="ev.total_points != null && ev.max_score == null" x-text="(ev.total_points ?? 0) + ' pts'"></span>
                        <span class="meta-chip" x-show="ev.difficulty" x-text="ev.difficulty"></span>
                    </div>
                    <p class="event-desc is-preview" x-show="!isExpanded(ev)" x-text="eventFullBody(ev) || 'Sin descripción por ahora.'"></p>
                    <div class="event-full" x-show="isExpanded(ev)" x-cloak @click.stop>
                        <div class="event-md" x-html="renderEventHtml(ev)"></div>
                        <button type="button" class="btn btn-primary" style="margin-top:14px" x-show="ev.course_id || ev.course" @click.stop="askTeacherAbout(ev)">
                            Escribirle al docente
                        </button>
                    </div>
                    <button type="button" class="btn btn-ghost event-toggle" @click.stop="toggleEvent(ev)" x-text="isExpanded(ev) ? 'Ocultar detalle' : 'Ver actividad completa'"></button>
                </article>
            </template>
            <div class="day-event-card is-selected" x-show="modalName === 'day' && orphanSelectedEvent" x-cloak>
                <div style="font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.08em;color:var(--nova-cyan);margin-bottom:6px" x-text="selectedEvent?.type_label"></div>
                <h3 style="margin:0;font-size:17px;font-weight:900" x-text="selectedEvent?.title"></h3>
                <div class="event-meta" x-text="[selectedEvent?.course, selectedEvent?.teacher].filter(Boolean).join(' · ')"></div>
                <div class="day-event-meta">
                    <span class="meta-chip" x-show="selectedEvent?.topic" x-text="'Tema: ' + (selectedEvent?.topic || '')"></span>
                    <span class="meta-chip" x-show="selectedEvent?.weight_percentage != null" x-text="(selectedEvent?.weight_percentage ?? 0) + '% del lapso'"></span>
                    <span class="meta-chip" x-show="selectedEvent?.max_score != null" x-text="(selectedEvent?.max_score ?? 0) + ' pts'"></span>
                </div>
                <div class="event-md" x-html="renderEventHtml(selectedEvent)"></div>
                <button type="button" class="btn btn-ghost event-toggle" @click="toggleEvent(selectedEvent)" x-text="isExpanded(selectedEvent) ? 'Ocultar detalle' : 'Ver actividad completa'"></button>
                <button type="button" class="btn btn-primary" style="margin-top:10px" x-show="selectedEvent?.course_id || selectedEvent?.course" @click="askTeacherAbout(selectedEvent)">
                    Escribirle al docente
                </button>
            </div>
        </div>

        <div class="fam-dialog" :class="{ 'is-visible': modalName === 'absence' }" x-cloak @click.stop>
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
                <button class="btn btn-ghost" @click="closeAnyModal()">Cancelar</button>
                <button class="btn btn-primary" @click="submitAbsence()">Enviar al colegio</button>
            </div>
        </div>

        <div class="fam-dialog" :class="{ 'is-visible': modalName === 'subject' }" x-cloak @click.stop>
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

        <div class="fam-dialog" :class="{ 'is-visible': modalName === 'announcement' }" x-cloak @click.stop>
            <h3 x-text="announcementModal?.title"></h3>
            <p class="empty" x-text="announcementModal?.author"></p>
            <p style="white-space:pre-wrap" x-text="announcementModal?.body"></p>
            <button class="btn btn-ghost" @click="closeAnyModal()">Cerrar</button>
        </div>

        <div class="fam-dialog" :class="{ 'is-visible': modalName === 'chat' }" x-cloak @click.stop>
            <h3 x-text="chat?.teacher"></h3>
            <div style="max-height:46vh;overflow:auto">
                <template x-for="m in (chat?.messages || [])" :key="m.id">
                    <div class="chat-bubble" :class="m.mine ? 'mine' : 'theirs'">
                        <div x-text="m.body"></div>
                        <div class="chat-meta" x-text="fmtTime(m.at)"></div>
                    </div>
                </template>
            </div>
            <div style="display:flex;gap:8px;margin-top:10px">
                <input x-model="chatBody" @keydown.enter="sendChat()" placeholder="Escribe un mensaje">
                <button class="btn btn-primary" @click="sendChat()">Enviar</button>
            </div>
        </div>

        <div class="fam-dialog" :class="{ 'is-visible': modalName === 'profile' }" x-cloak @click.stop>
            <h3>Editar perfil</h3>
            <label>Nombre</label><input x-model="profile.name">
            <label>Teléfono</label><input x-model="profile.phone">
            <label>Dirección</label><input x-model="profile.address">
            <label>Número de emergencia</label><input x-model="profile.emergency">
            <p x-text="profileMsg" style="color:var(--nova-success)"></p>
            <div style="display:flex;gap:8px;justify-content:flex-end">
                <button class="btn btn-ghost" @click="closeAnyModal()">Cerrar</button>
                <button class="btn btn-primary" @click="saveProfile()">Guardar</button>
            </div>
        </div>
    </div>

    <div x-show="toast" x-cloak class="fam-toast" x-text="toast"></div>
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('familyHub', () => ({
                students: @json($students),
                reasons: @json($reasons),
                studentId: @json($students->first()['id'] ?? null),
                view: 'home',
                modalName: null,
                sidebarOpen: false,
                isDark: document.documentElement.classList.contains('dark'),
                showThemePicker: false,
                currentThemeId: document.documentElement.getAttribute('data-theme') || 'light',
                summary: {},
                calendar: { events: {}, month: '{{ now()->format('Y-m') }}', label: '', total_events: 0 },
                subjects: [],
                announcements: [],
                threads: [],
                notifications: [],
                unreadNotif: 0,
                selectedDay: '{{ now()->toDateString() }}',
                selectedEvent: null,
                expandedEventId: null,
                daySheetOpen: false,
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
                showCompose: false,
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
                csrf() {
                    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                        || document.querySelector('input[name="_token"]')?.value
                        || '';
                },
                applyCsrf(token) {
                    if (!token) return this.csrf();
                    const meta = document.querySelector('meta[name="csrf-token"]');
                    if (meta) meta.setAttribute('content', token);
                    document.querySelectorAll('input[name="_token"]').forEach((el) => { el.value = token; });
                    return token;
                },
                xsrfCookie() {
                    const row = document.cookie.split('; ').find((part) => part.startsWith('XSRF-TOKEN='));
                    return row ? decodeURIComponent(row.split('=').slice(1).join('=')) : '';
                },
                async refreshCsrf() {
                    try {
                        const res = await fetch(@json(route('representante.api.csrf')), {
                            method: 'GET',
                            credentials: 'same-origin',
                            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        });
                        const json = await res.json().catch(() => ({}));
                        return this.applyCsrf(json.token) || this.csrf();
                    } catch (_) {
                        return this.csrf();
                    }
                },
                async postJson(url, payload = {}, retry = true) {
                    const token = await this.refreshCsrf();
                    const headers = {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': token,
                    };
                    const xsrf = this.xsrfCookie();
                    if (xsrf) headers['X-XSRF-TOKEN'] = xsrf;
                    const res = await fetch(url, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers,
                        body: JSON.stringify({ _token: token, ...payload }),
                    });
                    if (res.status === 419 && retry) {
                        await this.refreshCsrf();
                        return this.postJson(url, payload, false);
                    }
                    const json = await res.json().catch(() => ({}));
                    return { ok: res.ok, status: res.status, json };
                },
                get currentStudent() { return this.students.find(s => String(s.id) === String(this.studentId)); },
                get parentFirstName() {
                    const name = @json($parent['name'] ?? 'familia');
                    return String(name).trim().split(/\s+/)[0] || 'familia';
                },
                get homeSubtitle() {
                    const kid = this.currentStudent;
                    if (!kid) return 'Tu panel familiar.';
                    const bits = [kid.grade, kid.section, kid.school].filter(Boolean);
                    return bits.length ? `${kid.name} · ${bits.join(' · ')}` : kid.name;
                },
                get homeInsight() {
                    const next = this.summary?.pending_tasks?.next_title;
                    const when = this.fmt(this.summary?.pending_tasks?.next_date);
                    if (next) return `Próximo: ${next}${when ? ' · ' + when : ''}`;
                    const ev = this.summary?.evaluations?.next_title;
                    if (ev) return `Próxima evaluación: ${ev}`;
                    return 'Semana tranquila: no hay entregas marcadas.';
                },
                get weekStrip() {
                    const start = new Date();
                    start.setHours(12, 0, 0, 0);
                    const days = [];
                    for (let i = 0; i < 7; i++) {
                        const d = new Date(start);
                        d.setDate(start.getDate() + i);
                        const date = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
                        const events = this.calendar.events?.[date] || [];
                        days.push({
                            date,
                            n: d.getDate(),
                            dow: d.toLocaleDateString('es-VE', { weekday: 'short' }).replace('.', ''),
                            isToday: i === 0,
                            label: events[0]?.title || 'Libre',
                        });
                    }
                    return days;
                },
                get themeOptions() { return window.AULA_THEMES || []; },
                get unreadAnnouncements() { return this.announcements.filter(a => !a.read).length; },
                get unreadMessages() { return (this.threads || []).reduce((n, t) => n + (Number(t.unread) || 0), 0); },
                get monthEventCount() {
                    return Object.values(this.calendar.events || {}).reduce((n, list) => n + (list?.length || 0), 0);
                },
                get orphanSelectedEvent() {
                    if (!this.selectedEvent) return false;
                    return !this.eventsFor(this.selectedDay).some(e => e.id === this.selectedEvent.id);
                },
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
                    const d = new Date(`${value}T12:00:00`);
                    if (Number.isNaN(d.getTime())) return String(value).slice(0, 10);
                    return d.toLocaleDateString('es-VE', { day: '2-digit', month: 'short' });
                },
                fmtLong(value) {
                    if (!value) return '';
                    const d = new Date(`${value}T12:00:00`);
                    if (Number.isNaN(d.getTime())) return String(value);
                    return d.toLocaleDateString('es-VE', { weekday: 'long', day: 'numeric', month: 'long' });
                },
                fmtTime(value) {
                    if (!value) return '';
                    const d = new Date(value);
                    if (Number.isNaN(d.getTime())) return '';
                    return d.toLocaleString('es-VE', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
                },
                eventFullBody(ev) {
                    if (!ev) return '';
                    if (ev.body) return String(ev.body).trim();
                    return [ev.description, ev.instructions, ev.notes, ev.director_notes, ev.learning_outcome]
                        .map((part) => String(part || '').trim())
                        .filter(Boolean)
                        .filter((part, idx, all) => all.indexOf(part) === idx)
                        .join('\n\n');
                },
                isExpanded(ev) {
                    return !!ev && this.expandedEventId === ev.id;
                },
                toggleEvent(ev) {
                    if (!ev) return;
                    this.selectedEvent = ev;
                    this.expandedEventId = this.expandedEventId === ev.id ? null : ev.id;
                },
                renderEventHtml(ev) {
                    const text = this.eventFullBody(ev);
                    if (!text) return '<p>Sin descripción por ahora.</p>';
                    if (typeof window.renderMarkdown === 'function') {
                        return window.renderMarkdown(text);
                    }
                    return text
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        .replace(/\n/g, '<br>');
                },
                dueParts(value) {
                    if (!value) return { day: '—', mon: '' };
                    const d = new Date(`${value}T12:00:00`);
                    if (Number.isNaN(d.getTime())) return { day: String(value).slice(8, 10), mon: '' };
                    return {
                        day: d.getDate(),
                        mon: d.toLocaleDateString('es-VE', { month: 'short' }).replace('.', '').toUpperCase(),
                    };
                },
                eventColor(type) {
                    return ({
                        class: '#2563EB', task: '#F59E0B', activity: '#7C3AED',
                        evaluation: '#DC2626', plan: '#EC4899', absence: '#FB7185', tardy: '#F97316',
                    })[type] || '#7C3AED';
                },
                eventsFor(date) { return date ? (this.calendar.events?.[date] || []) : []; },
                applyTheme(themeId) {
                    if (window.applyAulaTheme) window.applyAulaTheme(themeId);
                    this.currentThemeId = document.documentElement.getAttribute('data-theme') || themeId;
                    this.isDark = document.documentElement.classList.contains('dark');
                    this.showThemePicker = false;
                },
                toggleThemePicker() {
                    this.showThemePicker = !this.showThemePicker;
                    if (this.showThemePicker) {
                        this.$nextTick(() => this.placeThemePicker());
                    }
                },
                placeThemePicker() {
                    const btn = this.$refs.themePaletteBtn;
                    const panel = this.$refs.themePicker;
                    if (!btn || !panel) return;
                    const rect = btn.getBoundingClientRect();
                    const width = Math.min(260, window.innerWidth - 16);
                    let left = rect.left;
                    if (left + width > window.innerWidth - 8) {
                        left = Math.max(8, window.innerWidth - width - 8);
                    }
                    panel.style.left = `${Math.max(8, left)}px`;
                    panel.style.right = 'auto';
                    panel.style.top = 'auto';
                    panel.style.bottom = `${Math.max(8, window.innerHeight - rect.top + 8)}px`;
                },
                setView(name) {
                    this.closeOverlays();
                    this.view = name;
                    this.sidebarOpen = false;
                    this.showThemePicker = false;
                    if (name !== 'comms') this.chat = null;
                    if (name === 'docs') this.loadBoletasOficiales();
                },
                closeOverlays() {
                    this.modalName = null;
                    this.daySheetOpen = false;
                    this.openAbsence = false;
                    this.openProfile = false;
                    this.subjectModal = null;
                    this.announcementModal = null;
                    this.showNotif = false;
                    this.expandedEventId = null;
                },
                trendIcon(t) { return t === 'up' ? '↑' : (t === 'down' ? '↓' : '↔'); },
                async init() {
                    window.addEventListener('resize', () => {
                        if (this.showThemePicker) this.placeThemePicker();
                    });
                    if (location.hash === '#comms') {
                        this.view = 'comms';
                        this.commTab = 'messages';
                    }
                    await this.refreshCsrf();
                    if (!this.studentId) return;
                    await this.refreshAll();
                    setInterval(() => this.refreshAll(true), 30000);
                    setInterval(() => {
                        if (this.view === 'comms' && this.commTab === 'messages') this.pollInbox();
                    }, 12000);
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
                    const d = new Date(y, m - 1 + (delta || 0), 1);
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
                    if (this.modalName === 'day' && this.selectedEvent) {
                        const fresh = list.find(e => e.id === this.selectedEvent.id);
                        if (fresh) this.selectedEvent = { ...this.selectedEvent, ...fresh };
                        return;
                    }
                    this.selectedEvent = list.length ? list[0] : null;
                },
                pickDay(date) {
                    this.openDay(date);
                },
                closeAnyModal() {
                    this.closeOverlays();
                    this.showThemePicker = false;
                    this.sidebarOpen = false;
                },
                openAbsenceModal() {
                    this.closeAnyModal();
                    this.openAbsence = true;
                    this.modalName = 'absence';
                },
                openProfileModal() {
                    this.closeAnyModal();
                    this.openProfile = true;
                    this.modalName = 'profile';
                },
                openDay(date, ev = null) {
                    if (!date) return;
                    this.closeAnyModal();
                    this.selectedDay = date;
                    const list = this.eventsFor(date);
                    this.selectedEvent = ev || list[0] || null;
                    this.expandedEventId = ev?.id || null;
                    this.daySheetOpen = true;
                    this.modalName = 'day';
                },
                openReminder(item) {
                    const date = item?.due_date || item?.date;
                    if (!date) return;
                    if (date.slice(0, 7) !== this.calendar.month) {
                        this.calendar.month = date.slice(0, 7);
                        this.shiftMonth(0).then(() => this.openDay(date, item));
                        return;
                    }
                    const match = this.eventsFor(date).find(e => e.id === item.id) || item;
                    this.openDay(date, match);
                },
                closeDaySheet() {
                    this.daySheetOpen = false;
                    if (this.modalName === 'day') this.modalName = null;
                },
                async goToday() {
                    const today = '{{ now()->toDateString() }}';
                    this.calendar.month = today.slice(0, 7);
                    await this.shiftMonth(0);
                    this.openDay(today);
                },
                isToday(date) {
                    if (!date) return false;
                    return date === '{{ now()->toDateString() }}';
                },
                openEvent(ev) {
                    this.toggleEvent(ev);
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
                    this.modalName = 'subject';
                },
                async openAnnouncement(a) {
                    this.announcementModal = a;
                    this.modalName = 'announcement';
                    await this.postJson(`/representante/api/anuncios/${a.id}/leer`, { estudiante_id: this.studentId });
                    a.read = true;
                },
                async openThread(id, useModal = false) {
                    const json = await fetch(`/representante/api/mensajes/${id}?estudiante_id=${this.studentId}`, { headers: { Accept: 'application/json' } }).then(r => r.json());
                    this.chat = json.thread;
                    this.chatBody = '';
                    this.view = 'comms';
                    this.commTab = 'messages';
                    this.modalName = useModal ? 'chat' : null;
                    const row = this.threads.find(t => t.id === id);
                    if (row) row.unread = 0;
                    this.$nextTick(() => {
                        const box = this.$refs.famMsgs;
                        if (box) box.scrollTop = box.scrollHeight;
                    });
                },
                async pollInbox() {
                    if (!this.studentId) return;
                    try {
                        const msgs = await fetch(`/representante/api/mensajes?estudiante_id=${this.studentId}`, { headers: { Accept: 'application/json' } }).then(r => r.json());
                        this.threads = msgs.threads || [];
                        if (this.chat?.id) {
                            const json = await fetch(`/representante/api/mensajes/${this.chat.id}?estudiante_id=${this.studentId}`, { headers: { Accept: 'application/json' } }).then(r => r.json());
                            if (json.thread) this.chat = json.thread;
                        }
                    } catch (_) {}
                },
                async sendChat() {
                    if (!this.chatBody.trim() || !this.chat) return;
                    const body = this.chatBody;
                    const { ok, status, json } = await this.postJson(`/representante/api/mensajes/${this.chat.id}`, {
                        estudiante_id: this.studentId,
                        body,
                    });
                    if (!ok) {
                        this.showToast(status === 419 ? 'La sesión expiró. Recarga e inténtalo de nuevo.' : (json.message || 'No se pudo enviar el mensaje.'));
                        return;
                    }
                    this.chatBody = '';
                    this.showToast('Mensaje enviado.');
                    await this.openThread(this.chat.id, this.modalName === 'chat');
                },
                askTeacherAbout(ev) {
                    const courseId = ev?.course_id || this.subjects.find(s => s.name === ev?.course)?.id;
                    const title = ev?.title || 'esta actividad';
                    const when = ev?.date ? this.fmtLong(ev.date) : '';
                    this.composeCourseId = courseId ? String(courseId) : '';
                    this.newMessage = `Hola, quería consultar sobre «${title}»${when ? ' (' + when + ')' : ''}.\n\n`;
                    this.closeAnyModal();
                    this.view = 'comms';
                    this.commTab = 'messages';
                    this.chat = null;
                    this.showCompose = true;
                    if (!courseId) this.showToast('Elige la materia para enviarle el mensaje al docente.');
                    this.$nextTick(() => document.getElementById('fam-compose-body')?.focus());
                },
                async messageTeacher(courseId) {
                    if (!courseId) { this.showToast('Elige una materia.'); return; }
                    if (!this.newMessage.trim()) { this.showToast('Escribe un mensaje.'); return; }
                    const { ok, status, json } = await this.postJson(`/representante/api/mensajes`, {
                        estudiante_id: this.studentId,
                        course_id: courseId,
                        body: this.newMessage,
                    });
                    if (!ok) {
                        this.showToast(status === 419 ? 'La sesión expiró. Recarga e inténtalo de nuevo.' : (json.message || 'No se pudo enviar el mensaje.'));
                        return;
                    }
                    this.newMessage = '';
                    this.composeCourseId = '';
                    this.subjectModal = null;
                    this.modalName = null;
                    this.view = 'comms';
                    this.commTab = 'messages';
                    this.showCompose = false;
                    this.showToast('Mensaje enviado al docente.');
                    await this.refreshAll();
                    if (json.thread_id) await this.openThread(json.thread_id);
                },
                openNotification(n) {
                    this.showNotif = false;
                    const haystack = `${n?.title || ''} ${n?.link || ''}`.toLowerCase();
                    if (haystack.includes('mensaje') || haystack.includes('#comms')) {
                        this.setView('comms');
                        this.commTab = 'messages';
                    }
                },
                async submitAbsence() {
                    this.absenceError = '';
                    if (!this.absence.reason_id) {
                        this.absenceError = 'No hay motivos cargados. Recarga la página o avisa al colegio.';
                        return;
                    }
                    const { ok, json } = await this.postJson(`/representante/api/ausencia`, this.absence);
                    if (!ok) {
                        const firstError = json.errors ? Object.values(json.errors).flat()[0] : null;
                        this.absenceError = firstError || json.message || json.error || 'No se pudo enviar.';
                        return;
                    }
                    this.openAbsence = false;
                    this.modalName = null;
                    this.absence.comment = '';
                    this.showToast('Ausencia reportada. El docente ya fue notificado.');
                    await this.refreshAll();
                },
                async openBoletin() {
                    this.closeAnyModal();
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
                    const { json } = await this.postJson(`/representante/api/perfil`, this.profile);
                    this.profileMsg = json.message || 'Guardado.';
                    this.showToast('Perfil actualizado.');
                },
                toggleNotif() { this.showNotif = !this.showNotif; },
                async markNotifRead() {
                    await this.postJson(`/representante/api/notificaciones/leer`);
                    await this.refreshAll();
                },
                toggleTheme() {
                    this.toggleThemePicker();
                },
            }));
        });
    </script>
    <script>
        (function () {
            if (!('serviceWorker' in navigator)) return;
            navigator.serviceWorker.getRegistrations().then(function (regs) {
                regs.forEach(function (r) { r.unregister(); });
            });
        })();
    </script>
</body>
</html>
