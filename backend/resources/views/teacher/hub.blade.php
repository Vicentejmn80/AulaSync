<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>AulaSync · Hub Académico Inteligente</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@600;700;800;900&display=swap" rel="stylesheet">
    <style>
        /* ── Design System AulaSync (alineado con la landing --az-*) ── */
        :root {
            --nova-deep: #F1F5F9;
            --nova-dark: #FFFFFF;
            --nova-medium: #F8FAFC;
            --nova-light: #F1F5F9;
            --nova-violet: #7C3AED;
            --nova-fuchsia: #C455ED;
            --nova-cyan: #EC4899;
            --nova-success: #22C55E;
            --nova-warning: #F59E0B;
            --nova-gradient: linear-gradient(135deg, #7C3AED 0%, #C455ED 55%, #EC4899 100%);
            --nova-glass: rgba(124, 58, 237, 0.04);
            --nova-glass-border: rgba(139, 46, 201, 0.14);
            --nova-shadow: 0 10px 28px rgba(28, 20, 60, 0.07);
            --text-primary: #1C1233;
            --text-secondary: #5B4B72;
            --text-tertiary: #75648F;
            --text-inverse: #FFFFFF;
            --bg-primary: #F4F6FB;
            --bg-secondary: #FFFFFF;
            --bg-tertiary: #F0E8FF;
            --bg-card: #FFFFFF;
            --bg-sidebar: #FFFFFF;
            /* Alias directos del sistema de la landing, para componentes nuevos */
            --az-violet: #7C3AED;
            --az-fuchsia: #C455ED;
            --az-rose: #EC4899;
            --az-radius-lg: 26px;
            --az-radius-md: 18px;
            --az-shadow-glow: 0 16px 40px -20px rgba(107, 33, 168, 0.34);
            --font-display: 'Manrope', 'Inter', system-ui, sans-serif;
        }

        html.dark {
            --nova-deep: #060B18;
            --nova-dark: #0C1225;
            --nova-medium: #11182F;
            --nova-light: #192140;
            --nova-violet: #8B5CF6;
            --nova-fuchsia: #C455ED;
            --nova-cyan: #EC4899;
            --nova-success: #22C55E;
            --nova-warning: #F59E0B;
            --nova-gradient: linear-gradient(135deg, #8B5CF6 0%, #C455ED 55%, #EC4899 100%);
            --nova-glass: rgba(196, 85, 237, 0.05);
            --nova-glass-border: rgba(196, 85, 237, 0.18);
            --nova-shadow: 0 25px 50px -12px rgba(59, 7, 100, 0.5);
            --text-primary: rgba(255, 255, 255, 0.92);
            --text-secondary: rgba(255, 255, 255, 0.78);
            --text-tertiary: rgba(255, 255, 255, 0.52);
            --text-inverse: #0C1225;
            --bg-primary: #0F0A1F;
            --bg-secondary: #170F2E;
            --bg-tertiary: #1E1440;
            --bg-card: rgba(23, 15, 46, 0.85);
            --bg-sidebar: rgba(20, 13, 40, 0.92);
            --az-shadow-glow: 0 25px 50px -12px rgba(196, 85, 237, 0.4);
        }

        [x-cloak] {
            display: none !important;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'SF Pro Text', 'Manrope', 'Inter', system-ui, sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            overflow-x: hidden;
            overflow-y: auto;
            transition: background-color 0.3s ease, color 0.2s ease;
            -webkit-font-smoothing: antialiased;
        }

/* ── Theme Toggle Button (CORREGIDO) ───────────────────── */
.theme-toggle-wrap {
    position: relative;
    z-index: 30;
}
.theme-picker-backdrop {
    position: fixed;
    inset: 0;
    z-index: 1900;
    background: transparent;
}
.theme-picker {
    position: fixed;
    top: 72px;
    left: 16px;
    width: min(300px, calc(100vw - 24px));
    max-height: min(70vh, 520px);
    overflow-y: auto;
    overflow-x: hidden;
    background: var(--bg-card);
    border: 1px solid var(--nova-glass-border);
    border-radius: 16px;
    box-shadow: var(--nova-shadow);
    padding: 12px;
    z-index: 2000;
}
@media (min-width: 768px) {
    .theme-picker { left: 312px; }
}
.theme-picker h4 {
    margin: 0 0 8px;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: .06em;
    color: var(--text-tertiary);
}
.theme-picker-option {
    width: 100%;
    display: flex;
    align-items: center;
    gap: 10px;
    border: 0;
    background: transparent;
    color: var(--text-primary);
    padding: 8px;
    border-radius: 12px;
    cursor: pointer;
    font-size: 13px;
    font-weight: 700;
    text-align: left;
}
.theme-picker-option span:last-child {
    white-space: normal;
    overflow: visible;
    line-height: 1.3;
}
.theme-picker-option:hover,
.theme-picker-option.active {
    background: color-mix(in srgb, var(--nova-violet) 12%, transparent);
}
.theme-picker-dot {
    width: 16px;
    height: 16px;
    border-radius: 50%;
    flex-shrink: 0;
    box-shadow: inset 0 0 0 1px rgba(0,0,0,0.08);
}
.theme-toggle {
    width: 44px;
    height: 44px;
    border-radius: 14px;
    background: var(--nova-glass);
    border: 1px solid var(--nova-glass-border);
    color: var(--nova-violet);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
    outline: none;
}

.theme-toggle::before {
    content: '';
    position: absolute;
    inset: 0;
    background: var(--nova-gradient);
    opacity: 0;
    transition: opacity 0.3s ease;
    pointer-events: none;
}

.theme-toggle:hover::before {
    opacity: 0.2;
}

.theme-toggle:hover {
    transform: scale(1.1);
    border-color: var(--nova-violet);
}

.theme-toggle i {
    position: relative;
    z-index: 2;
    font-size: 20px;
    transition: transform 0.3s ease;
    pointer-events: none;
}

.theme-toggle:hover i {
    transform: rotate(15deg);
}

.theme-toggle:active {
    transform: scale(0.95);
}

        /* ── Animaciones avanzadas ──────────────────────────── */
        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            33% { transform: translateY(-10px) rotate(1deg); }
            66% { transform: translateY(5px) rotate(-1deg); }
        }

        @keyframes glow-pulse {
            0%, 100% { opacity: 0.3; filter: blur(20px); }
            50% { opacity: 0.7; filter: blur(25px); }
        }

        @keyframes shimmer {
            0% { background-position: -1000px 0; }
            100% { background-position: 1000px 0; }
        }

        @keyframes slide-up {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* ── Efectos de fondo dinámicos ─────────────────────── */
        .nova-bg {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: -1;
            overflow: hidden;
            transition: opacity 0.5s ease;
        }

        html.dark .nova-bg-orb {
            opacity: 0.85;
        }
        :root:not(.dark) .nova-bg-orb {
            opacity: 0.4;
        }

        .nova-bg-orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            animation: glow-pulse 8s ease-in-out infinite;
            transition: all 0.5s ease;
        }

        .nova-bg-orb:nth-child(1) {
            top: -10%;
            left: -5%;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(124, 58, 237, 0.4) 0%, transparent 70%);
            animation-delay: 0s;
        }

        .nova-bg-orb:nth-child(2) {
            bottom: -10%;
            right: -5%;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(192, 38, 211, 0.3) 0%, transparent 70%);
            animation-delay: 2s;
        }

        .nova-bg-orb:nth-child(3) {
            top: 40%;
            right: 20%;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(6, 182, 212, 0.2) 0%, transparent 70%);
            animation-delay: 4s;
        }

        .nova-grid {
            position: absolute;
            inset: 0;
            background-image: 
                linear-gradient(var(--nova-glass-border) 1px, transparent 1px),
                linear-gradient(90deg, var(--nova-glass-border) 1px, transparent 1px);
            background-size: 50px 50px;
            pointer-events: none;
            opacity: 0.5;
        }

        /* ── Hub Root ───────────────────────────────────────── */
        #hub-root {
            display: flex;
            height: 100vh;
            height: 100dvh;
            max-height: 100dvh;
            width: 100%;
            position: relative;
            backdrop-filter: blur(20px);
            background: rgba(0, 0, 0, 0.2);
            overflow: hidden;
        }

        :root:not(.dark) #hub-root {
            background: rgba(255, 255, 255, 0.3);
        }
        html.dark #hub-root {
            background: rgba(0, 0, 0, 0.2);
        }

        /* ── Sidebar Nova ───────────────────────────────────── */
        #hub-sidebar {
            width: 300px;
            min-width: 300px;
            height: 100dvh;
            max-height: 100dvh;
            min-height: 0;
            background: var(--bg-sidebar);
            backdrop-filter: blur(20px);
            border-right: 1px solid var(--nova-glass-border);
            display: flex;
            flex-direction: column;
            position: relative;
            overflow: hidden;
            box-shadow: 5px 0 30px -15px rgba(0, 0, 0, 0.5);
            transition: background-color 0.3s ease, border-color 0.3s ease, transform 0.25s ease;
        }

        @media (max-width: 767px) {
            #hub-sidebar {
                position: fixed;
                left: 0;
                top: 0;
                height: 100vh;
                height: 100dvh;
                z-index: 110;
                width: min(280px, 80vw);
                min-width: unset;
                transform: translateX(-100%);
                box-shadow: none;
                overflow-y: auto;
                overflow-x: hidden;
                -webkit-overflow-scrolling: touch;
            }

            #hub-sidebar.hub-sidebar-open {
                transform: translateX(0);
                box-shadow: 8px 0 40px rgba(0, 0, 0, 0.45);
            }

            #hub-canvas {
                padding: calc(3.6rem + env(safe-area-inset-top)) 0.75rem calc(108px + env(safe-area-inset-bottom));
                min-height: calc(100dvh - 56px);
                height: 100dvh;
                overflow-y: auto;
                overflow-x: hidden;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .content-grid-2 {
                grid-template-columns: 1fr !important;
            }

            /* ── Calendario Mobile: celdas compactas + puntos ── */
            .calendar-header {
                flex-direction: column;
                gap: 10px;
                margin-bottom: 12px;
                align-items: stretch;
            }

            .calendar-title h2 {
                font-size: 18px;
                text-align: left;
            }

            .calendar-title p {
                font-size: 12px;
                text-align: left;
            }

            .calendar-nav {
                width: 100%;
                justify-content: flex-start;
                flex-wrap: wrap;
                gap: 6px;
            }

            .calendar-nav-btn {
                width: 40px;
                height: 40px;
                font-size: 14px;
            }

            .today-btn {
                padding: 0 14px;
                font-size: 13px;
            }

            .calendar-stats {
                width: auto;
                text-align: center;
                margin-left: 0;
            }

            .pedagogy-btn {
                width: 100%;
                justify-content: flex-start;
            }

            .calendar-grid {
                padding: 10px 8px 12px;
                border-radius: 18px;
                min-height: 0;
            }

            .weekdays {
                gap: 3px;
                margin-bottom: 6px;
            }

            .weekday {
                font-size: 10px;
                letter-spacing: 0;
            }

            .calendar-days {
                gap: 3px;
            }

            .calendar-day {
                min-height: 46px;
                max-height: 46px;
                height: 46px;
                padding: 4px 2px 6px;
                border-radius: 12px;
                cursor: pointer;
            }

            .calendar-day.has-events {
                border-color: color-mix(in srgb, var(--nova-violet) 40%, var(--nova-glass-border));
            }

            .day-number {
                position: static;
                display: block;
                text-align: center;
                font-size: 12px;
                line-height: 1.2;
                font-weight: 800;
            }

            .day-content {
                display: none;
            }

            .day-dots {
                display: flex;
                justify-content: center;
                gap: 3px;
                margin-top: 3px;
                min-height: 6px;
            }

            .day-more-count { display: none; }
            .more-events { display: none; }

            /* AI hint oculto en mobile */
            .ai-hint-cal {
                display: none;
            }

            .day-modal-content {
                max-height: 70vh;
                overflow-y: auto;
            }

            .dash-greeting { font-size: 22px; }
            .dash-toolbar { width: 100%; }
            .dash-search { min-width: 0; flex: 1; }
            .ios-stat { padding: 12px 10px; }
            .ios-stat-value { font-size: 18px; }
            .ai-command-card { padding: 12px; }
            .content-card { padding: 16px; border-radius: 18px; }
            .modal-overlay { padding: 12px; align-items: flex-end; }
            .modal-nova {
                max-width: 100%;
                width: 100%;
                max-height: min(88dvh, 860px);
                border-radius: 24px 24px 16px 16px;
            }
        }

        @media (min-width: 768px) {
            #hub-sidebar {
                position: relative;
                left: auto;
                top: auto;
                height: 100dvh;
                max-height: 100dvh;
                z-index: auto;
                width: 300px;
                min-width: 300px;
                transform: none !important;
            }
        }

        #hub-sidebar::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg, 
                transparent, 
                var(--nova-violet), 
                var(--nova-fuchsia), 
                var(--nova-cyan), 
                transparent
            );
        }

        .sidebar-brand {
            background: linear-gradient(180deg, var(--nova-dark) 0%, transparent 100%);
            border-bottom: 1px solid var(--nova-glass-border);
            position: relative;
            overflow: hidden;
        }

        .sidebar-brand::after {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, var(--nova-glass-border) 0%, transparent 70%);
            animation: float 15s ease-in-out infinite;
        }

        .brand-button {
            display: flex;
            align-items: center;
            gap: 12px;
            width: 100%;
            padding: 20px 20px 16px;
            background: transparent;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            z-index: 2;
        }

        .brand-button:hover {
            transform: translateX(5px);
        }

        .brand-icon {
            width: 48px;
            height: 48px;
            background: var(--nova-gradient);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 20px -5px var(--nova-violet);
        }

        .brand-icon::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transform: translateX(-100%);
            transition: transform 0.5s ease;
        }

        .brand-button:hover .brand-icon::before {
            transform: translateX(100%);
        }

        .brand-text {
            flex: 1;
            text-align: left;
        }

        .brand-title {
            font-family: var(--font-display);
            font-size: 18px;
            font-weight: 800;
            background: linear-gradient(135deg, var(--text-primary), var(--nova-violet));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 4px;
        }

        .brand-subtitle {
            font-size: 11px;
            color: var(--text-tertiary);
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .brand-subtitle i {
            color: var(--nova-cyan);
            font-size: 8px;
        }
        
        .user-panel {
    padding: 0 20px 20px;
    position: relative;
    z-index: 5;
    pointer-events: auto;
}

        /* ── Navegación ─────────────────────────────────────── */
        .nav-group-label {
            padding: 0 16px 8px;
            font-size: 10.5px;
            font-weight: 800;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: var(--text-tertiary);
        }

        .nav-section {
            padding: 10px 12px;
            border-bottom: 1px solid var(--nova-glass-border);
            flex-shrink: 0;
        }

        .nav-section-account {
            padding: 8px 12px 10px;
            flex-shrink: 0;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 12px;
            width: 100%;
            border: none;
            background: transparent;
            border-radius: 12px;
            color: var(--text-secondary);
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
            overflow: hidden;
        }

        .nav-item::before {
            content: '';
            position: absolute;
            inset: 0;
            background: var(--nova-gradient);
            opacity: 0;
            transition: opacity 0.2s ease;
            z-index: -1;
        }

        .nav-item:hover {
            color: var(--text-primary);
            transform: translateX(5px);
        }

        .nav-item:hover::before {
            opacity: 0.1;
        }

        .nav-item.active {
            color: var(--text-primary);
            background: var(--nova-glass);
            border-left: 3px solid var(--nova-violet);
        }

        .nav-item i {
            width: 20px;
            font-size: 16px;
            color: var(--nova-violet);
            transition: all 0.2s ease;
        }

        .nav-item:hover i {
            color: var(--nova-fuchsia);
            transform: scale(1.1);
        }

        .nav-badge {
            margin-left: auto;
            background: var(--nova-glass);
            color: var(--text-primary);
            font-size: 10px;
            font-weight: 700;
            padding: 4px 8px;
            border-radius: 30px;
            border: 1px solid var(--nova-glass-border);
        }

        .notification-badge {
            position: absolute;
            top: -4px;
            right: -6px;
            background: #ef4444;
            color: white;
            font-size: 9px;
            font-weight: 800;
            min-width: 16px;
            height: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            padding: 0 4px;
            line-height: 1;
            box-shadow: 0 0 6px rgba(239, 68, 68, 0.5);
        }

        .notifications-dropdown {
            position: absolute;
            top: calc(100% + 8px);
            right: 0;
            width: 320px;
            max-height: 400px;
            background: var(--bg-card);
            border: 1px solid var(--nova-glass-border);
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.4);
            overflow: hidden;
            z-index: 100;
        }

        .notifications-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 16px;
            border-bottom: 1px solid var(--nova-glass-border);
            color: var(--text-primary);
        }

        .notifications-list {
            max-height: 340px;
            overflow-y: auto;
        }

        .notifications-empty {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            padding: 32px 16px;
            color: var(--text-tertiary);
            font-size: 13px;
        }

        .notifications-item {
            display: flex;
            align-items: flex-start;
            padding: 12px 16px;
            text-decoration: none;
            transition: background 0.15s;
            border-bottom: 1px solid var(--nova-glass-border);
        }

        .notifications-item:hover {
            background: var(--nova-glass);
        }

        .notifications-item.unread {
            background: rgba(34, 211, 238, 0.05);
        }

        .notifications-item-content {
            flex: 1;
            min-width: 0;
        }

        .notifications-title {
            font-size: 13px;
            font-weight: 700;
            color: var(--text-primary);
        }

        .notifications-message {
            font-size: 12px;
            color: var(--text-secondary);
            margin-top: 2px;
        }

        .notifications-time {
            font-size: 10px;
            color: var(--text-tertiary);
            margin-top: 4px;
        }

        /* ── Cursos Sidebar ─────────────────────────────────── */
        .courses-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 16px 4px;
            flex-shrink: 0;
        }

        .courses-header h4 {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--text-tertiary);
        }

        .add-course-btn {
            width: 28px;
            height: 28px;
            border-radius: 10px;
            background: var(--nova-glass);
            border: 1px solid var(--nova-glass-border);
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .add-course-btn:hover {
            background: var(--nova-gradient);
            color: white;
            transform: rotate(90deg);
            border-color: transparent;
        }

        .course-list {
            flex: 1;
            min-height: 0;
            overflow-y: auto;
            padding: 4px 10px 8px;
        }

        .course-list::-webkit-scrollbar {
            width: 4px;
        }

        .course-list::-webkit-scrollbar-track {
            background: transparent;
        }

        .course-list::-webkit-scrollbar-thumb {
            background: var(--nova-glass-border);
            border-radius: 4px;
        }

        .course-btn {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 10px;
            width: 100%;
            border: none;
            background: transparent;
            border-radius: 12px;
            margin-bottom: 2px;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
        }

        .course-btn::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(90deg, 
                var(--nova-glass-border), 
                transparent
            );
            border-radius: 14px;
            opacity: 0;
            transition: opacity 0.2s ease;
        }

        .course-btn:hover::before {
            opacity: 1;
        }

        .course-btn.active {
            background: var(--nova-glass);
            border-left: 3px solid var(--course-accent, var(--nova-fuchsia));
        }

        .course-avatar {
            width: 32px;
            height: 32px;
            background: var(--course-accent, var(--nova-gradient));
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            font-weight: 700;
            color: white;
            position: relative;
            overflow: hidden;
            box-shadow: 0 4px 12px -4px var(--course-accent, var(--nova-violet));
            flex-shrink: 0;
        }

        .course-avatar::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(45deg, transparent, rgba(255,255,255,0.2), transparent);
            transform: translateX(-100%);
            transition: transform 0.5s ease;
        }

        .course-btn:hover .course-avatar::after {
            transform: translateX(100%);
        }

        .course-info {
            flex: 1;
            min-width: 0;
            text-align: left;
        }

        .course-name {
            color: var(--text-primary);
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .course-meta {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .course-grade {
            font-size: 10px;
            color: var(--text-tertiary);
        }

        .course-students-badge {
            background: var(--nova-glass);
            color: var(--nova-cyan);
            font-size: 9px;
            font-weight: 700;
            padding: 2px 6px;
            border-radius: 30px;
            border: 1px solid var(--nova-glass-border);
        }

        /* ── Canvas Principal ───────────────────────────────── */
        #hub-canvas {
            flex: 1;
            min-width: 0;
            min-height: 0;
            height: 100dvh;
            max-height: 100dvh;
            overflow-y: auto;
            overflow-x: hidden;
            padding: 20px 28px;
            position: relative;
            -webkit-overflow-scrolling: touch;
        }

        #hub-canvas::-webkit-scrollbar {
            width: 6px;
        }

        #hub-canvas::-webkit-scrollbar-track {
            background: transparent;
        }

        #hub-canvas::-webkit-scrollbar-thumb {
            background: var(--nova-glass-border);
            border-radius: 6px;
        }

        /* ── Tarjetas de Estadísticas ───────────────────────── */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card-nova {
            background: var(--bg-card);
            backdrop-filter: blur(10px);
            border: 1px solid var(--nova-glass-border);
            border-radius: 16px;
            padding: 16px;
            height: auto;
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
            animation: slide-up 0.5s ease forwards;
            opacity: 0;
        }

        .stat-card-nova:nth-child(1) { animation-delay: 0.1s; }
        .stat-card-nova:nth-child(2) { animation-delay: 0.2s; }
        .stat-card-nova:nth-child(3) { animation-delay: 0.3s; }
        .stat-card-nova:nth-child(4) { animation-delay: 0.4s; }

        .stat-card-nova:hover {
            transform: translateY(-5px);
            border-color: var(--nova-violet);
            box-shadow: var(--nova-shadow);
        }

        .stat-card-nova::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: var(--nova-gradient);
            transform: translateX(-100%);
            transition: transform 0.5s ease;
        }

        .stat-card-nova:hover::before {
            transform: translateX(100%);
        }

        .stat-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 15px;
        }

        .stat-label {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-tertiary);
        }

        .stat-icon {
            width: 40px;
            height: 40px;
            background: var(--nova-glass);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            border: 1px solid var(--nova-glass-border);
        }

        .stat-value {
            font-size: 36px;
            font-weight: 800;
            background: linear-gradient(135deg, var(--text-primary), var(--nova-violet));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            line-height: 1;
            margin-bottom: 8px;
        }

        .stat-footer {
            font-size: 12px;
            color: var(--text-tertiary);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .stat-footer i {
            color: var(--nova-cyan);
            font-size: 10px;
        }

        /* ── Dashboard iOS: header, widgets, insights ── */
        .dash-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-top: 0;
            margin-bottom: 14px;
            flex-wrap: wrap;
        }

        .dash-eyebrow {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            font-weight: 600;
            text-transform: capitalize;
            color: var(--text-tertiary);
            margin-bottom: 6px;
        }

        .dash-greeting {
            font-family: var(--font-display);
            font-size: 28px;
            font-weight: 800;
            color: var(--text-primary);
            margin-bottom: 4px;
            line-height: 1.1;
            letter-spacing: -0.03em;
        }

        .dash-subtitle {
            color: var(--text-secondary);
            font-size: 14px;
            max-width: 520px;
            line-height: 1.45;
        }

        .dash-toolbar {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .dash-search {
            display: flex;
            align-items: center;
            gap: 8px;
            min-width: 220px;
            height: 44px;
            padding: 0 16px;
            background: #fff;
            border: 1px solid rgba(124, 58, 237, 0.08);
            border-radius: 999px;
            box-shadow: 0 6px 20px rgba(28, 20, 60, 0.05);
        }

        html.dark .dash-search {
            background: var(--bg-card);
            border-color: var(--nova-glass-border);
        }

        .dash-search i { color: var(--text-tertiary); font-size: 13px; }

        .dash-search input {
            border: 0;
            outline: 0;
            background: transparent;
            width: 100%;
            font-size: 14px;
            color: var(--text-primary);
        }

        .ios-icon-btn {
            width: 44px;
            height: 44px;
            border-radius: 14px;
            border: 1px solid rgba(124, 58, 237, 0.08);
            background: #fff;
            color: var(--text-primary);
            box-shadow: 0 6px 20px rgba(28, 20, 60, 0.05);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            position: relative;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        html.dark .ios-icon-btn {
            background: var(--bg-card);
            border-color: var(--nova-glass-border);
            color: var(--text-primary);
        }

        .ios-icon-btn:hover { transform: translateY(-1px); }

        .btn-create {
            height: 44px;
            padding: 0 18px;
            border: 0;
            border-radius: 999px;
            background: var(--nova-gradient);
            color: #fff;
            font-weight: 800;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            box-shadow: 0 10px 24px rgba(124, 58, 237, 0.28);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .btn-create:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 30px rgba(124, 58, 237, 0.34);
        }

        .create-menu {
            position: absolute;
            right: 0;
            top: calc(100% + 8px);
            min-width: 230px;
            background: #fff;
            border-radius: 18px;
            padding: 8px;
            box-shadow: 0 18px 50px rgba(28, 20, 60, 0.16);
            border: 1px solid rgba(124, 58, 237, 0.08);
            z-index: 60;
        }

        html.dark .create-menu { background: var(--bg-secondary); border-color: var(--nova-glass-border); }

        .create-menu button,
        .create-menu a {
            display: flex;
            align-items: center;
            gap: 10px;
            width: 100%;
            padding: 11px 12px;
            border: 0;
            background: transparent;
            border-radius: 12px;
            color: var(--text-primary);
            font-size: 13.5px;
            font-weight: 600;
            text-align: left;
            cursor: pointer;
            text-decoration: none;
        }

        .create-menu button:hover,
        .create-menu a:hover { background: rgba(124, 58, 237, 0.08); }

        .ios-stat-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            margin-bottom: 14px;
        }

        .ios-stat {
            background: #fff;
            border-radius: 18px;
            padding: 14px 14px 12px;
            box-shadow: var(--nova-shadow);
            border: 1px solid rgba(255,255,255,0.8);
            transition: transform 0.22s cubic-bezier(.2,.8,.2,1);
        }

        html.dark .ios-stat { background: var(--bg-card); border-color: var(--nova-glass-border); }

        .ios-stat:hover { transform: translateY(-3px); }

        .ios-stat-icon {
            width: 34px;
            height: 34px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            margin-bottom: 10px;
            font-size: 14px;
        }

        .ios-stat-icon.purple { background: linear-gradient(135deg, #7C3AED, #A855F7); }
        .ios-stat-icon.green { background: linear-gradient(135deg, #10B981, #34D399); }
        .ios-stat-icon.blue { background: linear-gradient(135deg, #2563EB, #60A5FA); }
        .ios-stat-icon.amber { background: linear-gradient(135deg, #F59E0B, #FBBF24); }

        .ios-stat-value {
            font-family: var(--font-display);
            font-size: 22px;
            font-weight: 800;
            letter-spacing: -0.03em;
            line-height: 1;
            margin-bottom: 2px;
        }

        .ios-stat-label {
            font-size: 11.5px;
            font-weight: 600;
            color: var(--text-tertiary);
        }

        .ios-board {
            display: grid;
            grid-template-columns: 1.15fr 0.85fr;
            gap: 12px;
            margin-bottom: 0;
            align-items: start;
        }

        .ios-panel {
            background: #fff;
            border-radius: 18px;
            padding: 14px 16px;
            box-shadow: var(--nova-shadow);
        }

        html.dark .ios-panel { background: var(--bg-card); }

        .ios-panel h3 {
            font-family: var(--font-display);
            font-size: 14px;
            font-weight: 800;
            margin-bottom: 10px;
        }

        .ios-panel-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            margin-bottom: 6px;
        }

        .ios-panel-head h3 { margin-bottom: 0; }

        .panel-week-chip {
            font-size: 11px;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 999px;
            background: rgba(124, 58, 237, 0.1);
            color: #7C3AED;
            white-space: nowrap;
        }

        html.dark .panel-week-chip {
            background: rgba(124, 58, 237, 0.18);
            color: #C4B5FD;
        }

        .upcoming-list {
            display: flex;
            flex-direction: column;
            max-height: 168px;
            overflow-y: auto;
        }

        .today-grade-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
            max-height: 220px;
            overflow-y: auto;
        }

        .today-grade-group {
            border: 1px solid var(--nova-glass-border);
            border-left: 4px solid var(--grade-accent, #7C3AED);
            border-radius: 14px;
            background: var(--nova-glass);
            padding: 10px;
        }

        .today-grade-group header {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            margin-bottom: 8px;
        }

        .today-grade-group header strong {
            font-size: 13px;
            color: var(--text-primary);
        }

        .today-grade-group header span {
            font-size: 11px;
            color: var(--text-tertiary);
        }

        .today-grade-items {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .today-grade-item {
            border: 0;
            background: transparent;
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            border-radius: 10px;
            text-align: left;
            color: inherit;
            padding: 6px;
        }

        .today-grade-item:hover {
            background: rgba(124, 58, 237, 0.08);
        }

        .today-grade-time {
            font-size: 11px;
            font-weight: 700;
            color: var(--grade-accent, #7C3AED);
            min-width: 82px;
        }

        .today-grade-copy {
            min-width: 0;
            flex: 1;
        }

        .today-grade-copy p {
            font-size: 12px;
            font-weight: 700;
            color: var(--text-primary);
            margin: 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .today-grade-copy small {
            font-size: 11px;
            color: var(--text-tertiary);
        }

        .next-activity-box {
            width: 100%;
            border: 1px solid var(--nova-glass-border);
            border-left: 6px solid var(--next-accent, #7C3AED);
            border-radius: 22px;
            padding: 6px;
            margin-bottom: 16px;
            color: inherit;
            background:
                linear-gradient(135deg, color-mix(in srgb, var(--next-accent, #7C3AED) 16%, var(--bg-card)), var(--bg-card));
            box-shadow: 0 16px 32px -22px var(--next-accent, rgba(124, 58, 237, 0.6));
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .next-activity-box:hover {
            transform: translateY(-2px);
        }

        .next-activity-toggle {
            width: 100%;
            border: 0;
            background: transparent;
            padding: 14px 16px;
            text-align: left;
            cursor: pointer;
            color: inherit;
        }

        .next-activity-toggle:disabled {
            cursor: default;
        }

        .next-activity-kicker {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: var(--next-accent, #7C3AED);
            margin-bottom: 8px;
        }

        .next-activity-main {
            display: grid;
            grid-template-columns: auto 1fr auto;
            gap: 16px;
            align-items: center;
        }

        .next-activity-time-wrap {
            min-width: 86px;
        }

        .next-activity-time {
            font-size: 28px;
            line-height: 1;
            font-weight: 800;
            color: var(--text-primary);
        }

        .next-activity-time-wrap small,
        .next-activity-queue-time small {
            display: block;
            margin-top: 4px;
            font-size: 11px;
            font-weight: 700;
            color: var(--text-tertiary);
            text-transform: uppercase;
            letter-spacing: .04em;
        }

        .next-activity-copy h2 {
            margin: 0 0 4px;
            font-size: 20px;
            font-weight: 800;
            color: var(--text-primary);
        }

        .next-activity-copy p {
            margin: 0;
            font-size: 13px;
            color: var(--text-secondary);
        }

        .next-activity-type {
            display: inline-flex;
            align-items: center;
            padding: 6px 10px;
            border-radius: 999px;
            background: color-mix(in srgb, var(--next-accent, #7C3AED) 14%, transparent);
            color: var(--next-accent, #7C3AED);
            font-size: 11px;
            font-weight: 800;
        }

        .next-activity-queue {
            margin: 0 10px 10px;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .next-activity-queue-item {
            width: 100%;
            display: grid;
            grid-template-columns: 78px 1fr auto;
            gap: 10px;
            align-items: center;
            border: 0;
            background: color-mix(in srgb, var(--bg-secondary) 88%, transparent);
            border-radius: 14px;
            padding: 10px 12px;
            text-align: left;
            cursor: pointer;
            color: inherit;
        }

        .next-activity-queue-item strong {
            font-size: 13px;
            color: var(--text-primary);
        }

        .next-activity-queue-item small {
            display: block;
            color: var(--text-tertiary);
            font-size: 11px;
        }

        .next-activity-queue-time strong {
            font-size: 15px;
            color: var(--next-accent, #7C3AED);
        }

        @media (max-width: 767px) {
            .next-activity-main {
                grid-template-columns: 1fr;
                gap: 10px;
            }
            .next-activity-queue-item {
                grid-template-columns: 1fr;
            }
        }

        .upcoming-list::-webkit-scrollbar { width: 4px; }
        .upcoming-list::-webkit-scrollbar-thumb {
            background: var(--nova-glass-border);
            border-radius: 4px;
        }

        .upcoming-row--clickable {
            width: 100%;
            border: 0;
            background: transparent;
            cursor: pointer;
            text-align: left;
            color: inherit;
            border-radius: 12px;
            transition: background 0.15s ease;
        }

        .upcoming-row--clickable:hover {
            background: rgba(124, 58, 237, 0.05);
        }

        html.dark .upcoming-row--clickable:hover {
            background: rgba(124, 58, 237, 0.12);
        }

        .upcoming-empty {
            color: var(--text-tertiary);
            font-size: 13px;
            margin: 0 0 10px;
        }

        .upcoming-row {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 7px 0;
            border-bottom: 1px solid rgba(124, 58, 237, 0.06);
        }

        .upcoming-row:last-child { border-bottom: 0; }

        .upcoming-date {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            background: color-mix(in srgb, var(--grade-accent, #7C3AED) 14%, transparent);
            color: var(--grade-accent, #7C3AED);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        html.dark .upcoming-date { background: color-mix(in srgb, var(--grade-accent, #7C3AED) 22%, transparent); color: var(--grade-accent, #C4B5FD); }

        .upcoming-date strong { font-size: 14px; line-height: 1; }
        .upcoming-date span { font-size: 8px; font-weight: 800; letter-spacing: 0.06em; }

        .upcoming-copy { min-width: 0; flex: 1; }
        .upcoming-copy p { font-size: 13px; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .upcoming-copy small { color: var(--text-tertiary); font-size: 11px; }

        .status-pill {
            font-size: 10px;
            font-weight: 700;
            padding: 4px 8px;
            border-radius: 999px;
            background: #ECFDF5;
            color: #059669;
            white-space: nowrap;
        }

        html.dark .status-pill { background: rgba(16,185,129,0.15); color: #6EE7B7; }

        .reminder-row {
            display: flex;
            align-items: center;
            gap: 10px;
            width: 100%;
            padding: 7px 0;
            border: 0;
            background: transparent;
            cursor: pointer;
            text-align: left;
            color: inherit;
        }

        .reminder-ico {
            width: 32px;
            height: 32px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            flex-shrink: 0;
            font-size: 12px;
        }

        .quick-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 6px;
        }

        .quick-action {
            border: 0;
            background: #F7F4FF;
            border-radius: 999px;
            padding: 6px 10px;
            text-align: left;
            cursor: pointer;
            color: var(--text-primary);
            font-weight: 600;
            font-size: 11px;
            transition: transform 0.2s ease, background 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            white-space: nowrap;
        }

        html.dark .quick-action { background: rgba(124, 58, 237, 0.12); }

        .quick-action:hover { transform: translateY(-1px); background: #EFE7FF; }
        .quick-action i { color: #7C3AED; font-size: 11px; }

        .sidebar-profile {
            margin: 0 12px 12px;
            padding: 10px;
            border-radius: 14px;
            background: #F7F4FF;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-shrink: 0;
        }

        html.dark .sidebar-profile { background: rgba(124, 58, 237, 0.12); }

        .sidebar-profile-avatar {
            width: 34px;
            height: 34px;
            border-radius: 12px;
            background: var(--nova-gradient);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            flex-shrink: 0;
            font-size: 13px;
        }

        .sidebar-profile strong { display: block; font-size: 13px; }
        .sidebar-profile small { color: var(--text-tertiary); font-size: 11px; }

        @media (max-width: 1100px) {
            .ios-stat-grid { grid-template-columns: repeat(2, 1fr); }
            .ios-board { grid-template-columns: 1fr; }
        }

        @media (max-width: 767px) {
            .ios-stat-grid { grid-template-columns: 1fr 1fr; }
            .dash-search { min-width: 100%; }
        }

        /* AulaSync Intelligence — command center */
        .ai-command-card {
            position: relative;
            background: var(--bg-card);
            border: 1px solid var(--nova-glass-border);
            border-radius: 18px;
            padding: 14px 18px;
            margin-bottom: 14px;
            overflow: hidden;
        }

        @media (min-width: 900px) {
            .ai-command-card {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 16px;
            }

            .ai-command-head {
                margin-bottom: 0 !important;
            }

            .ai-command-footer {
                margin-top: 0 !important;
                flex-shrink: 0;
            }
        }

        .ai-command-glow {
            position: absolute;
            top: -60%;
            right: -10%;
            width: 320px;
            height: 320px;
            background: radial-gradient(circle, rgba(196, 85, 237, 0.16), transparent 70%);
            pointer-events: none;
        }

        .ai-command-head {
            display: flex;
            align-items: center;
            gap: 12px;
            position: relative;
            z-index: 1;
            margin-bottom: 12px;
            min-width: 0;
            flex: 1;
        }

        .ai-command-icon {
            width: 40px;
            height: 40px;
            flex-shrink: 0;
            border-radius: 12px;
            background: var(--nova-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 18px;
            box-shadow: var(--az-shadow-glow);
        }

        .ai-command-copy h2 {
            font-family: var(--font-display);
            font-size: 15px;
            font-weight: 800;
            color: var(--text-primary);
            margin-bottom: 2px;
        }

        .ai-command-copy p {
            font-size: 13px;
            color: var(--text-secondary);
            line-height: 1.4;
            max-width: none;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .ai-command-footer {
            position: relative;
            z-index: 1;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 12px;
        }

        .ai-insight-counter {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: var(--nova-glass);
            border: 1px solid var(--nova-glass-border);
            color: var(--text-secondary);
            font-size: 11.5px;
            font-weight: 600;
            padding: 6px 12px;
            border-radius: 30px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .ai-insight-counter:hover {
            border-color: var(--nova-violet);
            color: var(--text-primary);
        }

        .ai-command-footer .btn-create {
            height: 36px;
            padding: 0 14px;
            font-size: 12.5px;
            box-shadow: 0 6px 16px rgba(124, 58, 237, 0.22);
        }

        .ai-command-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn-ai-ghost {
            background: transparent;
            border: none;
            color: var(--nova-violet);
            font-size: 13.5px;
            font-weight: 700;
            cursor: pointer;
            padding: 10px 6px;
            transition: color 0.2s ease;
        }

        .btn-ai-ghost:hover {
            color: var(--nova-fuchsia);
        }

        /* Insights */
        .insights-section {
            margin-bottom: 30px;
            scroll-margin-top: 24px;
        }

        .insights-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 16px;
            margin-bottom: 18px;
        }

        .insight-card {
            background: var(--bg-card);
            border: 1px solid var(--nova-glass-border);
            border-radius: var(--az-radius-md);
            padding: 18px 20px;
            transition: all 0.25s ease;
        }

        .insight-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--nova-shadow);
        }

        .insight-chip {
            display: inline-flex;
            font-size: 10.5px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            padding: 4px 10px;
            border-radius: 30px;
            margin-bottom: 12px;
        }

        .insight-atencion .insight-chip {
            color: #B4761F;
            background: #FDF0DD;
        }

        .insight-tendencia .insight-chip {
            color: var(--nova-violet);
            background: var(--nova-glass);
        }

        .insight-logro .insight-chip {
            color: #159A79;
            background: #DFF6EC;
        }

        .insight-recomendacion .insight-chip {
            color: var(--nova-fuchsia);
            background: var(--nova-glass);
        }

        .insight-proximamente {
            opacity: 0.7;
        }

        .insight-proximamente .insight-chip {
            color: var(--text-tertiary);
            background: var(--nova-glass);
        }

        html.dark .insight-atencion .insight-chip {
            color: #FCD34D;
            background: rgba(252, 211, 77, 0.12);
        }

        html.dark .insight-logro .insight-chip {
            color: #34D399;
            background: rgba(52, 211, 153, 0.12);
        }

        .insight-course {
            font-size: 12px;
            font-weight: 700;
            color: var(--text-tertiary);
            margin-bottom: 4px;
        }

        .insight-text {
            font-size: 13.5px;
            color: var(--text-primary);
            line-height: 1.5;
            margin-bottom: 12px;
        }

        .insight-action {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: transparent;
            border: none;
            color: var(--nova-violet);
            font-size: 12.5px;
            font-weight: 700;
            cursor: pointer;
            padding: 0;
        }

        .insight-action:hover {
            color: var(--nova-fuchsia);
        }

        .dash-quote {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
            font-style: italic;
            color: var(--text-tertiary);
            padding: 4px 2px;
        }

        .dash-quote i {
            color: var(--nova-glass-border);
            font-size: 12px;
        }

        @media (max-width: 767px) {
            .dash-header {
                flex-direction: column;
                align-items: stretch;
            }

            .dash-stat-strip {
                justify-content: flex-start;
            }

            .ios-stat-grid {
                gap: 8px;
            }

            .create-menu {
                right: 0;
                left: 0;
                width: min(100%, 320px);
            }
        }

        /* ── Tarjetas de Contenido ──────────────────────────── */
        .content-grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }

        .content-card {
            background: var(--bg-card);
            backdrop-filter: blur(10px);
            border: 1px solid var(--nova-glass-border);
            border-radius: 24px;
            padding: 24px;
            transition: all 0.3s ease;
        }

        .content-card:hover {
            border-color: var(--nova-violet);
            box-shadow: var(--nova-shadow);
        }

        .card-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
        }

        .card-header i {
            width: 40px;
            height: 40px;
            background: var(--nova-gradient);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: white;
        }

        .card-header h3 {
            font-size: 16px;
            font-weight: 700;
            color: var(--text-primary);
        }

        .card-header p {
            font-size: 12px;
            color: var(--text-tertiary);
            margin-top: 2px;
        }

        .next-activity-content {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .activity-tag {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--nova-glass);
            padding: 8px 16px;
            border-radius: 40px;
            border: 1px solid var(--nova-glass-border);
            width: fit-content;
        }

        .activity-tag i {
            color: var(--nova-cyan);
            font-size: 12px;
        }

        .activity-tag span {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-primary);
        }

        .quote-card {
            background: var(--nova-gradient);
            position: relative;
            overflow: hidden;
        }

        .quote-card::before {
            content: '"';
            position: absolute;
            bottom: -20px;
            right: 20px;
            font-size: 120px;
            color: rgba(255, 255, 255, 0.1);
            font-family: serif;
        }

        .quote-text {
            font-size: 16px;
            line-height: 1.6;
            color: white;
            font-style: italic;
            margin-bottom: 15px;
            position: relative;
            z-index: 2;
        }

        .quote-author {
            font-size: 13px;
            color: rgba(255, 255, 255, 0.7);
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .quote-author::before {
            content: '';
            width: 30px;
            height: 2px;
            background: rgba(255, 255, 255, 0.3);
            border-radius: 2px;
        }

        /* ── Tarjetas de Cursos ─────────────────────────────── */
        .section-title {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 12px;
        }

        .section-title i {
            color: var(--nova-violet);
            font-size: 16px;
        }

        .section-title h2 {
            font-family: var(--font-display);
            font-size: 16px;
            font-weight: 700;
            color: var(--text-primary);
        }

        .courses-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 12px;
            margin-bottom: 14px;
        }

        .course-card {
            background: var(--bg-card);
            backdrop-filter: blur(10px);
            border: 1px solid var(--nova-glass-border);
            border-radius: 18px;
            padding: 14px 16px;
            box-shadow: var(--nova-shadow);
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .course-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--course-accent, var(--nova-gradient));
            transform: scaleX(1);
            transition: transform 0.3s ease;
        }

        .course-card:hover {
            transform: translateY(-5px);
            border-color: var(--course-accent, var(--nova-violet));
            box-shadow: 0 14px 28px -16px var(--course-accent, rgba(124, 58, 237, 0.55));
        }

        .course-card:hover::before {
            transform: scaleX(1);
        }

        .course-card-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }

        .course-card-avatar {
            width: 40px;
            height: 40px;
            background: var(--course-accent, var(--nova-gradient));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            font-weight: 700;
            color: white;
            flex-shrink: 0;
            box-shadow: 0 8px 16px -10px var(--course-accent, rgba(124, 58, 237, 0.7));
        }

        .course-card-info {
            flex: 1;
            min-width: 0;
        }

        .course-card-info h3 {
            font-size: 14px;
            font-weight: 700;
            font-family: var(--font-display);
            color: var(--text-primary);
            margin-bottom: 2px;
        }

        .course-card-info p {
            font-size: 11px;
            color: var(--text-tertiary);
        }

        .course-pending-badge {
            flex-shrink: 0;
            font-size: 10px;
            font-weight: 700;
            color: #B4761F;
            background: #FDF0DD;
            border: 1px solid rgba(180, 118, 31, 0.2);
            padding: 3px 8px;
            border-radius: 30px;
            white-space: nowrap;
        }

        html.dark .course-pending-badge {
            color: #FCD34D;
            background: rgba(252, 211, 77, 0.12);
            border-color: rgba(252, 211, 77, 0.25);
        }

        .course-progress {
            margin-bottom: 8px;
        }

        .course-progress-head {
            display: flex;
            justify-content: space-between;
            font-size: 10px;
            font-weight: 600;
            color: var(--text-tertiary);
            margin-bottom: 4px;
        }

        .course-progress-track {
            height: 5px;
            border-radius: 30px;
            background: var(--nova-glass);
            border: 1px solid var(--nova-glass-border);
            overflow: hidden;
        }

        .course-progress-fill {
            height: 100%;
            border-radius: 30px;
            background: var(--nova-gradient);
            transition: width 0.4s ease;
        }

        .course-stats {
            display: flex;
            gap: 12px;
            margin: 0;
            padding: 8px 0 0;
            border-top: 1px solid var(--nova-glass-border);
        }

        .course-stat {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 11px;
            color: var(--text-secondary);
        }

        .course-stat i {
            color: var(--nova-cyan);
        }

        .card-footer {
            display: none;
        }

        /* ── Empty State AI ─────────────────────────────────── */
        .empty-state-nova {
            background: linear-gradient(135deg, var(--nova-dark) 0%, var(--nova-medium) 100%);
            border-radius: 32px;
            padding: 50px;
            text-align: center;
            position: relative;
            overflow: hidden;
            border: 1px solid var(--nova-glass-border);
        }

        .empty-state-nova::before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at 30% 50%, var(--nova-glass-border) 0%, transparent 60%);
        }

        .ai-orb {
            width: 100px;
            height: 100px;
            margin: 0 auto 30px;
            background: var(--nova-gradient);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            animation: float 6s ease-in-out infinite;
        }

        .ai-orb::before {
            content: '';
            position: absolute;
            inset: -10px;
            border-radius: 50%;
            background: var(--nova-gradient);
            opacity: 0.3;
            filter: blur(20px);
            animation: glow-pulse 3s ease-in-out infinite;
        }

        .ai-orb i {
            font-size: 40px;
            color: white;
            position: relative;
            z-index: 2;
        }

        .empty-title {
            font-size: 28px;
            font-weight: 800;
            background: linear-gradient(135deg, var(--text-primary), var(--nova-violet));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 15px;
            position: relative;
            z-index: 2;
        }

        .empty-subtitle {
            font-size: 16px;
            color: var(--text-tertiary);
            margin-bottom: 30px;
            position: relative;
            z-index: 2;
        }

        .command-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            max-width: 600px;
            margin: 0 auto 30px;
            position: relative;
            z-index: 2;
        }

        .command-btn {
            background: var(--nova-glass);
            border: 1px solid var(--nova-glass-border);
            border-radius: 20px;
            padding: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: left;
        }

        .command-btn:hover {
            background: var(--nova-glass);
            border-color: var(--nova-violet);
            transform: translateY(-2px);
        }

        .command-btn small {
            font-size: 10px;
            color: var(--nova-cyan);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: block;
            margin-bottom: 6px;
        }

        .command-btn p {
            font-size: 13px;
            color: var(--text-primary);
            font-weight: 500;
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            position: relative;
            z-index: 2;
        }

        .btn-primary {
            background: var(--nova-gradient);
            border: none;
            padding: 14px 28px;
            border-radius: 40px;
            color: white;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
            position: relative;
            overflow: hidden;
        }

        .btn-primary::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transform: translateX(-100%);
            transition: transform 0.5s ease;
        }

        .btn-primary:hover::before {
            transform: translateX(100%);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 30px -10px var(--nova-violet);
        }

        .btn-secondary {
            background: var(--nova-glass);
            border: 1px solid var(--nova-glass-border);
            padding: 14px 28px;
            border-radius: 40px;
            color: var(--text-primary);
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn-secondary:hover {
            background: var(--nova-glass);
            border-color: var(--nova-violet);
            transform: translateY(-2px);
        }

        /* ── Vista de Curso Detallado ───────────────────────── */
        .course-detail-header {
            display: flex;
            align-items: flex-start;
            gap: 20px;
            margin-bottom: 30px;
        }

        .back-btn {
            width: 44px;
            height: 44px;
            background: var(--bg-card);
            border: 1px solid var(--nova-glass-border);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .back-btn:hover {
            background: var(--nova-glass);
            color: var(--nova-violet);
            transform: translateX(-3px);
        }

        .course-detail-title {
            flex: 1;
        }

        .course-detail-title h1 {
            font-size: 28px;
            font-weight: 800;
            background: linear-gradient(135deg, var(--text-primary), var(--nova-violet));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 6px;
        }

        .course-detail-meta {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .meta-badge {
            background: var(--nova-glass);
            padding: 4px 12px;
            border-radius: 30px;
            font-size: 12px;
            color: var(--text-primary);
            border: 1px solid var(--nova-glass-border);
        }

        .action-badge {
            display: flex;
            align-items: center;
            gap: 6px;
            background: var(--nova-glass);
            padding: 6px 14px;
            border-radius: 30px;
            color: var(--nova-cyan);
            font-size: 13px;
            font-weight: 500;
        }

        .panel-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }

        .panel-card {
            background: var(--bg-card);
            backdrop-filter: blur(10px);
            border: 1px solid var(--nova-glass-border);
            border-radius: 24px;
            overflow: hidden;
        }

        .panel-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 18px 20px;
            border-bottom: 1px solid var(--nova-glass-border);
        }

        .panel-header h3 {
            font-size: 16px;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .panel-header h3 i {
            color: var(--nova-violet);
        }

        .panel-count {
            background: var(--nova-glass);
            color: var(--text-primary);
            font-size: 12px;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 30px;
            border: 1px solid var(--nova-glass-border);
        }

        .panel-link {
            color: var(--nova-cyan);
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: gap 0.2s ease;
        }

        .panel-link:hover {
            gap: 8px;
            color: var(--nova-fuchsia);
        }

        .student-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 20px;
            border-bottom: 1px solid var(--nova-glass-border);
            transition: background 0.2s ease;
        }

        .student-item:hover {
            background: var(--nova-glass);
        }

        .student-index {
            width: 28px;
            height: 28px;
            background: var(--nova-glass);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            color: var(--text-tertiary);
        }

        .student-name {
            flex: 1;
            font-size: 14px;
            color: var(--text-primary);
            font-weight: 500;
        }

        .activity-item {
            border-bottom: 1px solid var(--nova-glass-border);
        }

        .activity-header {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 15px 20px;
            cursor: pointer;
            transition: background 0.2s ease;
        }

        .activity-header:hover {
            background: var(--nova-glass);
        }

        .activity-type-indicator {
            width: 4px;
            height: 30px;
            border-radius: 4px;
        }

        .activity-info {
            flex: 1;
        }

        .activity-title {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 4px;
        }

        .activity-meta {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .activity-type-badge {
            font-size: 10px;
            padding: 2px 8px;
            border-radius: 30px;
            background: var(--nova-glass);
            color: var(--nova-violet);
        }

        .activity-date {
            font-size: 10px;
            color: var(--text-secondary);
            font-weight: 600;
        }

        .activity-weight {
            font-size: 11px;
            font-weight: 700;
            color: var(--nova-violet);
        }

        .acum-badge {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            border: 1px solid color-mix(in srgb, var(--nova-violet) 28%, transparent);
            background: color-mix(in srgb, var(--nova-violet) 10%, var(--bg-card));
            padding: 4px 10px;
            font-size: 11px;
            font-weight: 700;
            color: var(--nova-violet);
            white-space: nowrap;
        }

        html.dark .acum-badge {
            border-color: color-mix(in srgb, var(--nova-cyan) 35%, transparent);
            background: color-mix(in srgb, var(--nova-cyan) 12%, transparent);
            color: #f0abfc;
        }

        .activity-chevron {
            color: var(--text-tertiary);
            transition: transform 0.3s ease;
        }

        .activity-body {
            padding: 0 20px 20px 56px;
        }

        .activity-description {
            font-size: 13px;
            color: var(--text-secondary);
            line-height: 1.5;
            margin-bottom: 15px;
        }

        .activity-description.markdown-body :where(p, ul, ol) {
            margin: 0 0 0.65em;
        }

        .activity-description.markdown-body ul,
        .activity-description.markdown-body ol {
            padding-left: 1.25rem;
        }

        .activity-description.markdown-body strong {
            color: var(--text-primary);
            font-weight: 700;
        }

        /* ── Lesson Section Cards ──────────────────────────────────── */
        .lesson-sections {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 4px;
        }

        .lesson-section {
            border-radius: 10px;
            padding: 10px 14px;
            transition: opacity .2s;
        }

        .lesson-section-title {
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .1em;
            margin-bottom: 6px;
        }

        .lesson-section-content {
            font-size: 13px;
            color: var(--text-secondary);
            line-height: 1.6;
        }

        .lesson-section-content p { margin: 0 0 .45em; }
        .lesson-section-content p:last-child { margin-bottom: 0; }
        .lesson-section-content ul,
        .lesson-section-content ol { padding-left: 1.2rem; margin: 0 0 .45em; }
        .lesson-section-content strong { color: var(--text-primary); font-weight: 700; }

        /* ── Modal Phase Cards (Inicio / Desarrollo / Cierre) ─────── */
        .phase-cards-stack {
            display: flex;
            flex-direction: column;
            gap: 14px;
            margin-bottom: 4px;
        }

        .phase-card {
            border-radius: 18px;
            overflow: hidden;
            border: 1px solid transparent;
            transition: box-shadow 0.25s ease, border-color 0.25s ease, transform 0.2s ease;
        }

        .phase-card:hover:not(.phase-card--editing) {
            transform: translateY(-1px);
        }

        .phase-card--inicio {
            background: linear-gradient(145deg, rgba(124, 58, 237, 0.11) 0%, rgba(124, 58, 237, 0.03) 55%, rgba(255, 255, 255, 0.02) 100%);
            border-color: rgba(124, 58, 237, 0.28);
            box-shadow: 0 8px 28px rgba(124, 58, 237, 0.08);
        }

        .phase-card--desarrollo {
            background: linear-gradient(145deg, rgba(6, 182, 212, 0.11) 0%, rgba(34, 197, 94, 0.06) 55%, rgba(255, 255, 255, 0.02) 100%);
            border-color: rgba(6, 182, 212, 0.28);
            box-shadow: 0 8px 28px rgba(6, 182, 212, 0.07);
        }

        .phase-card--cierre {
            background: linear-gradient(145deg, rgba(59, 130, 246, 0.1) 0%, rgba(236, 72, 153, 0.06) 55%, rgba(255, 255, 255, 0.02) 100%);
            border-color: rgba(59, 130, 246, 0.26);
            box-shadow: 0 8px 28px rgba(59, 130, 246, 0.07);
        }

        html.dark .phase-card--inicio {
            background: linear-gradient(145deg, rgba(108, 74, 224, 0.18) 0%, rgba(108, 74, 224, 0.05) 60%, rgba(12, 18, 37, 0.4) 100%);
        }

        html.dark .phase-card--desarrollo {
            background: linear-gradient(145deg, rgba(59, 201, 219, 0.16) 0%, rgba(34, 197, 94, 0.08) 60%, rgba(12, 18, 37, 0.4) 100%);
        }

        html.dark .phase-card--cierre {
            background: linear-gradient(145deg, rgba(96, 165, 250, 0.14) 0%, rgba(236, 72, 153, 0.08) 60%, rgba(12, 18, 37, 0.4) 100%);
        }

        .phase-card--editing {
            box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.25), 0 12px 32px rgba(0, 0, 0, 0.12);
        }

        .phase-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 12px 16px 10px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
        }

        :root:not(.dark) .phase-card-header {
            border-bottom-color: rgba(15, 23, 42, 0.06);
        }

        .phase-card-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.12em;
            text-transform: uppercase;
        }

        .phase-card--inicio .phase-card-badge { color: #7C3AED; }
        .phase-card--desarrollo .phase-card-badge { color: #0891B2; }
        .phase-card--cierre .phase-card-badge { color: #3B82F6; }

        html.dark .phase-card--inicio .phase-card-badge { color: #A78BFA; }
        html.dark .phase-card--desarrollo .phase-card-badge { color: #22D3EE; }
        html.dark .phase-card--cierre .phase-card-badge { color: #60A5FA; }

        .phase-card-badge i {
            font-size: 10px;
            opacity: 0.85;
        }

        .phase-card-actions {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-shrink: 0;
        }

        .phase-edit-btn {
            width: 32px;
            height: 32px;
            border-radius: 10px;
            border: 1px solid var(--nova-glass-border);
            background: rgba(255, 255, 255, 0.04);
            color: var(--text-secondary);
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            transition: all 0.15s ease;
        }

        .phase-edit-btn:hover {
            color: var(--nova-violet);
            border-color: rgba(124, 58, 237, 0.35);
            background: rgba(124, 58, 237, 0.08);
        }

        .phase-save-btn,
        .phase-cancel-btn {
            padding: 6px 12px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.15s ease;
            border: 1px solid transparent;
        }

        .phase-save-btn {
            background: linear-gradient(135deg, rgba(124, 58, 237, 0.9), rgba(6, 182, 212, 0.85));
            color: #fff;
            border-color: rgba(255, 255, 255, 0.1);
        }

        .phase-save-btn:hover:not(:disabled) {
            filter: brightness(1.08);
            transform: translateY(-1px);
        }

        .phase-save-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .phase-cancel-btn {
            background: transparent;
            color: var(--text-secondary);
            border-color: var(--nova-glass-border);
        }

        .phase-cancel-btn:hover {
            background: rgba(239, 68, 68, 0.06);
            color: #ef4444;
            border-color: rgba(239, 68, 68, 0.25);
        }

        .phase-card-body {
            padding: 14px 16px 16px;
            font-size: 13.5px;
            line-height: 1.65;
            color: var(--text-secondary);
        }

        .phase-card-body :where(p, ul, ol) {
            margin: 0 0 0.55em;
        }

        .phase-card-body ul,
        .phase-card-body ol {
            padding-left: 1.25rem;
        }

        .phase-card-body strong {
            color: var(--text-primary);
            font-weight: 700;
        }

        .phase-card-body p:last-child,
        .phase-card-body ul:last-child,
        .phase-card-body ol:last-child {
            margin-bottom: 0;
        }

        .phase-empty {
            margin: 0;
            font-style: italic;
            color: var(--text-tertiary);
            font-size: 13px;
        }

        .phase-card-textarea {
            width: 100%;
            min-height: 120px;
            resize: vertical;
            border: none;
            border-top: 1px solid rgba(255, 255, 255, 0.06);
            background: rgba(0, 0, 0, 0.12);
            color: var(--text-primary);
            padding: 14px 16px 16px;
            font-size: 13.5px;
            line-height: 1.6;
            font-family: inherit;
            outline: none;
        }

        :root:not(.dark) .phase-card-textarea {
            background: rgba(255, 255, 255, 0.65);
            border-top-color: rgba(15, 23, 42, 0.06);
        }

        .phase-card-textarea:focus {
            background: rgba(124, 58, 237, 0.04);
        }

        .phase-card--inicio .phase-card-textarea:focus { box-shadow: inset 0 0 0 1px rgba(124, 58, 237, 0.25); }
        .phase-card--desarrollo .phase-card-textarea:focus { box-shadow: inset 0 0 0 1px rgba(6, 182, 212, 0.25); }
        .phase-card--cierre .phase-card-textarea:focus { box-shadow: inset 0 0 0 1px rgba(59, 130, 246, 0.25); }

        .activity-description.markdown-body p:last-child {
            margin-bottom: 0;
        }

        .activity-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .action-btn {
            padding: 8px 16px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 6px;
            border: 1px solid transparent;
        }

        .action-btn.primary {
            background: var(--nova-gradient);
            color: white;
        }

        .action-btn.primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px -8px var(--nova-violet);
        }

        .action-btn.secondary {
            background: var(--nova-glass);
            border-color: var(--nova-glass-border);
            color: var(--text-primary);
        }

        .action-btn.secondary:hover {
            background: var(--nova-glass);
            border-color: var(--nova-violet);
        }

        .action-btn.warning {
            background: rgba(239, 68, 68, 0.1);
            border-color: rgba(239, 68, 68, 0.3);
            color: #ef4444;
        }

        .action-btn.warning:hover {
            background: rgba(239, 68, 68, 0.2);
        }

        .ai-context-bar {
            background: var(--bg-card);
            border: 1px solid var(--nova-glass-border);
            border-radius: 20px;
            padding: 16px 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            margin-top: 20px;
        }

        .ai-context-icon {
            width: 40px;
            height: 40px;
            background: var(--nova-gradient);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }

        .ai-context-text {
            flex: 1;
        }

        .ai-context-text p {
            font-size: 13px;
            color: var(--text-secondary);
        }

        .ai-context-text strong {
            color: var(--nova-cyan);
        }

        /* ── Calendario ─────────────────────────────────────── */
        .calendar-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            margin-bottom: 25px;
            gap: 16px;
            flex-wrap: wrap;
            padding-right: 0;
        }

        .calendar-title h2 {
            font-size: 24px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 4px;
        }

        .calendar-title p {
            color: var(--text-tertiary);
            font-size: 14px;
        }

        .calendar-nav {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            justify-content: flex-end;
            max-width: 100%;
        }

        .calendar-nav-btn--wide {
            width: auto;
            min-width: 40px;
            height: 40px;
            padding: 0 14px;
            gap: 8px;
            font-size: 12px;
            font-weight: 700;
            color: var(--text-primary);
        }

        .pedagogy-btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            height: 44px;
            padding: 0 14px 0 8px;
            border-radius: 16px;
            border: 1px solid color-mix(in srgb, var(--nova-violet) 35%, var(--nova-glass-border));
            background: linear-gradient(135deg, color-mix(in srgb, var(--nova-violet) 16%, var(--bg-card)), var(--bg-card));
            color: var(--text-primary);
            cursor: pointer;
            box-shadow: 0 8px 20px -12px color-mix(in srgb, var(--nova-violet) 70%, transparent);
            transition: transform 0.2s ease, border-color 0.2s ease;
        }
        .pedagogy-btn:hover {
            transform: translateY(-1px);
            border-color: var(--nova-violet);
        }
        .pedagogy-btn .pedagogy-emoji {
            width: 30px;
            height: 30px;
            border-radius: 10px;
            display: grid;
            place-items: center;
            background: var(--nova-gradient);
            font-size: 16px;
        }
        .pedagogy-btn small {
            display: block;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .04em;
            text-transform: uppercase;
            color: var(--nova-violet);
            line-height: 1;
        }
        .pedagogy-btn strong {
            display: block;
            font-size: 13px;
            font-weight: 800;
            line-height: 1.15;
        }

        .calendar-nav-btn {
            width: 40px;
            height: 40px;
            background: var(--bg-card);
            border: 1px solid var(--nova-glass-border);
            border-radius: 14px;
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .calendar-nav-btn:hover {
            background: var(--nova-glass);
            color: var(--nova-violet);
            border-color: var(--nova-violet);
        }

        .today-btn {
            background: var(--nova-glass);
            border: 1px solid var(--nova-glass-border);
            color: var(--text-primary);
            font-weight: 600;
            font-size: 13px;
            padding: 0 20px;
        }

        .calendar-stats {
            background: var(--nova-glass);
            color: var(--nova-cyan);
            padding: 4px 12px;
            border-radius: 30px;
            font-size: 13px;
            margin-left: 15px;
        }

        .calendar-grade-filters {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
            margin-left: 4px;
        }

        .calendar-grade-chip {
            border: 1px solid var(--nova-glass-border);
            background: var(--bg-secondary);
            color: var(--text-secondary);
            font-size: 11px;
            font-weight: 700;
            padding: 6px 10px;
            border-radius: 999px;
            cursor: pointer;
            transition: all 0.15s ease;
        }

        .calendar-grade-chip.active {
            color: #fff;
            border-color: transparent;
            box-shadow: 0 8px 18px -10px currentColor;
        }

        .calendar-grid {
            background: var(--bg-card);
            backdrop-filter: blur(10px);
            border: 1px solid var(--nova-glass-border);
            border-radius: 24px;
            padding: 20px;
            min-height: 420px;
        }

        .weekdays {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 8px;
            margin-bottom: 12px;
        }

        .weekday {
            text-align: center;
            font-size: 12px;
            font-weight: 600;
            color: var(--text-tertiary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .calendar-days {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 8px;
        }

        .calendar-day {
            background: var(--bg-secondary);
            border: 1px solid var(--nova-glass-border);
            border-radius: 16px;
            min-height: 118px;
            max-height: 196px;
            padding: 8px;
            position: relative;
            transition: all 0.2s ease;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            cursor: pointer;
        }

        .calendar-day.has-events {
            border-color: color-mix(in srgb, var(--nova-violet) 18%, var(--nova-glass-border));
        }

        .calendar-day:hover {
            border-color: var(--nova-violet);
            background: var(--nova-glass);
        }

        .calendar-day.today {
            border-color: var(--nova-fuchsia);
            background: var(--nova-glass);
        }

        .calendar-day.empty {
            background: transparent;
            border-color: transparent;
        }

        .day-number {
            position: absolute;
            top: 6px;
            right: 8px;
            font-size: 12px;
            font-weight: 600;
            color: var(--text-tertiary);
        }

        .today .day-number {
            color: var(--nova-fuchsia);
            font-weight: 800;
        }

        .day-content {
            margin-top: 20px;
            display: flex;
            flex-direction: column;
            gap: 4px;
            min-height: 0;
            overflow-y: auto;
            overflow-x: hidden;
            padding-right: 2px;
            -webkit-overflow-scrolling: touch;
        }
        .day-content::-webkit-scrollbar { width: 4px; }
        .day-content::-webkit-scrollbar-thumb {
            background: color-mix(in srgb, var(--nova-violet) 45%, transparent);
            border-radius: 999px;
        }

        .day-grade-summary {
            margin-top: 24px;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .day-grade-pill {
            display: none;
        }

        .cal-grade-event {
            display: flex;
            flex-direction: column;
            gap: 1px;
            width: 100%;
            border: 0;
            border-radius: 9px;
            padding: 5px 6px;
            text-align: left;
            color: #fff;
            cursor: pointer;
            box-shadow: 0 6px 14px -10px rgba(15, 23, 42, 0.55);
        }

        .cal-grade-event-line {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 9px;
            font-weight: 800;
            letter-spacing: .04em;
            text-transform: uppercase;
            opacity: .92;
        }

        .cal-grade-event-title {
            font-size: 10px;
            font-weight: 700;
            line-height: 1.25;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .cal-grade-event.has-director-notes {
            outline: 2px solid #f59e0b;
            outline-offset: -2px;
        }

        .cal-event {
            font-size: 10px;
            padding: 4px 6px;
            border-radius: 8px;
            color: white;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .cal-event.clase {
            background: linear-gradient(135deg, #6C4AE0, #8B5CF6);
        }

        .cal-event.actividad {
            background: linear-gradient(135deg, #C455ED, #E879F9);
        }

        .cal-event.homework {
            background: linear-gradient(135deg, #3BC9DB, #22D3EE);
        }

        .cal-event.has-director-notes {
            outline: 2px solid #f59e0b;
            outline-offset: -2px;
            box-shadow: 0 0 8px rgba(245, 158, 11, 0.3);
        }

        .cal-event:hover {
            transform: scale(1.02);
            filter: brightness(1.1);
        }

        .more-events {
            font-size: 9px;
            color: var(--nova-cyan);
            cursor: pointer;
            margin-top: 2px;
        }

        .day-agenda {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .day-agenda-row {
            display: grid;
            grid-template-columns: 64px 1fr;
            gap: 10px;
            align-items: start;
        }

        .day-agenda-hour {
            font-size: 11px;
            font-weight: 800;
            color: var(--text-tertiary);
            padding-top: 10px;
        }

        .day-agenda-cards {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .day-agenda-empty {
            font-size: 12px;
            color: var(--text-tertiary);
            border: 1px dashed var(--nova-glass-border);
            border-radius: 12px;
            padding: 8px 10px;
        }

        .day-agenda-card {
            border: 1px solid var(--nova-glass-border);
            border-left: 4px solid var(--grade-accent, #7C3AED);
            border-radius: 14px;
            background: var(--nova-glass);
            padding: 10px 12px;
            width: 100%;
            text-align: left;
            color: inherit;
            cursor: pointer;
        }

        .day-agenda-card:hover {
            border-color: rgba(124, 58, 237, 0.4);
            transform: translateY(-1px);
        }

        .day-agenda-card-top {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 4px;
        }

        .day-agenda-time {
            font-size: 11px;
            font-weight: 700;
            color: var(--grade-accent, #7C3AED);
        }

        .day-agenda-title {
            margin: 0 0 2px;
            font-size: 13px;
            font-weight: 700;
            color: var(--text-primary);
        }

        .day-agenda-meta {
            margin: 0;
            font-size: 11px;
            color: var(--text-tertiary);
        }

        .day-dots {
            display: none;
        }

        .day-dot {
            width: 5px;
            height: 5px;
            border-radius: 99px;
            flex-shrink: 0;
        }

        .day-dot.clase { background: #8B5CF6; }
        .day-dot.actividad { background: #C455ED; }
        .day-dot.homework { background: #22D3EE; }

        .day-more-count {
            font-size: 9px;
            font-weight: 800;
            color: var(--text-tertiary);
            line-height: 1;
        }

        /* ── Modales ────────────────────────────────────────── */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(10px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            padding: 16px;
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
        }

        .modal-nova {
            background: var(--bg-card);
            backdrop-filter: blur(20px);
            border: 1px solid var(--nova-glass-border);
            border-radius: 32px;
            width: 100%;
            max-width: 500px;
            max-height: min(90vh, 860px);
            overflow: hidden;
            box-shadow: var(--nova-shadow);
            display: flex;
            flex-direction: column;
        }

        .modal-header {
            padding: 24px;
            border-bottom: 1px solid var(--nova-glass-border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-shrink: 0;
        }

        .modal-header h3 {
            font-size: 20px;
            font-weight: 700;
            color: var(--text-primary);
        }

        .modal-close {
            width: 36px;
            height: 36px;
            background: var(--nova-glass);
            border: 1px solid var(--nova-glass-border);
            border-radius: 12px;
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .modal-close:hover {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            border-color: #ef4444;
        }

        .modal-body {
            padding: 24px;
            overflow-y: auto;
            overflow-x: hidden;
            min-height: 0;
            flex: 1;
            -webkit-overflow-scrolling: touch;
        }

        .modal-footer {
            padding: 20px 24px;
            border-top: 1px solid var(--nova-glass-border);
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            flex-shrink: 0;
        }

        .tareas-box {
            margin-top: 18px;
            border: 1px solid var(--nova-glass-border);
            background: var(--nova-glass);
            border-radius: 18px;
            padding: 14px;
        }

        .tareas-title {
            font-size: 12px;
            font-weight: 700;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: .08em;
            margin-bottom: 10px;
        }

        .tarea-row {
            padding: 10px 12px;
            border-radius: 12px;
            border: 1px solid var(--nova-glass-border);
            background: rgba(255, 255, 255, 0.03);
            margin-bottom: 8px;
        }

        .tarea-row:last-child {
            margin-bottom: 0;
        }

        .modal-meta-row {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 14px 0;
            border-bottom: 1px solid var(--nova-glass-border);
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .modal-meta-item {
            font-size: 12px;
            color: var(--text-secondary);
            font-weight: 500;
        }

        .modal-meta-item i {
            margin-right: 5px;
        }

        .modal-section-label {
            font-size: 12px;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .modal-prose {
            color: var(--text-secondary);
            font-size: 13.5px;
            line-height: 1.7;
            margin-bottom: 0;
        }

        .modal-prose-empty {
            color: var(--text-tertiary);
            font-style: italic;
            margin: 0;
        }

        .modal-ai-lab {
            margin: 0 28px 16px;
            border-radius: 16px;
            border: 1px solid color-mix(in srgb, var(--nova-violet) 22%, transparent);
            background: color-mix(in srgb, var(--nova-violet) 6%, var(--bg-card));
            padding: 16px 18px;
            box-shadow: var(--nova-shadow);
        }

        .modal-ai-lab-header {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 14px;
        }

        .modal-ai-lab-header i {
            font-size: 12px;
            color: var(--nova-violet);
        }

        .modal-ai-lab-header span {
            font-size: 10px;
            font-weight: 700;
            color: var(--nova-violet);
            text-transform: uppercase;
            letter-spacing: 0.1em;
        }

        .modal-ai-lab-header hr {
            flex: 1;
            height: 1px;
            border: none;
            background: linear-gradient(90deg, rgba(108,74,224,0.3), transparent);
            margin-left: 6px;
        }

        .modal-ai-grid {
            display: grid;
            grid-template-columns: repeat(1, minmax(0, 1fr));
            gap: 12px;
        }

        @media (min-width: 640px) {
            .modal-ai-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        .modal-ai-btn {
            padding: 8px 14px;
            border-radius: 8px;
            border: 1px solid var(--nova-glass-border);
            background: var(--bg-secondary);
            color: var(--text-primary);
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.15s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            width: 100%;
            text-align: left;
        }

        .modal-ai-btn:hover {
            background: color-mix(in srgb, var(--nova-violet) 10%, var(--bg-secondary));
            border-color: color-mix(in srgb, var(--nova-violet) 35%, transparent);
        }

        .modal-footer-btn {
            padding: 8px 16px;
            border-radius: 10px;
            border: 1px solid var(--nova-glass-border);
            background: transparent;
            color: var(--text-secondary);
            font-size: 12px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.15s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .modal-footer-btn:hover {
            background: var(--nova-glass);
            color: var(--text-primary);
        }

        .modal-footer-btn.primary {
            border: none;
            background: var(--nova-gradient);
            color: white;
            font-weight: 600;
            padding: 8px 18px;
        }

        .modal-footer-btn.primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 20px -8px var(--nova-violet);
        }

        .modal-footer-btn.danger {
            border-color: rgba(239,68,68,0.2);
            background: rgba(239,68,68,0.06);
            color: #ef4444;
        }

        .modal-footer-btn.danger:hover {
            background: rgba(239,68,68,0.12);
            border-color: rgba(239,68,68,0.4);
        }

        .mini-modal {
            width: 100%;
            max-width: 540px;
            background: var(--bg-card);
            border: 1px solid var(--nova-glass-border);
            border-radius: 24px;
            box-shadow: var(--nova-shadow);
            overflow: hidden;
        }

        .grades-slideover-wrap {
            position: fixed;
            inset: 0;
            z-index: 1100;
            display: flex;
            justify-content: flex-end;
        }

        .grades-slideover-backdrop {
            position: absolute;
            inset: 0;
            background: rgba(5, 8, 18, 0.72);
            backdrop-filter: blur(5px);
        }

        .grades-slideover-panel {
            position: relative;
            width: min(720px, 100%);
            height: 100%;
            background: var(--bg-card);
            border-left: 1px solid var(--nova-glass-border);
            box-shadow: -20px 0 60px rgba(0, 0, 0, 0.25);
            display: flex;
            flex-direction: column;
            animation: slide-up 0.25s ease;
        }

        .grades-slideover-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 22px 24px 16px;
            border-bottom: 1px solid var(--nova-glass-border);
            background: color-mix(in srgb, var(--nova-violet) 8%, var(--bg-card));
        }

        .grades-slideover-eyebrow {
            font-size: 11px;
            font-weight: 700;
            color: var(--nova-cyan);
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin: 0 0 6px;
        }

        .grades-slideover-header h3 {
            margin: 0;
            font-size: 20px;
            color: var(--text-primary);
        }

        .grades-slideover-subtitle {
            margin: 6px 0 0;
            color: var(--text-secondary);
            font-size: 13px;
        }

        .grades-slideover-meta {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            padding: 14px 24px;
            border-bottom: 1px solid var(--nova-glass-border);
            background: rgba(255, 255, 255, 0.02);
        }

        .meta-chip {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            border: 1px solid var(--nova-glass-border);
            border-radius: 999px;
            padding: 7px 12px;
            font-size: 12px;
            color: var(--text-primary);
            background: var(--bg-secondary);
        }

        .meta-chip strong {
            color: var(--nova-violet);
        }

        .attendance-status-btn {
            border: 1px solid var(--nova-glass-border);
            background: var(--bg-card);
            color: var(--text-secondary);
            border-radius: 999px;
            padding: 6px 12px;
            font-weight: 700;
            font-size: 12px;
            cursor: pointer;
            transition: background .15s ease, color .15s ease, border-color .15s ease;
        }

        .attendance-status-btn:hover {
            border-color: var(--nova-violet);
        }

        .attendance-status-btn.is-active.present {
            background: #0F766E;
            color: #fff;
            border-color: transparent;
        }

        .attendance-status-btn.is-active.tardy {
            background: #B45309;
            color: #fff;
            border-color: transparent;
        }

        .attendance-status-btn.is-active.absent {
            background: var(--nova-fuchsia);
            color: #fff;
            border-color: transparent;
        }

        .attendance-reason-select {
            border: 1px solid var(--nova-glass-border);
            background: var(--bg-card);
            color: var(--text-primary);
            border-radius: 10px;
            padding: 6px 10px;
            font-size: 12px;
        }

        .grades-slideover-body {
            flex: 1;
            overflow: auto;
            padding: 18px 24px;
        }

        .grades-table-wrap {
            border: 1px solid var(--nova-glass-border);
            border-radius: 18px;
            overflow: hidden;
            background: var(--bg-secondary);
        }

        .grades-table {
            width: 100%;
            border-collapse: collapse;
        }

        .grades-table th,
        .grades-table td {
            padding: 12px 14px;
            border-bottom: 1px solid var(--nova-glass-border);
            color: var(--text-primary);
            font-size: 13px;
            text-align: left;
        }

        .grades-table th {
            background: color-mix(in srgb, var(--nova-violet) 10%, var(--bg-secondary));
            color: var(--text-secondary);
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            font-weight: 700;
        }

        .grades-table tbody tr:last-child td {
            border-bottom: 0;
        }

        .student-grade-cell {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .student-avatar {
            width: 32px;
            height: 32px;
            border-radius: 999px;
            border: 1px solid color-mix(in srgb, var(--nova-violet) 35%, transparent);
            color: var(--nova-violet);
            background: color-mix(in srgb, var(--nova-violet) 12%, var(--bg-secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
        }

        html.dark .student-avatar {
            color: #c4b5fd;
            border-color: color-mix(in srgb, var(--nova-cyan) 35%, transparent);
            background: color-mix(in srgb, var(--nova-violet) 22%, transparent);
        }

        .grade-input-wrap {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .grade-input {
            width: 96px;
            background: var(--nova-glass);
            border: 1px solid var(--nova-glass-border);
            border-radius: 11px;
            padding: 8px 10px;
            color: var(--text-primary);
            font-size: 13px;
        }

        .grade-input:focus {
            outline: none;
            border-color: var(--nova-cyan);
            box-shadow: 0 0 0 3px rgba(59, 201, 219, 0.15);
        }

        .grade-saved-icon {
            color: #2dd4bf;
            font-size: 14px;
            filter: drop-shadow(0 0 8px rgba(45, 212, 191, 0.5));
        }

        .grades-inline-error {
            border: 1px solid rgba(248, 113, 113, 0.3);
            color: #fecaca;
            background: rgba(127, 29, 29, 0.25);
            border-radius: 12px;
            font-size: 13px;
            padding: 12px 14px;
        }

        .publish-confirm-box {
            border: 1px solid rgba(59, 201, 219, 0.25);
            background: rgba(17, 94, 89, 0.15);
            border-radius: 14px;
            padding: 12px 14px;
            margin-bottom: 14px;
            color: var(--text-secondary);
            font-size: 13px;
        }

        .publish-confirm-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 10px;
        }

        .grade-status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border-radius: 999px;
            padding: 5px 10px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .03em;
            border: 1px solid transparent;
        }

        .grade-status-pending {
            color: #f59e0b;
            background: rgba(245, 158, 11, 0.14);
            border-color: rgba(245, 158, 11, 0.35);
        }

        .grade-status-saving {
            color: #93c5fd;
            background: rgba(59, 130, 246, 0.15);
            border-color: rgba(59, 130, 246, 0.35);
        }

        .grade-status-saved {
            color: #34d399;
            background: rgba(16, 185, 129, 0.14);
            border-color: rgba(45, 212, 191, 0.45);
        }

        .grade-status-published {
            color: #67e8f9;
            background: rgba(8, 145, 178, 0.15);
            border-color: rgba(34, 211, 238, 0.45);
        }

        .grade-status-error {
            color: #fca5a5;
            background: rgba(220, 38, 38, 0.15);
            border-color: rgba(248, 113, 113, 0.4);
        }

        .grades-slideover-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            padding: 14px 24px 20px;
            border-top: 1px solid var(--nova-glass-border);
            background: rgba(255, 255, 255, 0.02);
        }

        /* ── Skeleton ───────────────────────────────────────── */
        .skeleton-nova {
            background: linear-gradient(90deg, 
                var(--bg-secondary) 25%, 
                var(--nova-glass) 50%, 
                var(--bg-secondary) 75%
            );
            background-size: 200% 100%;
            animation: shimmer 1.5s infinite;
            border-radius: 16px;
        }
    </style>
    @include('partials.theme-system')
    <link rel="manifest" href="/manifest.json">
</head>
<body>

    <!-- Fondo dinámico Nova -->
    <div class="nova-bg">
        <div class="nova-bg-orb"></div>
        <div class="nova-bg-orb"></div>
        <div class="nova-bg-orb"></div>
        <div class="nova-grid"></div>
    </div>

<div id="hub-root" x-data="teacherHub()" x-init="init()">

    {{-- Móvil: overlay + barra superior con menú hamburguesa --}}
    <div
        x-show="sidebarOpen"
        x-transition.opacity
        @click="sidebarOpen = false"
        class="fixed inset-0 z-[100] bg-black/55 backdrop-blur-[2px] md:hidden"
        x-cloak
        aria-hidden="true"
    ></div>

    <header
        class="fixed top-0 left-0 right-0 z-[120] flex h-14 items-center gap-3 border-b px-4 md:hidden"
        style="padding-top: max(0.5rem, env(safe-area-inset-top)); border-color: var(--nova-glass-border); background: var(--bg-secondary); backdrop-filter: blur(12px);"
    >
        <button
            type="button"
            @click="sidebarOpen = !sidebarOpen"
            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border transition hover:opacity-90"
            style="border-color: var(--nova-glass-border); color: var(--text-primary); background: var(--nova-glass);"
            :aria-expanded="sidebarOpen"
            aria-controls="hub-sidebar"
            aria-label="Menú de navegación"
        >
            <i class="fa-solid text-lg" :class="sidebarOpen ? 'fa-xmark' : 'fa-bars'"></i>
        </button>
        <span class="min-w-0 truncate text-sm font-bold" style="color: var(--text-primary);">AulaSync</span>
    </header>

    {{-- SIDEBAR NOVA --}}
    <aside
        id="hub-sidebar"
        :class="{ 'hub-sidebar-open': sidebarOpen }"
    >
    <div class="sidebar-brand" style="position: relative;">
    <div style="display: flex; align-items: center; justify-content: space-between; padding: 14px 16px 0 16px; position: relative; z-index: 10;">
        <button @click="loadWelcome()" class="brand-button" style="width: auto; flex: 1; padding: 0;">
            <div class="brand-icon">
                <i class="fa-solid fa-robot"></i>
            </div>
            <div class="brand-text">
                <div class="brand-title">AulaSync</div>
                <div class="brand-subtitle">
                    <i class="fa-solid fa-circle"></i>
                    <span>{{ auth()->user()->name }}</span>
                </div>
            </div>
        </button>
        {{-- Theme palette picker + switch claro/oscuro --}}
        <div class="theme-toggle-wrap" style="display:flex;align-items:center;gap:8px;">
            @include('components.theme-toggle')
            <button @click.stop="showThemePicker = !showThemePicker" class="theme-toggle" title="Cambiar colores del tema" style="position: relative; z-index: 20;">
                <i class="fa-solid fa-palette"></i>
            </button>
        </div>
    </div>
    <div class="user-panel" style="position: relative; z-index: 5; padding-bottom: 8px;"></div>
</div>

        <nav class="nav-section">
            <button @click="loadWelcome()" :class="{ active: view === 'welcome' }" class="nav-item">
                <i class="fa-solid fa-house-chimney"></i>
                <span>Inicio</span>
            </button>
            <button @click="loadCalendar()" :class="{ active: view === 'calendar' }" class="nav-item">
                <i class="fa-solid fa-calendar-days"></i>
                <span>Calendario</span>
                <template x-if="calendarData?.total_activities > 0">
                    <span class="nav-badge" x-text="calendarData.total_activities"></span>
                </template>
            </button>
            <a href="{{ route('historial') }}" class="nav-item">
                <i class="fa-solid fa-folder-open"></i>
                <span>Planificaciones</span>
                <i class="fa-solid fa-arrow-up-right-from-square" style="margin-left: auto; font-size: 10px; opacity: 0.4;"></i>
            </a>
            <a href="{{ route('intelligence.index') }}" class="nav-item">
                <i class="fa-solid fa-brain"></i>
                <span>Inteligencia</span>
                <i class="fa-solid fa-arrow-up-right-from-square" style="margin-left: auto; font-size: 10px; opacity: 0.4;"></i>
            </a>
            <a href="{{ route('teacher.activities.index') }}" class="nav-item">
                <i class="fa-solid fa-clipboard-list"></i>
                <span>Actividades</span>
                <i class="fa-solid fa-arrow-up-right-from-square" style="margin-left: auto; font-size: 10px; opacity: 0.4;"></i>
            </a>
            <a href="{{ route('teacher.evaluations.index') }}" class="nav-item">
                <i class="fa-solid fa-file-pen"></i>
                <span>Evaluaciones</span>
                <i class="fa-solid fa-arrow-up-right-from-square" style="margin-left: auto; font-size: 10px; opacity: 0.4;"></i>
            </a>
            <a href="{{ route('teacher.assessment.index') }}" class="nav-item">
                <i class="fa-solid fa-diagram-project"></i>
                <span>Estrategia de Evaluación</span>
                <i class="fa-solid fa-arrow-up-right-from-square" style="margin-left: auto; font-size: 10px; opacity: 0.4;"></i>
            </a>
            <a href="{{ route('teacher.communication.index') }}" class="nav-item">
                <i class="fa-solid fa-comments"></i>
                <span>Comunicación</span>
                <i class="fa-solid fa-arrow-up-right-from-square" style="margin-left: auto; font-size: 10px; opacity: 0.4;"></i>
            </a>
            <a href="{{ route('teacher.attendance.index') }}" class="nav-item">
                <i class="fa-solid fa-clipboard-user"></i>
                <span>Asistencia</span>
                <i class="fa-solid fa-arrow-up-right-from-square" style="margin-left: auto; font-size: 10px; opacity: 0.4;"></i>
            </a>
        </nav>

        <div class="courses-header">
            <h4>Mis Cursos</h4>
        </div>

        <div class="course-list">
            <template x-if="coursesLoading">
                <div class="space-y-2">
                    <div class="skeleton-nova" style="height: 60px;"></div>
                    <div class="skeleton-nova" style="height: 60px;"></div>
                    <div class="skeleton-nova" style="height: 60px;"></div>
                </div>
            </template>
            <template x-if="!coursesLoading && courses.length === 0">
                <div style="text-align: center; padding: 30px 20px;">
                    <i class="fa-solid fa-book-open" style="font-size: 30px; color: var(--text-tertiary); margin-bottom: 15px;"></i>
                    <p style="color: var(--text-tertiary); font-size: 13px; margin-bottom: 10px;">El director aún no te asignó cursos</p>
                    <p style="color: var(--text-tertiary); font-size: 12px;">Pídele un código DOC- o que te asigne la materia.</p>
                </div>
            </template>
            <template x-for="c in courses" :key="c.id">
                <button @click="loadCourse(c.id)"
                        :class="{ active: view === 'course' && currentCourseId === c.id }"
                        class="course-btn"
                        :style="`--course-accent: ${c.grade_color || gradeColor(c.grade)}`">
                    <div class="course-avatar" x-text="c.subject_name.charAt(0).toUpperCase()"></div>
                    <div class="course-info">
                        <div class="course-name" x-text="c.subject_name"></div>
                        <div class="course-meta">
                            <span class="course-grade" x-text="c.grade + (c.section ? ' / ' + c.section : '')"></span>
                            <span class="course-students-badge" x-text="c.students_count + ' alumnos'"></span>
                        </div>
                    </div>
                </button>
            </template>
        </div>

        <nav class="nav-section-account">
            <a href="{{ route('profile.edit') }}" class="nav-item">
                <i class="fa-solid fa-gear"></i>
                <span>Configuración</span>
            </a>
        </nav>
        <div class="sidebar-profile">
            <div class="sidebar-profile-avatar">{{ strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</div>
            <div style="min-width:0;flex:1;">
                <strong class="truncate">{{ auth()->user()->name }}</strong>
                <small>Docente</small>
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="ios-icon-btn" title="Cerrar sesión" style="width:36px;height:36px;box-shadow:none;">
                    <i class="fa-solid fa-right-from-bracket" style="font-size:12px;"></i>
                </button>
            </form>
        </div>
    </aside>

    <div class="theme-picker-backdrop" x-show="showThemePicker" x-cloak @click="showThemePicker = false"></div>
    <div class="theme-picker" x-show="showThemePicker" x-cloak x-transition.opacity @click.stop>
        <h4>Colores del tema</h4>
        <template x-for="theme in themeOptions" :key="theme.id">
            <button type="button" class="theme-picker-option" :class="{ active: currentThemeId === theme.id }" @click="applyTheme(theme.id)">
                <span class="theme-picker-dot" :style="`background:${theme.dot}`"></span>
                <span x-text="theme.label"></span>
            </button>
        </template>
    </div>

    {{-- CANVAS PRINCIPAL --}}
    <main id="hub-canvas">

        {{-- SKELETON LOADING --}}
        <template x-if="canvasLoading">
            <div style="display: flex; flex-direction: column; gap: 20px;">
                <div class="skeleton-nova" style="height: 50px; width: 250px;"></div>
                <div class="stats-grid">
                    <div class="skeleton-nova" style="height: 120px;"></div>
                    <div class="skeleton-nova" style="height: 120px;"></div>
                    <div class="skeleton-nova" style="height: 120px;"></div>
                    <div class="skeleton-nova" style="height: 120px;"></div>
                </div>
                <div class="content-grid-2">
                    <div class="skeleton-nova" style="height: 200px;"></div>
                    <div class="skeleton-nova" style="height: 200px;"></div>
                </div>
            </div>
        </template>

        {{-- WELCOME VIEW --}}
        <template x-if="!canvasLoading && view === 'welcome'">
            <div style="animation: slide-up 0.5s ease;">
                <div class="dash-header">
                    <div class="dash-header-main">
                        <p class="dash-eyebrow">{{ now()->isoFormat('dddd, D [de] MMMM YYYY') }}</p>
                        <h1 class="dash-greeting" x-text="greetingHello()"></h1>
                        <p class="dash-subtitle" x-text="greetingSubtitle()"></p>
                    </div>
                    <div class="dash-toolbar">
                        <label class="dash-search">
                            <i class="fa-solid fa-magnifying-glass"></i>
                            <input type="search" x-model="dashQuery" placeholder="Buscar curso…">
                        </label>
                        <div class="relative z-50" @click.outside="showNotifications = false">
                            <button type="button" @click.stop="toggleNotifications()" title="Notificaciones" class="ios-icon-btn">
                                <i class="fa-regular fa-bell"></i>
                                <span x-show="unreadCount > 0"
                                      x-text="unreadCount > 99 ? '99+' : unreadCount"
                                      class="notification-badge"
                                      x-cloak></span>
                            </button>
                            <div x-show="showNotifications"
                                 x-cloak
                                 x-transition.opacity.scale.origin.top.right
                                 class="notifications-dropdown right-0 z-50 bg-slate-900 border border-slate-800 shadow-2xl">
                                <div class="notifications-header">
                                    <span class="text-sm font-bold">Notificaciones</span>
                                    <button @click="markAllNotificationsRead()" class="text-xs text-cyan-300 hover:text-cyan-200 transition">
                                        Marcar todas leídas
                                    </button>
                                </div>
                                <div class="notifications-list">
                                    <template x-if="notifications.length === 0">
                                        <div class="notifications-empty">
                                            <i class="fa-regular fa-bell-slash"></i>
                                            <span>Sin notificaciones</span>
                                        </div>
                                    </template>
                                    <template x-for="n in notifications" :key="n.id">
                                        <a href="#"
                                           @click.prevent="handleNotificationClick(n)"
                                           class="notifications-item"
                                           :class="{ 'unread': !n.read_at }">
                                            <div class="notifications-item-content">
                                                <p class="notifications-title" x-text="n.title"></p>
                                                <p class="notifications-message" x-text="n.message" x-show="n.message"></p>
                                                <p class="notifications-time" x-text="n.created_at ? new Date(n.created_at).toLocaleDateString('es') : ''"></p>
                                            </div>
                                            <i x-show="!n.read_at" class="fa-solid fa-circle text-xs text-cyan-400" style="margin-left: 8px; flex-shrink: 0;"></i>
                                        </a>
                                    </template>
                                </div>
                            </div>
                        </div>
                        <div class="relative" @click.outside="createMenuOpen = false">
                            <button type="button" class="btn-create" @click="createMenuOpen = !createMenuOpen">
                                <i class="fa-solid fa-plus"></i>
                                Crear
                                <i class="fa-solid fa-chevron-down" style="font-size:10px;opacity:.8;"></i>
                            </button>
                            <div class="create-menu" x-show="createMenuOpen" x-cloak x-transition.opacity>
                                <a href="{{ route('teacher.planner.manual') }}"><i class="fa-solid fa-wand-magic-sparkles" style="color:#7C3AED;"></i> Nueva planificación</a>
                                <a href="{{ route('teacher.activities.index') }}"><i class="fa-solid fa-clipboard-list" style="color:#10B981;"></i> Crear actividad</a>
                                <button type="button" @click="createMenuOpen = false; openBubbleWithFocus()"><i class="fa-solid fa-sparkles" style="color:#EC4899;"></i> Preguntar a AulaSync AI</button>
                            </div>
                        </div>
                    </div>
                </div>

                <template x-if="stats">
                    <section class="next-activity-box"
                             :class="{ 'is-open': nextQueueOpen, 'is-empty': !stats.next_activity }"
                             :style="`--next-accent: ${stats.next_activity?.grade_color || gradeColor(stats.next_activity?.grade)}`">
                        <button type="button"
                                class="next-activity-toggle"
                                @click="stats.next_activity && (nextQueueOpen = !nextQueueOpen)"
                                :disabled="!stats.next_activity">
                            <div class="next-activity-kicker">
                                <span><i class="fa-solid fa-clock" style="margin-right:6px;"></i>Próxima actividad</span>
                                <span x-show="stats.next_activity" x-text="nextQueueOpen ? 'Ocultar lista' : 'Ver siguientes 5'"></span>
                            </div>
                            <template x-if="stats.next_activity">
                                <div class="next-activity-main">
                                    <div class="next-activity-time-wrap">
                                        <div class="next-activity-time" x-text="stats.next_activity.time_label || '—'"></div>
                                        <small x-text="formatQueueWhen(stats.next_activity.due_date)"></small>
                                    </div>
                                    <div class="next-activity-copy">
                                        <h2 x-text="stats.next_activity.title"></h2>
                                        <p>
                                            <span x-text="stats.next_activity.grade || 'Sin grado'"></span>
                                            ·
                                            <span x-text="stats.next_activity.subject_name || stats.next_activity.course_name || 'Sin materia'"></span>
                                        </p>
                                    </div>
                                    <span class="next-activity-type" x-text="stats.next_activity.type_label || 'Actividad'"></span>
                                </div>
                            </template>
                            <template x-if="!stats.next_activity">
                                <div class="next-activity-main">
                                    <div class="next-activity-copy">
                                        <h2>Sin clases programadas</h2>
                                        <p>Cuando tengas una clase, actividad o examen, verás aquí la hora, el grado y el tema.</p>
                                    </div>
                                </div>
                            </template>
                        </button>
                        <div class="next-activity-queue" x-show="nextQueueOpen && (stats.upcoming_queue || []).length" x-cloak>
                            <template x-for="item in (stats.upcoming_queue || [])" :key="'q-'+item.id">
                                <button type="button"
                                        class="next-activity-queue-item"
                                        :style="`--next-accent: ${item.grade_color || gradeColor(item.grade)}`"
                                        @click="openActivityModalFromExternal({ id: item.id })">
                                    <span class="next-activity-queue-time">
                                        <strong x-text="item.time_label || '—'"></strong>
                                        <small x-text="formatQueueWhen(item.due_date)"></small>
                                    </span>
                                    <span>
                                        <strong x-text="item.title"></strong>
                                        <small x-text="`${item.grade || ''} · ${item.subject_name || item.course_name || ''}`"></small>
                                    </span>
                                    <span class="next-activity-type" x-text="item.type_label || 'Actividad'"></span>
                                </button>
                            </template>
                        </div>
                    </section>
                </template>

                <template x-if="stats">
                    <div class="ios-stat-grid">
                        <div class="ios-stat">
                            <div class="ios-stat-icon purple"><i class="fa-solid fa-layer-group"></i></div>
                            <div class="ios-stat-value" x-text="stats.total_courses"></div>
                            <div class="ios-stat-label">Cursos activos</div>
                        </div>
                        <div class="ios-stat">
                            <div class="ios-stat-icon green"><i class="fa-solid fa-clipboard-check"></i></div>
                            <div class="ios-stat-value" x-text="pendingTotal()"></div>
                            <div class="ios-stat-label">Actividades por calificar</div>
                        </div>
                        <div class="ios-stat">
                            <div class="ios-stat-icon blue"><i class="fa-solid fa-users"></i></div>
                            <div class="ios-stat-value" x-text="stats.total_students"></div>
                            <div class="ios-stat-label">Estudiantes</div>
                        </div>
                        <div class="ios-stat">
                            <div class="ios-stat-icon amber"><i class="fa-solid fa-chart-line"></i></div>
                            <div class="ios-stat-value" x-text="stats.avg_grade ?? '—'"></div>
                            <div class="ios-stat-label" x-text="stats.climate?.label ?? 'Promedio general'"></div>
                        </div>
                    </div>
                </template>

                <div class="ai-command-card">
                    <div class="ai-command-glow" aria-hidden="true"></div>
                    <div class="ai-command-head">
                        <div class="ai-command-icon">
                            <i class="fa-solid fa-sparkles"></i>
                        </div>
                        <div class="ai-command-copy">
                            <h2>AulaSync Intelligence</h2>
                            <p x-text="aiSummaryText()"></p>
                        </div>
                    </div>
                    <div class="ai-command-footer">
                        <button type="button" class="ai-insight-counter" @click="scrollToInsights()">
                            <i class="fa-solid fa-bolt"></i>
                            <span x-text="activeInsightsCount()"></span>
                            insights activos
                        </button>
                        <div class="ai-command-actions">
                            <button type="button" class="btn-create" @click="openBubbleWithFocus()">
                                <i class="fa-solid fa-robot"></i>
                                Preguntar a AulaSync AI
                            </button>
                        </div>
                    </div>
                </div>

                <template x-if="filteredCourses().length > 0">
                    <div>
                        <div class="section-title">
                            <i class="fa-solid fa-layer-group"></i>
                            <h2>Mis Cursos</h2>
                        </div>
                        <div class="courses-grid">
                            <template x-for="c in filteredCourses()" :key="c.id">
                                <div @click="loadCourse(c.id)" class="course-card" :style="`--course-accent: ${c.grade_color || gradeColor(c.grade)}`">
                                    <div class="course-card-header">
                                        <div class="course-card-avatar" x-text="c.subject_name.charAt(0).toUpperCase()"></div>
                                        <div class="course-card-info">
                                            <h3 x-text="c.subject_name"></h3>
                                            <p x-text="c.grade + (c.section ? ' / ' + c.section : '')"></p>
                                        </div>
                                        <span class="course-pending-badge" x-show="c.pending_grading_count > 0">
                                            <span x-text="c.pending_grading_count"></span> sin calificar
                                        </span>
                                    </div>
                                    <div class="course-progress" x-show="c.avg_score !== null && c.avg_score !== undefined">
                                        <div class="course-progress-head">
                                            <span>Promedio</span>
                                            <span x-text="c.avg_score + '/20'"></span>
                                        </div>
                                        <div class="course-progress-track">
                                            <div class="course-progress-fill" :style="`width: ${Math.max(4, Math.min(100, Math.round((c.avg_score / 20) * 100)))}%`"></div>
                                        </div>
                                    </div>
                                    <div class="course-stats">
                                        <span class="course-stat">
                                            <i class="fa-solid fa-users"></i>
                                            <span x-text="c.students_count + ' alumnos'"></span>
                                        </span>
                                        <span class="course-stat">
                                            <i class="fa-solid fa-clipboard-list"></i>
                                            <span x-text="c.activities_count + ' actividades'"></span>
                                        </span>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>

                <div class="ios-board">
                    <div class="ios-panel ios-panel--today-grade">
                        <div class="ios-panel-head">
                            <h3>Agenda de hoy por grado</h3>
                            <span class="panel-week-chip">Ordenada por hora</span>
                        </div>
                        <template x-if="(stats?.today_grade_list || []).length">
                            <div class="today-grade-list">
                                <template x-for="bucket in stats.today_grade_list" :key="'today-'+bucket.grade">
                                    <section class="today-grade-group" :style="`--grade-accent: ${bucket.items?.[0]?.grade_color || gradeColor(bucket.grade)}`">
                                        <header>
                                            <strong x-text="bucket.grade"></strong>
                                            <span x-text="`${bucket.count} bloque${bucket.count === 1 ? '' : 's'}`"></span>
                                        </header>
                                        <div class="today-grade-items">
                                            <template x-for="item in bucket.items" :key="'item-'+item.id">
                                                <button type="button" class="today-grade-item" :style="`--grade-accent: ${item.grade_color || gradeColor(item.grade)}`" @click="openActivityModalFromExternal({ id: item.id })">
                                                    <span class="today-grade-time" x-text="item.time_range || item.time_label || 'Sin hora'"></span>
                                                    <div class="today-grade-copy">
                                                        <p x-text="item.title"></p>
                                                        <small x-text="item.course_name"></small>
                                                    </div>
                                                </button>
                                            </template>
                                        </div>
                                    </section>
                                </template>
                            </div>
                        </template>
                        <template x-if="!(stats?.today_grade_list || []).length">
                            <p class="upcoming-empty">No hay actividades para hoy. Puedes planificar desde el calendario.</p>
                        </template>
                    </div>

                    <div class="ios-panel ios-panel--upcoming">
                        <div class="ios-panel-head">
                            <h3>Próximas actividades</h3>
                            <template x-if="(stats?.activities_this_week || 0) > 0">
                                <span class="panel-week-chip" x-text="`${stats.activities_this_week} esta semana`"></span>
                            </template>
                        </div>
                        <template x-if="(stats?.upcoming_activities || []).length">
                            <div class="upcoming-list">
                                <template x-for="act in stats.upcoming_activities" :key="act.id">
                                    <button type="button"
                                            class="upcoming-row upcoming-row--clickable"
                                            :style="`--grade-accent: ${act.grade_color || gradeColor(act.grade)}`"
                                            @click="openActivityModalFromExternal({ id: act.id })">
                                        <div class="upcoming-date">
                                            <strong x-text="formatDueParts(act.due_date).day"></strong>
                                            <span x-text="formatDueParts(act.due_date).mon"></span>
                                        </div>
                                        <div class="upcoming-copy">
                                            <p x-text="act.title"></p>
                                            <small x-text="act.course_name"></small>
                                        </div>
                                        <span class="status-pill">Programada</span>
                                    </button>
                                </template>
                            </div>
                        </template>
                        <template x-if="!(stats?.upcoming_activities || []).length">
                            <p class="upcoming-empty">No hay entregas próximas. Cuando crees una actividad, aparecerá aquí.</p>
                            <button type="button" class="insight-action" @click="loadCalendar()">Ver calendario</button>
                        </template>
                    </div>

                    <div class="ios-panel" x-ref="insightsSection">
                        <h3>Recordatorios</h3>
                        <template x-for="insight in visibleInsights()" :key="insight.id">
                            <button type="button" class="reminder-row" @click="runInsightAction(insight)">
                                <div class="reminder-ico" :style="insight.type === 'atencion' ? 'background:#F59E0B' : (insight.type === 'logro' ? 'background:#10B981' : 'background:#7C3AED')">
                                    <i class="fa-solid" :class="insight.type === 'atencion' ? 'fa-bolt' : 'fa-sparkles'"></i>
                                </div>
                                <div class="upcoming-copy">
                                    <p x-text="insight.chipLabel"></p>
                                    <small x-text="insight.text"></small>
                                </div>
                                <i class="fa-solid fa-chevron-right" style="color:var(--text-tertiary);font-size:11px;"></i>
                            </button>
                        </template>
                        <div class="quick-actions">
                            <a href="{{ route('teacher.planner.manual') }}" class="quick-action"><i class="fa-solid fa-wand-magic-sparkles"></i>Planificar</a>
                            <a href="{{ route('teacher.attendance.index') }}" class="quick-action"><i class="fa-solid fa-clipboard-user"></i>Asistencia</a>
                            <a href="{{ route('teacher.activities.index') }}" class="quick-action"><i class="fa-solid fa-plus"></i>Actividad</a>
                            <button type="button" class="quick-action" @click="openBubbleWithFocus()"><i class="fa-solid fa-robot"></i>IA</button>
                        </div>
                    </div>
                </div>

                {{-- Empty State AI --}}
                <template x-if="courses.length === 0 && !coursesLoading">
                    <div class="empty-state-nova">
                        <div class="ai-orb">
                            <i class="fa-solid fa-robot"></i>
                        </div>
                        <h2 class="empty-title">Aún no tienes cursos asignados</h2>
                        <p class="empty-subtitle">El director crea la estructura escolar y te invita con un código DOC-. Tú planificas, evalúas y tomas asistencia.</p>
                        
                        <div class="command-grid">
                            <button @click="sendAICommand('¿Qué alumnos de mi grado están en la nómina del colegio?')" class="command-btn">
                                <small>Nómina</small>
                                <p>"¿Qué alumnos de mi grado están en la nómina?"</p>
                            </button>
                            <button @click="sendAICommand('Inscribe a María en este curso si ya está matriculada en el colegio')" class="command-btn">
                                <small>Vincular</small>
                                <p>"Inscribe a María si ya está en el colegio"</p>
                            </button>
                        </div>

                        <div class="action-buttons">
                            <button @click="openBubbleWithFocus()" class="btn-primary">
                                <i class="fa-solid fa-robot"></i>
                                Hablar con el Asistente
                            </button>
                            <p class="empty-subtitle">El director debe asignarte un curso. Mientras tanto puedes planificar o hablar con el asistente sobre tus clases asignadas.</p>
                        </div>
                    </div>
                </template>
            </div>
        </template>

        {{-- COURSE DETAIL VIEW --}}
        <template x-if="!canvasLoading && view === 'course' && courseData">
            <div style="animation: slide-up 0.5s ease;">
                {{-- Header --}}
                <div class="course-detail-header">
                    <button @click="loadWelcome()" class="back-btn">
                        <i class="fa-solid fa-arrow-left"></i>
                    </button>
                    <div class="course-detail-title">
                        <h1 x-text="courseData.subject_name"></h1>
                        <div class="course-detail-meta">
                            <span class="meta-badge" x-text="courseData.grade + (courseData.section ? ' / Sección ' + courseData.section : '')"></span>
                            <span class="meta-badge" x-text="courseData.grading_scale_label || '1 al 20'"></span>
                            <span class="action-badge">
                                <i class="fa-regular fa-calendar"></i>
                                <span x-text="courseData.school_year ?? ''"></span>
                            </span>
                        </div>
                    </div>
                    <div style="display: flex; gap: 10px;">
                        <button type="button" class="btn-primary" style="padding: 10px 20px;" @click="openCourseSettings()">
                            <i class="fa-solid fa-pen-to-square"></i>
                            Gestionar
                        </button>
                    </div>
                </div>

                {{-- Paneles --}}
                <div class="panel-grid">
                    {{-- Alumnos --}}
                    <div class="panel-card">
                        <div class="panel-header">
                            <h3><i class="fa-solid fa-users"></i> Alumnos</h3>
                            <span class="panel-count" x-text="courseData.students.length"></span>
                            <button type="button" class="panel-link" @click="openEnrollModal()">
                                Vincular <i class="fa-solid fa-plus"></i>
                            </button>
                        </div>
                        <template x-if="courseData.students.length === 0">
                            <div style="padding: 40px 20px; text-align: center;">
                                <i class="fa-solid fa-user-graduate" style="font-size: 30px; color: var(--text-tertiary); margin-bottom: 10px;"></i>
                                <p style="color: var(--text-tertiary);">Sin alumnos inscritos.</p>
                                <p style="color: var(--text-tertiary); font-size: 12px; margin-top: 5px;">Vincula alumnos ya matriculados por el director.</p>
                            </div>
                        </template>
                        <div style="max-height: 300px; overflow-y: auto;">
                            <template x-for="(s, idx) in courseData.students" :key="s.id">
                                <div class="student-item">
                                    <div class="student-index" x-text="idx + 1"></div>
                                    <div style="display:flex; align-items:center; justify-content:space-between; width:100%; gap:10px;">
                                        <button type="button"
                                            @click="openStudentSlideover(s)"
                                            class="student-name text-left transition-colors duration-150 hover:text-cyan-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-cyan-400/60 rounded-md">
                                            <span x-text="s.name"></span>
                                        </button>
                                        <span class="acum-badge"
                                            x-text="`Acum: ${formatAcum(s.promedio_acumulado ?? s.nota_actual ?? s.avg_score)}`"></span>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    {{-- Actividades --}}
<div class="panel-card">
    <div class="panel-header">
        <h3><i class="fa-solid fa-clipboard-list"></i> Actividades</h3>
        <span class="panel-count" x-text="courseData?.activities?.length || 0"></span>
        <a href="{{ route('teacher.activities.index') }}" class="panel-link">
            Nueva <i class="fa-solid fa-plus"></i>
        </a>
    </div>
    <template x-if="(courseData?.activities?.length || 0) === 0">
        <div style="padding: 40px 20px; text-align: center;">
            <i class="fa-solid fa-pen-to-square" style="font-size: 30px; color: var(--text-tertiary); margin-bottom: 10px;"></i>
            <p style="color: var(--text-tertiary);">Sin actividades creadas.</p>
            <p style="color: var(--text-tertiary); font-size: 12px; margin-top: 5px;">"Crea Parcial 1 con peso 25%"</p>
        </div>
    </template>
    <div style="max-height: 400px; overflow-y: auto;">
        <template x-for="a in courseData.activities" :key="a.id">
            <div class="activity-item" x-data="{ open: false, editOpen: false, editPrompt: '' }">
                <div class="activity-header" @click="open = !open">
                    <div class="activity-type-indicator" 
                         :style="a.type === 'clase' 
                            ? 'background: linear-gradient(180deg, #6C4AE0, #C455ED)' 
                            : 'background: linear-gradient(180deg, #C455ED, #3BC9DB)'">
                    </div>
                    <div class="activity-info">
                        <div class="activity-title" x-text="a.title"></div>
                        <div class="activity-meta">
                            <span class="activity-type-badge" x-text="a.type === 'clase' ? 'CLASE' : (a.is_homework || a.type === 'tarea' ? 'TAREA' : 'ACTIVIDAD')"></span>
                            <span class="activity-date" x-text="a.due_date || 'Sin fecha'"></span>
                            <span class="activity-weight" x-show="a.weight_percentage > 0" x-text="a.weight_percentage + '%'"></span>
                            <span class="activity-weight" x-show="a.type !== 'clase' && a.total_students > 0" x-text="`${a.graded_count ?? 0}/${a.total_students} calificadas`"></span>
                        </div>
                    </div>
                    <i class="fa-solid fa-chevron-down activity-chevron" :class="{ 'fa-chevron-up': open }"></i>
                </div>
                <div x-show="open" x-cloak class="activity-body">
                    <div class="activity-description markdown-body" x-html="a.description ? renderMarkdown(a.description) : '<p>Sin descripción.</p>'"></div>
                    <div class="activity-actions">
                        <template x-if="a.type === 'clase'">
                            <button @click="sendAICommand(`Genera material de apoyo para la clase «${a.title}» del curso ${courseData.subject_name}`)" class="action-btn primary">
                                <i class="fa-solid fa-wand-magic-sparkles"></i>
                                Generar material
                            </button>
                        </template>
                        <template x-if="a.type !== 'clase'">
                            <button @click.stop="openGradesSlideover(a)" class="action-btn primary">
                                <i class="fa-solid fa-table-cells"></i>
                                Cargar Notas
                            </button>
                        </template>
                        
                        {{-- BOTÓN MODIFICADO: Ahora abre la burbuja IA con contexto --}}
                        <button @click="setActivityContext(a); $dispatch('open-activity-ai', { 
                        activity: { 
                            id: a.id, 
                            title: a.title, 
                            type: a.type, 
                            description: a.description,
                            due_date: a.due_date,
                            max_score: a.max_score,
                            course_id: courseData?.id,
                            course_name: courseData?.name,
                            grade: courseData?.grade,
                            section: courseData?.section,
                            teacher_id: {{ auth()->id() }}
                        }, 
                        courseName: courseData?.name,
                        fullContext: window.novaContext
                    })" class="btn-secondary">
                        <i class="fa-solid fa-robot"></i>
                        Modificar con IA
                    </button>
                        
                        <button @click.stop="requestDeleteActivity(a.id, a.title)" class="action-btn warning">
                            <i class="fa-solid fa-trash-alt"></i>
                            Eliminar
                        </button>
                    </div>
                    
                    {{-- SECCIÓN ELIMINADA: Ya no necesitamos el input local porque usamos la burbuja --}}
                    {{-- 
                    <div x-show="editOpen" x-cloak style="margin-top: 15px;">
                        <div style="display: flex; gap: 10px;">
                            <input x-model="editPrompt" 
                                   @keydown.enter="sendAICommand(editPrompt); editOpen = false"
                                   style="flex: 1; background: var(--nova-glass); border: 1px solid var(--nova-glass-border); border-radius: 30px; padding: 10px 15px; color: var(--text-primary); font-size: 13px;"
                                   placeholder="Ej: Cambia el peso a 30%">
                            <button @click="sendAICommand(editPrompt); editOpen = false" class="btn-primary" style="padding: 10px 20px;">
                                <i class="fa-solid fa-paper-plane"></i>
                            </button>
                        </div>
                    </div>
                    --}}
                </div>
            </div>
        </template>
    </div>
</div>

                {{-- Barra de contexto AI --}}
                <div class="ai-context-bar">
                    <div class="ai-context-icon">
                        <i class="fa-solid fa-robot"></i>
                    </div>
                    <div class="ai-context-text">
                        <p>
                            <strong>Asistente en contexto:</strong> Ahora sabe que estás en <span x-text="courseData.subject_name"></span>. 
                            Prueba: <em>"Agrega a María González"</em> o <em>"Crea Examen Final con peso 30%"</em>
                        </p>
                    </div>
                    <button @click="$dispatch('open-ai-bubble')" class="btn-secondary" style="padding: 8px 16px;">
                        <i class="fa-solid fa-robot"></i>
                        Abrir
                    </button>
                </div>
            </div>
        </template>

        {{-- CALENDAR VIEW --}}
        <template x-if="!canvasLoading && view === 'calendar'">
            <div style="animation: slide-up 0.5s ease;">
                {{-- Header calendario --}}
                <div class="calendar-header">
                    <div class="calendar-title">
                        <h2><i class="fa-solid fa-calendar-days" style="color: var(--nova-violet); margin-right: 10px;"></i>Calendario Académico</h2>
                        <p x-text="calendarData?.month_name ?? ''"></p>
                    </div>
                    <div class="calendar-nav">
                        <button @click="calNavigate(-1)" class="calendar-nav-btn">
                            <i class="fa-solid fa-chevron-left"></i>
                        </button>
                        <button @click="calNavigate(0)" class="calendar-nav-btn today-btn">
                            Hoy
                        </button>
                        <button @click="calNavigate(1)" class="calendar-nav-btn">
                            <i class="fa-solid fa-chevron-right"></i>
                        </button>
                        <span class="calendar-stats">
                            <span x-text="calendarData?.total_activities ?? 0"></span> entregas
                        </span>
                        <div class="calendar-grade-filters">
                            <template x-for="grade in calendarGradeOptions()" :key="'grade-'+grade">
                                <button type="button"
                                        class="calendar-grade-chip"
                                        :class="{ 'active': calendarGradeFilter === grade }"
                                        :style="gradeChipStyle(grade, calendarGradeFilter === grade)"
                                        @click="setCalendarGradeFilter(grade)"
                                        x-text="grade === 'all' ? 'Todos' : grade"></button>
                            </template>
                        </div>
                        <button type="button" class="pedagogy-btn" title="Cambiar modelo pedagógico de planificación"
                                @click="window.dispatchEvent(new CustomEvent('nova-lesson-template-picker'))">
                            <span class="pedagogy-emoji" aria-hidden="true">📘</span>
                            <span>
                                <small>Plantilla de clase</small>
                                <strong>Modelo pedagógico</strong>
                            </span>
                        </button>
                    </div>
                </div>

                {{-- AI hint --}}
                <div class="ai-hint-cal" style="background: var(--nova-glass); border: 1px solid var(--nova-glass-border); border-radius: 20px; padding: 15px 20px; margin-bottom: 25px; display: flex; align-items: center; gap: 12px;">
                    <i class="fa-solid fa-robot" style="color: var(--nova-cyan); font-size: 18px;"></i>
                    <p style="color: var(--text-secondary); font-size: 13px;">
                        <strong>IA activa:</strong> Di <em>"Planifica Fracciones para Matemáticas 3ro"</em> y el calendario se llenará automáticamente.
                    </p>
                </div>

                {{-- Grid calendario --}}
                <template x-if="calendarData">
                    <div class="calendar-grid">
                        <div class="weekdays">
                            <template x-for="day in ['Lun','Mar','Mié','Jue','Vie','Sáb','Dom']" :key="day">
                                <div class="weekday" x-text="day"></div>
                            </template>
                        </div>
                        <div class="calendar-days">
                            <template x-for="(cell, i) in calendarDays" :key="i">
                                <div :class="{
                                        'calendar-day': cell !== null,
                                        'calendar-day empty': cell === null,
                                        'today': cell !== null && isToday(cell),
                                        'has-events': cell !== null && activitiesForDay(cell).length > 0
                                     }"
                                     @click="cell && openDayModal(cell)">
                                    <template x-if="cell !== null">
                                        <div style="display:flex;flex-direction:column;min-height:0;height:100%;">
                                            <span class="day-number" x-text="cell"></span>
                                            <div class="day-content">
                                                <template x-for="act in activitiesForDay(cell).slice(0, calendarGradeFilter === 'all' ? 3 : 2)" :key="act.id">
                                                    <button @click.stop="setActivityContext(act); openActivityModal(act)"
                                                            class="cal-grade-event"
                                                            :class="{ 'has-director-notes': act.director_notes }"
                                                            :style="`background: ${act.grade_color || gradeColor(act.grade)}`"
                                                            :title="`${act.grade || ''} · ${act.time_label || ''} · ${act.title}`">
                                                        <span class="cal-grade-event-line">
                                                            <span x-text="act.grade || 'Curso'"></span>
                                                            <span x-text="act.time_label || ''"></span>
                                                        </span>
                                                        <span class="cal-grade-event-title" x-text="act.title"></span>
                                                    </button>
                                                </template>
                                                <button type="button"
                                                        class="more-events"
                                                        x-show="activitiesForDay(cell).length > (calendarGradeFilter === 'all' ? 3 : 2)"
                                                        @click.stop="openDayModal(cell)"
                                                        x-text="'+' + (activitiesForDay(cell).length - (calendarGradeFilter === 'all' ? 3 : 2)) + ' más'"></button>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>
            </div>
        </template>
    </main>

    {{-- Activity Modal --}}
<div x-show="activityModal" x-cloak class="modal-overlay" @click.self="activityModal = null" @keydown.escape.window="activityModal = null">
    <div class="modal-nova" style="max-width: 680px;">
        <div class="modal-header" style="padding: 20px 28px;">
            <div style="display: flex; align-items: center; gap: 12px; min-width: 0;">
                <span class="activity-type-badge" style="flex-shrink: 0; font-size: 10px; padding: 3px 10px;"
                      :style="activityModal?.type === 'clase'
                        ? 'background: rgba(59,201,219,0.12); color: var(--nova-cyan); border: 1px solid rgba(59,201,219,0.25);'
                        : 'background: rgba(245,158,11,0.12); color: #F59E0B; border: 1px solid rgba(245,158,11,0.25);'"
                      x-text="activityModal?.type === 'clase' ? 'CLASE' : 'EVALUACIÓN'"></span>
                <h3 style="font-size: 16px; font-weight: 600; color: var(--text-primary); margin: 0; line-height: 1.3; white-space: normal;" x-text="activityModal?.title"></h3>
            </div>
            <button @click="activityModal = null" class="modal-close" style="flex-shrink: 0;">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div class="modal-body" style="padding: 0 28px 20px;">
            <div class="modal-meta-row">
                <span class="modal-meta-item"><i class="fa-regular fa-building" style="color: var(--nova-violet);"></i><span x-text="activityModal?.course_name"></span></span>
                <span class="modal-meta-item"><i class="fa-regular fa-calendar" style="color: var(--nova-cyan);"></i><span x-text="activityModal?.due_date ?? '—'"></span></span>
                <span class="modal-meta-item" x-show="activityModal?.max_score > 0"><i class="fa-solid fa-bullseye" style="color: #F59E0B;"></i>Máx: <span x-text="activityModal?.max_score"></span></span>
                <span class="modal-meta-item" x-show="activityModal?.weight_percentage > 0"><i class="fa-solid fa-weight-scale" style="color: var(--nova-fuchsia);"></i><span x-text="activityModal?.weight_percentage"></span>%</span>
            </div>

            {{-- Fases de la clase: tarjetas según plantilla activa --}}
            <div x-show="activityModal?.type === 'clase'" class="phase-cards-stack">
                <div style="display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 2px;">
                    <span class="modal-section-label">Planificación de la clase</span>
                    <span style="font-size:11px;color:var(--text-tertiary)" x-text="lessonTemplateLabel()"></span>
                </div>

                <template x-for="phase in lessonPhaseDefs()" :key="phase.key">
                    <div class="phase-card"
                         :class="{ 'phase-card--editing': phaseEdit.editing === phase.key }"
                         :style="`border-left:3px solid ${phase.color}`">
                        <div class="phase-card-header">
                            <div class="phase-card-badge" :style="`color:${phase.color}`">
                                <i :class="phase.icon"></i>
                                <span x-text="phase.label"></span>
                            </div>
                            <div class="phase-card-actions">
                                <button x-show="phaseEdit.editing !== phase.key"
                                        @click="startPhaseEdit(phase.key)"
                                        class="phase-edit-btn" :title="'Editar ' + phase.label" type="button">
                                    <i class="fa-solid fa-pencil"></i>
                                </button>
                                <template x-if="phaseEdit.editing === phase.key">
                                    <div class="phase-card-actions">
                                        <button @click="savePhaseSection(phase.key)" class="phase-save-btn"
                                                :disabled="phaseEdit.saving === phase.key" type="button">
                                            <i class="fa-solid" :class="phaseEdit.saving === phase.key ? 'fa-spinner fa-spin' : 'fa-floppy-disk'"></i>
                                            <span x-text="phaseEdit.saving === phase.key ? 'Guardando…' : 'Guardar'"></span>
                                        </button>
                                        <button @click="cancelPhaseEdit(phase.key)" class="phase-cancel-btn" type="button">Cancelar</button>
                                    </div>
                                </template>
                            </div>
                        </div>
                        <div x-show="phaseEdit.editing !== phase.key"
                             class="phase-card-body markdown-body"
                             x-html="renderPhaseMarkdown(phaseEdit.values[phase.key] || '')"></div>
                        <textarea x-show="phaseEdit.editing === phase.key"
                                  x-model="phaseEdit.draft[phase.key]"
                                  class="phase-card-textarea"
                                  rows="4"
                                  :placeholder="phase.placeholder"></textarea>
                    </div>
                </template>
            </div>

            <div x-show="activityModal?.type === 'clase'" style="margin-top: 18px;">
                <div style="display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 8px;">
                    <span class="modal-section-label"><i class="fa-solid fa-pen-clip" style="margin-right:6px;"></i>Observación pedagógica</span>
                    <button @click="saveActivityNotes()" :disabled="notesSaving" class="phase-save-btn" type="button">
                        <i class="fa-solid" :class="notesSaving ? 'fa-spinner fa-spin' : 'fa-floppy-disk'"></i>
                        <span x-text="notesSaving ? 'Guardando…' : 'Guardar'"></span>
                    </button>
                </div>
                <textarea :value="activityModal?.notes || ''"
                          @input="if (activityModal) activityModal.notes = $event.target.value"
                          rows="3" class="phase-card-textarea"
                          placeholder="Anota observaciones generales de esta clase: comportamiento, ritmo, ajustes para la próxima sesión…"></textarea>
            </div>

            <div x-show="activityModal?.type !== 'clase'" class="phase-cards-stack">
                <div style="display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 2px;">
                    <span class="modal-section-label">Diseño de la evaluación</span>
                    <span style="font-size:11px;color:var(--text-tertiary)" x-text="activityModal?.evaluation_mode === 'physical' ? 'Formato impreso' : 'Formato digital'"></span>
                </div>
                <template x-for="card in evaluationBriefCards()" :key="card.key">
                    <div class="phase-card" :style="`border-left:3px solid ${card.color}`">
                        <div class="phase-card-header">
                            <div class="phase-card-badge" :style="`color:${card.color}`">
                                <i :class="card.icon"></i>
                                <span x-text="card.label"></span>
                            </div>
                        </div>
                        <div class="phase-card-body markdown-body" x-html="card.html"></div>
                    </div>
                </template>
            </div>

            <div x-show="(activityModal?.tareas ?? []).length > 0" style="margin-top: 22px;">
                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 10px;">
                    <i class="fa-solid fa-tasks" style="font-size: 11px; color: var(--nova-cyan);"></i>
                    <span class="modal-section-label" style="font-size: 10px;">Tareas asociadas</span>
                    <span style="font-size: 10px; background: var(--nova-glass); border: 1px solid var(--nova-glass-border); border-radius: 20px; padding: 1px 7px; color: var(--text-tertiary);" x-text="(activityModal?.tareas ?? []).length"></span>
                </div>
                <template x-for="task in (activityModal?.tareas ?? [])" :key="task.id">
                    <div class="tarea-row">
                        <div style="font-size: 13px; font-weight: 600; color: var(--text-primary);" x-text="task.titulo"></div>
                        <div style="font-size: 12px; color: var(--text-secondary); margin-top: 4px; line-height: 1.5;" x-text="task.descripcion || 'Sin descripción'"></div>
                        <div style="margin-top: 6px; display: flex; gap: 12px; font-size: 11px; color: var(--text-tertiary);">
                            <span><i class="fa-regular fa-calendar" style="margin-right: 3px;"></i> <span x-text="task.fecha_entrega || 'Sin fecha'"></span></span>
                            <span><i class="fa-solid fa-award" style="margin-right: 3px;"></i> <span x-text="task.puntos"></span> pts</span>
                        </div>
                    </div>
                </template>
            </div>

            <div x-show="activityModal?.nee_adaptation" style="margin-top: 16px;">
                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 10px;">
                    <i class="fa-solid fa-brain" style="font-size: 11px; color: var(--nova-fuchsia);"></i>
                    <span class="modal-section-label" style="font-size: 10px;">Adaptación NEE</span>
                    <span style="font-size: 10px; background: rgba(196,85,237,0.1); border: 1px solid rgba(196,85,237,0.2); border-radius: 20px; padding: 1px 7px; color: var(--nova-fuchsia);" x-text="activityModal?.nee_type || 'NEE'"></span>
                </div>
                <div style="padding: 12px 14px; border-radius: 10px; border: 1px solid rgba(196,85,237,0.15); background: rgba(196,85,237,0.03); font-size: 13px; color: var(--text-secondary); line-height: 1.6;" x-text="activityModal?.nee_adaptation"></div>
            </div>

            {{-- Director feedback --}}
            <div x-show="activityModal?.director_notes" style="margin-top: 16px;">
                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 10px;">
                    <i class="fa-solid fa-pen-clip" style="font-size: 11px; color: #f59e0b;"></i>
                    <span class="modal-section-label" style="font-size: 10px;">Notas del Director</span>
                </div>
                <div style="padding: 12px 14px; border-radius: 10px; border: 1px solid rgba(245, 158, 11, 0.25); background: rgba(245, 158, 11, 0.05); font-size: 13px; color: var(--text-secondary); line-height: 1.6;" x-text="activityModal?.director_notes"></div>
            </div>
        </div>

        <div x-show="activityModal?.type === 'clase'" class="modal-ai-lab">
            <div class="modal-ai-lab-header">
                <i class="fa-solid fa-flask"></i>
                <span>AI Lab</span>
                <hr>
            </div>
            <div class="modal-ai-grid">
                <button @click="openTaskIdeaModal()" class="modal-ai-btn">
                    <i class="fa-solid fa-dice" style="color: var(--nova-cyan); font-size: 13px; width: 18px; text-align: center;"></i>
                    Sugerir tarea para esta clase
                </button>
                <button @click="openNeeModal()" class="modal-ai-btn">
                    <i class="fa-solid fa-wand-magic-sparkles" style="color: var(--nova-fuchsia); font-size: 13px; width: 18px; text-align: center;"></i>
                    Generar adaptación curricular (NEE)
                </button>
                <button @click="setActivityContext(activityModal); $dispatch('open-activity-ai', { 
                    activity: { 
                        id: activityModal.id, 
                        title: activityModal.title, 
                        type: activityModal.type, 
                        description: activityModal.description,
                        due_date: activityModal.due_date,
                        max_score: activityModal.max_score,
                        course_id: activityModal?.course_id,
                        course_name: activityModal?.course_name,
                        grade: activityModal?.grade,
                        section: activityModal?.section,
                        objective: activityModal?.objective ?? activityModal?.description ?? '',
                        methodology: activityModal?.methodology ?? '',
                        teacher_id: {{ auth()->id() }}
                    }, 
                    courseName: activityModal?.course_name,
                    fullContext: activityModal
                }); activityModal = null" class="modal-ai-btn">
                    <i class="fa-solid fa-robot" style="color: var(--nova-violet); font-size: 13px; width: 18px; text-align: center;"></i>
                    Modificar contenido con IA
                </button>
            </div>
        </div>

        <div class="modal-footer" style="padding: 16px 28px; display: flex; gap: 8px; justify-content: flex-end; align-items: center; border-top: 1px solid var(--nova-glass-border); flex-wrap: wrap;">
            <template x-if="activityModal?.type === 'clase'">
                <button @click="openClassAttendance(activityModal)" class="modal-footer-btn primary">
                    <i class="fa-solid fa-clipboard-user"></i>
                    Tomar asistencia
                </button>
            </template>
            <template x-if="activityModal?.type !== 'clase'">
                <button @click="openGradesSlideover(activityModal)" class="modal-footer-btn primary">
                    <i class="fa-solid fa-table-cells"></i>
                    Cargar Notas
                </button>
            </template>
            <button @click.stop="requestDeleteActivity(activityModal?.id, activityModal?.title)" class="modal-footer-btn danger">
                <i class="fa-solid fa-trash-alt"></i>
                Eliminar
            </button>
            <button @click="activityModal = null" class="modal-footer-btn">
                Cerrar
            </button>
        </div>
    </div>
</div>

    {{-- Delete activity confirm --}}
    <div x-show="deleteConfirm.open" x-cloak class="modal-overlay" style="z-index: 12000;" @click.self="cancelDeleteActivity()" @keydown.escape.window="cancelDeleteActivity()">
        <div class="modal-nova" style="max-width: 420px;" @click.stop>
            <div class="modal-header" style="padding: 20px 24px;">
                <h3 style="font-family: var(--font-display); font-size: 18px; font-weight: 800; margin: 0;">Eliminar actividad</h3>
            </div>
            <div style="padding: 0 24px 20px;">
                <p style="font-size: 14px; line-height: 1.5; margin: 0 0 8px;">
                    ¿Eliminar <strong x-text="deleteConfirm.title"></strong>?
                </p>
                <p style="font-size: 13px; color: var(--text-tertiary); margin: 0;">Esta acción no se puede deshacer.</p>
            </div>
            <div class="modal-footer" style="padding: 16px 24px; display: flex; gap: 8px; justify-content: flex-end; border-top: 1px solid var(--nova-glass-border);">
                <button type="button" class="modal-footer-btn" :disabled="deleteConfirm.deleting" @click="cancelDeleteActivity()">Cancelar</button>
                <button type="button" class="modal-footer-btn danger" :disabled="deleteConfirm.deleting" @click="confirmDeleteActivity()">
                    <span x-show="!deleteConfirm.deleting"><i class="fa-solid fa-trash-alt"></i> Sí, eliminar</span>
                    <span x-show="deleteConfirm.deleting">Eliminando...</span>
                </button>
            </div>
        </div>
    </div>

    {{-- Grade Slide-over --}}
    <div x-show="gradesSlideover.open" x-cloak class="grades-slideover-wrap" @keydown.escape.window="closeGradesSlideover()">
        <div class="grades-slideover-backdrop" @click="closeGradesSlideover()"></div>
        <aside class="grades-slideover-panel" @click.stop>
            <div class="grades-slideover-header">
                <div>
                    <p class="grades-slideover-eyebrow">AulaSync · Cargar Notas</p>
                    <h3 x-text="gradesSlideover.activity?.title || 'Cargar notas'"></h3>
                    <p class="grades-slideover-subtitle" x-text="gradesSlideover.activity?.course_name || ''"></p>
                </div>
                <div style="display:flex; align-items:center; gap:10px;">
                    <button class="btn-primary"
                        style="padding: 10px 14px;"
                        :disabled="gradesSlideover.loading || gradesSlideover.publishing"
                        @click="gradesSlideover.confirmPublish = true">
                        🚀 Publicar Notas
                    </button>
                    <button @click="closeGradesSlideover()" class="modal-close">
                        <i class="fa-solid fa-times"></i>
                    </button>
                </div>
            </div>

            <div class="grades-slideover-meta">
                <span class="meta-chip">
                    <i class="fa-solid fa-star"></i>
                    Máx: <strong x-text="gradeMaxForActivity(gradesSlideover.activity)"></strong>
                    <span style="opacity:.75;">(<span x-text="courseData?.grading_scale_label || '1 al 20'"></span>)</span>
                </span>
                <span class="meta-chip">
                    <i class="fa-solid fa-chart-line"></i>
                    Promedio act.: <strong x-text="gradesSlideover.activity?.avg_score ?? '—'"></strong>
                </span>
                <span class="meta-chip">
                    <i class="fa-solid fa-users"></i>
                    <strong x-text="`${gradesSlideover.activity?.graded_count ?? 0}/${gradesSlideover.activity?.total_students ?? 0}`"></strong> cargadas
                </span>
            </div>

            <div class="grades-slideover-body">
                <div x-show="gradesSlideover.confirmPublish" x-cloak class="publish-confirm-box">
                    <p>¿Confirmas publicar las notas de esta actividad para que sean visibles por estudiantes?</p>
                    <div class="publish-confirm-actions">
                        <button class="btn-secondary" @click="gradesSlideover.confirmPublish = false">Cancelar</button>
                        <button class="btn-primary" :disabled="gradesSlideover.publishing" @click="publishGrades()">
                            <span x-show="!gradesSlideover.publishing">Sí, publicar</span>
                            <span x-show="gradesSlideover.publishing">Publicando...</span>
                        </button>
                    </div>
                </div>

                <template x-if="gradesSlideover.loading">
                    <div class="skeleton-nova" style="height: 240px;"></div>
                </template>

                <template x-if="!gradesSlideover.loading && gradesSlideover.error">
                    <p class="grades-inline-error" x-text="gradesSlideover.error"></p>
                </template>

                <template x-if="!gradesSlideover.loading && !gradesSlideover.error">
                    <div class="grades-table-wrap">
                        <table class="grades-table">
                            <thead>
                                <tr>
                                    <th>Alumno</th>
                                    <th>Nota</th>
                                    <th>Estado</th>
                                    <th>Acumulado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="(student, idx) in gradesSlideover.students" :key="student.id">
                                    <tr>
                                        <td>
                                            <div class="student-grade-cell">
                                                <div class="student-avatar" x-text="initials(student.name)"></div>
                                                <span x-text="student.name"></span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="grade-input-wrap">
                                                <input type="number"
                                                    class="grade-input"
                                                    :max="gradeMaxForActivity(gradesSlideover.activity)"
                                                    min="0"
                                                    step="0.01"
                                                    x-model="student.score"
                                                    :data-grade-index="idx"
                                                    @blur="persistGrade(student)"
                                                    @change="persistGrade(student)"
                                                    @keydown="handleGradeInputKeydown($event, student, idx)">
                                                <i x-show="gradesSlideover.savedPulse[student.id]"
                                                   x-transition.opacity.duration.200ms
                                                   class="fa-solid fa-circle-check grade-saved-icon"></i>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-[11px] font-semibold"
                                                :class="gradeStatusClass(student)">
                                                <i class="fa-solid fa-spinner animate-spin"
                                                    x-show="gradesSlideover.rowState?.[student.id] === 'saving'"></i>
                                                <i class="fa-solid fa-circle text-[6px]"
                                                    x-show="gradesSlideover.rowState?.[student.id] === 'draft'"></i>
                                                <i class="fa-solid fa-circle-check"
                                                    x-show="['saved','published'].includes(gradesSlideover.rowState?.[student.id])"></i>
                                                <span x-text="gradeStatusLabel(student)"></span>
                                            </span>
                                        </td>
                                        <td>
                                            <span x-text="student.nota_actual ?? '—'"></span>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </template>
            </div>

            <div class="grades-slideover-footer">
                <button @click="closeGradesSlideover()" class="btn-secondary">Cerrar</button>
            </div>
        </aside>
    </div>

    {{-- Class Attendance Slide-over --}}
    <div x-show="classAttendance.open" x-cloak class="grades-slideover-wrap" @keydown.escape.window="closeClassAttendance()">
        <div class="grades-slideover-backdrop" @click="closeClassAttendance()"></div>
        <aside class="grades-slideover-panel" @click.stop>
            <div class="grades-slideover-header">
                <div>
                    <p class="grades-slideover-eyebrow">AulaSync · Tomar Asistencia</p>
                    <h3 x-text="classAttendance.course_name || 'Asistencia'"></h3>
                    <p class="grades-slideover-subtitle" x-text="classAttendance.date"></p>
                </div>
                <div style="display:flex; align-items:center; gap:10px;">
                    <button class="btn-primary" style="padding: 10px 14px;"
                        :disabled="classAttendance.loading || classAttendance.saving || !classAttendance.roster.length"
                        @click="saveClassAttendance()">
                        <span x-show="!classAttendance.saving">💾 Guardar asistencia</span>
                        <span x-show="classAttendance.saving">Guardando…</span>
                    </button>
                    <button @click="closeClassAttendance()" class="modal-close">
                        <i class="fa-solid fa-times"></i>
                    </button>
                </div>
            </div>

            <div class="grades-slideover-meta">
                <span class="meta-chip">
                    <i class="fa-solid fa-users"></i>
                    <strong x-text="classAttendance.roster.length"></strong> estudiantes
                </span>
                <span class="meta-chip" x-show="classAttendance.taken">
                    <i class="fa-solid fa-circle-check"></i>
                    Ya registrada — puedes editarla
                </span>
                <a class="meta-chip" :href="`{{ route('teacher.attendance.index') }}?course_id=${classAttendance.course_id || ''}&date=${classAttendance.date || ''}`" style="text-decoration:none;">
                    <i class="fa-solid fa-up-right-from-square"></i>
                    Abrir módulo completo
                </a>
            </div>

            <div class="grades-slideover-body">
                <p class="ok" x-show="classAttendance.message" x-text="classAttendance.message" style="margin: 0 0 10px;"></p>
                <template x-if="classAttendance.loading">
                    <div class="skeleton-nova" style="height: 240px;"></div>
                </template>

                <template x-if="!classAttendance.loading && classAttendance.error">
                    <p class="grades-inline-error" x-text="classAttendance.error"></p>
                </template>

                <template x-if="!classAttendance.loading && !classAttendance.error">
                    <div class="grades-table-wrap">
                        <table class="grades-table">
                            <thead>
                                <tr>
                                    <th>Alumno</th>
                                    <th>Estado</th>
                                    <th>Motivo</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="row in classAttendance.roster" :key="row.student_id">
                                    <tr>
                                        <td>
                                            <div class="student-grade-cell">
                                                <div class="student-avatar" x-text="initials(row.name)"></div>
                                                <span x-text="row.name"></span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="status-btns" style="display:flex; gap:6px; flex-wrap:wrap;">
                                                <button type="button" class="attendance-status-btn"
                                                    :class="{ 'is-active present': row.status === 'present' }"
                                                    @click="setClassAttendanceStatus(row, 'present')">Presente</button>
                                                <button type="button" class="attendance-status-btn"
                                                    :class="{ 'is-active tardy': row.status === 'tardy' }"
                                                    @click="setClassAttendanceStatus(row, 'tardy')">Tarde</button>
                                                <button type="button" class="attendance-status-btn"
                                                    :class="{ 'is-active absent': row.status === 'absent' }"
                                                    @click="setClassAttendanceStatus(row, 'absent')">Ausente</button>
                                            </div>
                                        </td>
                                        <td>
                                            <select x-show="row.status === 'absent'" x-model="row.reason_id" class="attendance-reason-select">
                                                <option value="">Sin justificar</option>
                                                <template x-for="reason in attendanceReasons" :key="reason.id">
                                                    <option :value="reason.id" x-text="reason.label"></option>
                                                </template>
                                            </select>
                                            <span x-show="row.status !== 'absent'" style="color: var(--text-tertiary); font-size: 12px;">—</span>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </template>
            </div>

            <div class="grades-slideover-footer">
                <button @click="closeClassAttendance()" class="btn-secondary">Cerrar</button>
            </div>
        </aside>
    </div>

    {{-- Student Slide-over --}}
    <div x-show="studentSlideover.open" x-cloak class="grades-slideover-wrap" @keydown.escape.window="closeStudentSlideover()">
        <div class="grades-slideover-backdrop" @click="closeStudentSlideover()"></div>

        <aside class="grades-slideover-panel" @click.stop>
            <div class="grades-slideover-header">
                <div>
                    <p class="grades-slideover-eyebrow">AulaSync · Ficha del Alumno</p>
                    <h3 x-text="studentSlideover.student?.name || 'Alumno'"></h3>
                    <p class="grades-slideover-subtitle" x-text="studentSlideover.courseName || ''"></p>
                </div>
                <button @click="closeStudentSlideover()" class="modal-close">
                    <i class="fa-solid fa-times"></i>
                </button>
            </div>

            <div class="grades-slideover-meta">
                <span class="meta-chip">
                    <i class="fa-solid fa-chart-line"></i>
                    Promedio Acumulado:
                    <strong x-text="formatAcum(studentSlideover.student?.promedio_acumulado)"></strong>
                </span>
                <span class="meta-chip" x-show="studentSlideover.student?.document_id">
                    <i class="fa-solid fa-id-card"></i>
                    Cédula:
                    <strong x-text="studentSlideover.student?.document_id"></strong>
                </span>
                <span class="meta-chip" x-show="studentSlideover.student?.grade">
                    <i class="fa-solid fa-graduation-cap"></i>
                    <strong x-text="[studentSlideover.student?.grade, studentSlideover.student?.section].filter(Boolean).join(' / ')"></strong>
                </span>
                <span class="meta-chip" x-show="studentSlideover.student?.attendance_percentage !== null && studentSlideover.student?.attendance_percentage !== undefined">
                    <i class="fa-solid fa-clipboard-user"></i>
                    Asistencia:
                    <strong x-text="studentSlideover.student?.attendance_percentage + '%'"></strong>
                    <span style="opacity:.7; font-size:11px;" x-text="`(${studentSlideover.student?.attendance_present ?? 0} pres. · ${studentSlideover.student?.attendance_tardy ?? 0} tarde · ${studentSlideover.student?.attendance_absent ?? 0} aus.)`"></span>
                </span>
            </div>

            <div class="grades-slideover-body">
                <div x-show="studentSlideover.student?.has_family_code" class="mb-4 rounded-2xl border p-3" style="border-color: var(--nova-glass-border); background: var(--bg-secondary);">
                    <p class="text-[11px] font-bold uppercase tracking-widest mb-2" style="color: var(--nova-violet);">Código familiar (representante)</p>
                    <div x-show="!studentSlideover.familyUnlocked">
                        <button type="button" class="btn-secondary" @click="studentSlideover.showPin = true" style="font-size:12px;">
                            <i class="fa-solid fa-lock" style="margin-right:6px;"></i> Ver código con PIN
                        </button>
                    </div>
                    <div x-show="studentSlideover.familyUnlocked" x-cloak class="inline-flex items-center gap-2">
                        <code class="select-all rounded-lg border border-cyan-500/20 bg-cyan-500/10 px-3 py-1.5 font-mono text-xs font-bold tracking-widest text-cyan-300" x-text="studentSlideover.familyCode"></code>
                        <span class="text-xs text-slate-400" x-text="studentSlideover.familySeconds + 's'"></span>
                        <button type="button" class="btn-secondary" style="font-size:11px;padding:4px 8px;" @click="navigator.clipboard.writeText(studentSlideover.familyCode)">Copiar</button>
                    </div>
                    <p x-show="studentSlideover.pinError" class="grades-inline-error mt-2" x-text="studentSlideover.pinError" style="margin-top:8px;"></p>
                </div>

                <template x-if="studentSlideover.loading">
                    <div class="skeleton-nova" style="height: 220px;"></div>
                </template>

                <template x-if="!studentSlideover.loading && studentSlideover.error">
                    <p class="grades-inline-error" x-text="studentSlideover.error"></p>
                </template>

                <template x-if="!studentSlideover.loading && !studentSlideover.error && studentSlideover.activities.length === 0">
                    <p class="text-sm text-slate-400" style="padding:12px 0;">Aún no hay actividades calificables en este curso. La ficha del alumno está activa.</p>
                </template>

                <template x-if="!studentSlideover.loading && !studentSlideover.error && studentSlideover.activities.length > 0">
                    <div class="grades-table-wrap">
                        <table class="grades-table">
                            <thead>
                                <tr>
                                    <th>Actividad / Tarea</th>
                                    <th>Ponderación</th>
                                    <th>Nota</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="row in studentSlideover.activities" :key="row.activity_id">
                                    <tr>
                                        <td>
                                            <div class="student-grade-cell">
                                                <span x-text="row.title"></span>
                                            </div>
                                        </td>
                                        <td><span x-text="`${row.weight_percentage}%`"></span></td>
                                        <td><span x-text="row.score ?? '—'"></span></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </template>
            </div>

            <div class="grades-slideover-footer">
                <a :href="`/teacher/report-card/${studentSlideover.student?.id}`" class="btn-secondary" x-show="studentSlideover.student?.id" style="text-decoration:none;display:inline-flex;align-items:center;">
                    Ver boletín
                </a>
                <button @click="closeStudentSlideover()" class="btn-secondary">Cerrar</button>
            </div>
        </aside>

        <div x-show="studentSlideover.showPin" x-cloak class="modal-overlay" style="z-index:90;" @click.self="studentSlideover.showPin = false">
            <div class="mini-modal" @click.stop>
                <div class="modal-header">
                    <h3><i class="fa-solid fa-lock" style="margin-right:8px;"></i> PIN del colegio</h3>
                    <button @click="studentSlideover.showPin = false" class="modal-close"><i class="fa-solid fa-times"></i></button>
                </div>
                <div class="modal-body">
                    <p style="font-size:13px;color:var(--text-secondary);margin-bottom:12px;">El código familiar se mostrará solo 20 segundos.</p>
                    <input type="password" inputmode="numeric" maxlength="6" x-model="studentSlideover.pin" @keydown.enter="revealStudentFamilyCode()" placeholder="PIN de 4 a 6 dígitos" class="form-control" style="text-align:center;letter-spacing:.3em;font-family:monospace;">
                    <p x-show="studentSlideover.pinError" class="grades-inline-error" x-text="studentSlideover.pinError" style="margin-top:8px;"></p>
                </div>
                <div class="modal-footer" style="display:flex;gap:8px;justify-content:flex-end;">
                    <button type="button" class="btn-secondary" @click="studentSlideover.showPin = false">Cancelar</button>
                    <button type="button" class="btn-primary" @click="revealStudentFamilyCode()">Desbloquear</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Mini modal: sugerencia de tarea --}}
    <div x-show="taskIdeaModalOpen" x-cloak class="modal-overlay" @click.self="taskIdeaModalOpen = false">
        <div class="mini-modal">
            <div class="modal-header">
                <h3><i class="fa-solid fa-lightbulb" style="color: var(--nova-fuchsia); margin-right: 8px;"></i> Sugerencia de tarea</h3>
                <button @click="taskIdeaModalOpen = false" class="modal-close">
                    <i class="fa-solid fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div x-show="taskLoading" class="skeleton-nova" style="height: 96px;"></div>
                <div x-show="!taskLoading">
                    <label style="display:block; font-size:12px; color: var(--text-secondary); margin-bottom:6px;">Título sugerido</label>
                    <input x-model="taskForm.titulo"
                           :disabled="!taskAccepted"
                           style="width:100%; background: var(--nova-glass); color: var(--text-primary); border:1px solid var(--nova-glass-border); border-radius:12px; padding:10px 12px; font-size:13px;">

                    <label style="display:block; font-size:12px; color: var(--text-secondary); margin:12px 0 6px;">Descripción</label>
                    <textarea x-model="taskForm.descripcion"
                              :disabled="!taskAccepted"
                              rows="3"
                              style="width:100%; background: var(--nova-glass); color: var(--text-primary); border:1px solid var(--nova-glass-border); border-radius:12px; padding:10px 12px; font-size:13px;"></textarea>

                    <div style="display:grid; grid-template-columns: 1fr 120px; gap: 10px; margin-top: 12px;">
                        <div>
                            <label style="display:block; font-size:12px; color: var(--text-secondary); margin-bottom:6px;">Fecha de entrega</label>
                            <input type="date"
                                   x-model="taskForm.fecha_entrega"
                                   :disabled="!taskAccepted"
                                   style="width:100%; background: var(--nova-glass); color: var(--text-primary); border:1px solid var(--nova-glass-border); border-radius:12px; padding:10px 12px; font-size:13px;">
                        </div>
                        <div>
                            <label style="display:block; font-size:12px; color: var(--text-secondary); margin-bottom:6px;">Puntos</label>
                            <input type="number"
                                   min="1"
                                   x-model.number="taskForm.puntos"
                                   :disabled="!taskAccepted"
                                   style="width:100%; background: var(--nova-glass); color: var(--text-primary); border:1px solid var(--nova-glass-border); border-radius:12px; padding:10px 12px; font-size:13px;">
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button @click="generateTaskIdea()" class="btn-secondary">
                    <i class="fa-solid fa-rotate-right"></i> Regenerar 🔄
                </button>
                <button @click="acceptTaskIdea()" class="btn-secondary" :disabled="taskLoading">
                    <i class="fa-solid fa-check"></i> Aceptar ✅
                </button>
                <button @click="saveTask()" class="btn-primary" :disabled="taskSaving || !taskAccepted">
                    <i class="fa-solid fa-floppy-disk"></i> Guardar 💾
                </button>
            </div>
        </div>
    </div>

    {{-- Mini modal: adaptación NEE --}}
    <div x-show="neeModalOpen" x-cloak class="modal-overlay" @click.self="neeModalOpen = false">
        <div class="mini-modal">
            <div class="modal-header">
                <h3><i class="fa-solid fa-book-open-reader" style="color: var(--nova-fuchsia); margin-right: 8px;"></i> Adaptación NEE</h3>
                <button @click="neeModalOpen = false" class="modal-close">
                    <i class="fa-solid fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div x-show="neeLoading" class="skeleton-nova" style="height: 96px;"></div>
                <div x-show="!neeLoading">
                    <label style="display:block; font-size:12px; color: var(--text-secondary); margin-bottom:6px;">Tipo de condición</label>
                    <select x-model="neeForm.tipo"
                            :disabled="neeAccepted"
                            style="width:100%; background: var(--nova-glass); color: var(--text-primary); border:1px solid var(--nova-glass-border); border-radius:12px; padding:10px 12px; font-size:13px;">
                        <option value="">Selecciona…</option>
                        <option value="TDAH">🧠 TDAH</option>
                        <option value="TEA/Autismo">🧩 TEA/Autismo</option>
                        <option value="Dislexia">📖 Dislexia</option>
                        <option value="Discalculia">🔢 Discalculia</option>
                        <option value="Otro">⭐ Otro</option>
                    </select>

                    <label style="display:block; font-size:12px; color: var(--text-secondary); margin:12px 0 6px;">Adaptación sugerida</label>
                    <textarea x-model="neeForm.texto"
                              :disabled="!neeAccepted"
                              rows="4"
                              style="width:100%; background: var(--nova-glass); color: var(--text-primary); border:1px solid var(--nova-glass-border); border-radius:12px; padding:10px 12px; font-size:13px;"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button @click="generateNeeAdaptation()" class="btn-secondary" :disabled="!neeForm.tipo">
                    <i class="fa-solid fa-rotate-right"></i> Regenerar 🔄
                </button>
                <button @click="acceptNeeAdaptation()" class="btn-secondary" :disabled="neeLoading || !neeForm.texto">
                    <i class="fa-solid fa-check"></i> Aceptar ✅
                </button>
                <button @click="saveNeeAdaptation()" class="btn-primary" :disabled="neeSaving || !neeAccepted">
                    <i class="fa-solid fa-floppy-disk"></i> Guardar 💾
                </button>
            </div>
        </div>
    </div>

    {{-- Day Modal --}}
    <div x-show="dayModal" x-cloak class="modal-overlay" @click.self="dayModal = null" @keydown.escape.window="dayModal = null">
        <div class="modal-nova" style="max-width: 860px;">
            <div class="modal-header" style="background: var(--nova-gradient);">
                <div>
                    <p style="margin:0 0 4px;font-size:11px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:rgba(255,255,255,.75)">Agenda del día</p>
                    <h3 style="color: white; margin: 0; text-transform: capitalize;" x-text="dayModal?.dateLabel || ('Día ' + dayModal?.day)"></h3>
                </div>
                <button @click="dayModal = null" class="modal-close" style="background: rgba(255,255,255,0.2); color: white;">
                    <i class="fa-solid fa-times"></i>
                </button>
            </div>
            <div class="modal-body day-modal-content">
                <p x-show="!(dayModal?.activities || []).length" style="color: var(--text-secondary); font-size: 14px; padding: 12px 0;">
                    No hay clases, tareas ni evaluaciones este día.
                </p>
                <div class="day-agenda">
                    <template x-for="row in (dayModal?.agendaRows ?? [])" :key="'row-'+row.hour">
                        <div class="day-agenda-row">
                            <div class="day-agenda-hour" x-text="row.hour"></div>
                            <div class="day-agenda-cards">
                                <template x-if="!(row.activities || []).length">
                                    <div class="day-agenda-empty">Sin actividad programada</div>
                                </template>
                                <template x-for="act in (row.activities || [])" :key="'agenda-'+act.id">
                                    <button type="button"
                                            @click="setActivityContext(act); openActivityModal(act); dayModal = null"
                                            class="day-agenda-card"
                                            :style="`--grade-accent: ${act.grade_color || gradeColor(act.grade)}`">
                                        <div class="day-agenda-card-top">
                                            <span class="day-agenda-time" x-text="act.time_range || act.time_label || 'Sin hora'"></span>
                                            <span class="status-pill" style="margin-left:auto;" x-text="act.grade || 'Sin grado'"></span>
                                        </div>
                                        <p class="day-agenda-title" x-text="act.title"></p>
                                        <p class="day-agenda-meta" x-text="`${act.course_name || ''} · ${act.type || 'actividad'}`"></p>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>

    {{-- New Course Modal --}}
    <div x-show="showNewCourseModal" x-cloak class="modal-overlay" @click.self="showNewCourseModal = false">
        <div class="modal-nova" style="max-width: 450px;">
            <div class="modal-header">
                <h3>Nuevo Curso</h3>
                <button @click="showNewCourseModal = false" class="modal-close">
                    <i class="fa-solid fa-times"></i>
                </button>
            </div>
            <form method="POST" action="{{ route('teacher.courses.store') }}">
                @csrf
                <div class="modal-body">
                    <div style="display: flex; flex-direction: column; gap: 15px;">
                        <div>
                            <label style="display: block; color: var(--text-secondary); font-size: 12px; font-weight: 600; margin-bottom: 6px;">Materia *</label>
                            <input name="subject_name" required placeholder="Ej: Matemáticas"
                                   style="width: 100%; background: var(--nova-glass); border: 1px solid var(--nova-glass-border); border-radius: 14px; padding: 12px 15px; color: var(--text-primary); font-size: 14px;">
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                            <div>
                                <label style="display: block; color: var(--text-secondary); font-size: 12px; font-weight: 600; margin-bottom: 6px;">Grado *</label>
                                <input name="grade" required placeholder="Ej: 3ro"
                                       style="width: 100%; background: var(--nova-glass); border: 1px solid var(--nova-glass-border); border-radius: 14px; padding: 12px 15px; color: var(--text-primary); font-size: 14px;">
                            </div>
                            <div>
                                <label style="display: block; color: var(--text-secondary); font-size: 12px; font-weight: 600; margin-bottom: 6px;">Sección</label>
                                <input name="section" placeholder="Ej: A"
                                       style="width: 100%; background: var(--nova-glass); border: 1px solid var(--nova-glass-border); border-radius: 14px; padding: 12px 15px; color: var(--text-primary); font-size: 14px;">
                            </div>
                        </div>
                        <div>
                            <label style="display: block; color: var(--text-secondary); font-size: 12px; font-weight: 600; margin-bottom: 6px;">Año escolar</label>
                            <input name="school_year" value="{{ date('Y') . '-' . (date('Y')+1) }}"
                                   style="width: 100%; background: var(--nova-glass); border: 1px solid var(--nova-glass-border); border-radius: 14px; padding: 12px 15px; color: var(--text-primary); font-size: 14px;">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" @click="showNewCourseModal = false" class="btn-secondary">Cancelar</button>
                    <button type="submit" class="btn-primary">
                        <i class="fa-solid fa-plus"></i>
                        Crear Curso
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div x-show="showEnrollModal" x-cloak class="modal-overlay" @click.self="showEnrollModal = false">
        <div class="modal-nova" style="max-width: 520px;">
            <div class="modal-header">
                <h3>Vincular alumno del colegio</h3>
                <button @click="showEnrollModal = false" class="modal-close"><i class="fa-solid fa-times"></i></button>
            </div>
            <div class="modal-body" style="display:flex;flex-direction:column;gap:12px;">
                <p style="font-size:13px;color:var(--text-secondary);margin:0;">Solo puedes inscribir alumnos que el director ya matriculó. No se crean registros nuevos.</p>
                <input x-model="enrollSearch" @input="searchSchoolStudents()" placeholder="Buscar por nombre, cédula o código NV-" style="width:100%;background:var(--nova-glass);border:1px solid var(--nova-glass-border);border-radius:14px;padding:12px 15px;color:var(--text-primary);">
                <template x-for="hit in enrollHits" :key="hit.id">
                    <button type="button" @click="enrollExisting(hit.id)" style="display:block;width:100%;text-align:left;margin-top:8px;background:var(--bg-secondary);border:0;border-radius:10px;padding:8px 10px;color:var(--text-primary);cursor:pointer;">
                        Inscribir a <span x-text="hit.name"></span>
                        <span style="opacity:.6;font-size:12px;" x-text="(hit.grade ? ' · ' + hit.grade : '') + (hit.family_code ? ' · ' + hit.family_code : '')"></span>
                    </button>
                </template>
                <p x-show="enrollNotice" style="color:#0F766E;font-weight:700;font-size:13px;" x-text="enrollNotice"></p>
                <p x-show="enrollError" style="color:#B45309;font-weight:700;font-size:13px;" x-text="enrollError"></p>
            </div>
            <div class="modal-footer">
                <button type="button" @click="showEnrollModal = false" class="btn-secondary">Cerrar</button>
            </div>
        </div>
    </div>

    <div x-show="showCourseSettingsModal" x-cloak class="modal-overlay" @click.self="showCourseSettingsModal = false" @keydown.escape.window="showCourseSettingsModal = false">
        <div class="modal-nova" style="max-width: 480px;">
            <div class="modal-header">
                <h3>Gestionar curso</h3>
                <button type="button" @click="showCourseSettingsModal = false" class="modal-close"><i class="fa-solid fa-times"></i></button>
            </div>
            <div class="modal-body">
                <p style="font-size:13px;color:var(--text-secondary);margin-bottom:16px;">
                    Configura la escala de calificación de <strong x-text="courseData?.subject_name"></strong>.
                    Las notas ya guardadas no se modifican.
                </p>
                <label style="display:block;font-size:12px;font-weight:700;color:var(--text-secondary);margin-bottom:8px;">Escala de notas</label>
                <select x-model="courseSettingsForm.grading_scale" style="width:100%;padding:12px 14px;border-radius:14px;border:1px solid var(--nova-glass-border);background:var(--bg-secondary);color:var(--text-primary);">
                    <option value="1-5">1 al 5</option>
                    <option value="1-10">1 al 10</option>
                    <option value="1-20">1 al 20</option>
                </select>
                <p style="font-size:12px;color:var(--text-tertiary);margin-top:10px;line-height:1.5;">
                    Afecta validaciones de nuevas calificaciones, el asistente IA y la visualización del acumulado.
                </p>
            </div>
            <div class="modal-footer">
                <a href="{{ route('teacher.courses.index') }}" class="btn-secondary" style="margin-right:auto;text-decoration:none;">Gestión avanzada</a>
                <button type="button" class="btn-secondary" @click="showCourseSettingsModal = false">Cancelar</button>
                <button type="button" class="btn-primary" @click="saveCourseGradingScale()" :disabled="courseSettingsSaving">
                    <span x-show="!courseSettingsSaving">Guardar escala</span>
                    <span x-show="courseSettingsSaving">Guardando…</span>
                </button>
            </div>
        </div>
    </div>

</div>

{{-- AI Assistant bubble --}}
@include('components.ai-assistant-bubble')

<script>
// Theme toggle functionality
window.addEventListener('open-ai-bubble', () => {
    const root = document.getElementById('ai-assistant-root')?.__x;
    if (root) root.$data.open = true;
});

function teacherHub() {
    return {
        sidebarOpen:     false,
        createMenuOpen:  false,
        dashQuery:       '',
        view:            'welcome',
        canvasLoading:   false,
        coursesLoading:  false,
        stats:           null,
        courses:         [],
        courseData:      null,
        currentCourseId: null,
        planBlockFilter: {{ $initialPlanBlock ?? 'null' }},
        calendarData:    null,
        calendarMonth:   null,
        calendarGradeFilter: 'all',
        nextQueueOpen:   false,
        hiddenWidgets:   [],
        activityModal:   null,
        deleteConfirm:   { open: false, id: null, title: '', deleting: false },
        phaseEdit: {
            template: 'clasica',
            values: {},
            draft: {},
            editing: null,
            saving: null,
        },
        studentSlideover: {
            open: false,
            loading: false,
            error: null,
            student: null,
            activities: [],
            courseName: '',
            has_family_code: false,
            showPin: false,
            pin: '',
            pinError: '',
            familyUnlocked: false,
            familyCode: '',
            familySeconds: 0,
            familyTimer: null,
        },
        gradesSlideover: {
            open: false,
            loading: false,
            publishing: false,
            confirmPublish: false,
            error: null,
            activity: null,
            students: [],
            savedPulse: {},
            rowState: {},
        },
        attendanceReasons: @json($attendanceReasons ?? []),
        classAttendance: {
            open: false,
            loading: false,
            saving: false,
            activity_id: null,
            course_id: null,
            course_name: '',
            date: '',
            taken: false,
            roster: [],
            error: '',
            message: '',
        },
        notesSaving: false,
        dayModal:        null,
        taskIdeaModalOpen: false,
        taskLoading:     false,
        taskSaving:      false,
        taskAccepted:    false,
        taskForm: {
            titulo: '',
            descripcion: '',
            fecha_entrega: '',
            puntos: 20,
        },
        neeModalOpen: false,
        neeLoading: false,
        neeSaving: false,
        neeAccepted: false,
        neeForm: {
            tipo: '',
            texto: '',
        },
        showNewCourseModal: false,
        showEnrollModal: false,
        showCourseSettingsModal: false,
        courseSettingsSaving: false,
        courseSettingsForm: { grading_scale: '1-20' },
        enrollTab: 'new',
        enrollSaving: false,
        enrollNotice: '',
        enrollError: '',
        enrollSearch: '',
        enrollHits: [],
        enrollForm: { name: '', document_id: '', birthdate: '', sibling_student_id: '' },
        isDarkMode:      false,
        notifications:   [],
        unreadCount:     0,
        showNotifications: false,
        showThemePicker: false,
        currentThemeId: document.documentElement.getAttribute('data-theme') || 'light',
        get themeOptions() {
            return window.AULA_THEMES || [];
        },

        applyTheme(themeId) {
            if (window.applyAulaTheme) window.applyAulaTheme(themeId);
            this.currentThemeId = document.documentElement.getAttribute('data-theme') || themeId;
            this.isDarkMode = document.documentElement.classList.contains('dark');
            this.showThemePicker = false;
        },

        toggleTheme() {
            this.showThemePicker = !this.showThemePicker;
        },

        async loadNotifications() {
            try {
                const res = await fetch('/notifications', {
                    headers: { 'Accept': 'application/json' }
                });
                const data = await res.json();
                if (data.success) {
                    this.notifications = data.notifications || [];
                    this.unreadCount = data.unread_count || 0;
                }
            } catch (e) {
                // Silently fail
            }
        },

        toggleNotifications() {
            this.showNotifications = !this.showNotifications;
            if (this.showNotifications) {
                this.loadNotifications();
            }
        },

        async markAsRead(id) {
            try {
                const res = await fetch(`/notifications/${id}/read`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                        'Accept': 'application/json'
                    }
                });
                const data = await res.json();
                if (data.success) {
                    this.unreadCount = data.unread_count || 0;
                    const n = this.notifications.find(n => n.id === id);
                    if (n) n.read_at = new Date().toISOString();
                }
            } catch (e) {
                console.warn('Mark notification read failed', e);
            }
        },

        async markAllNotificationsRead() {
            try {
                const res = await fetch('/notifications/read-all', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                        'Accept': 'application/json'
                    }
                });
                const data = await res.json();
                if (data.success) {
                    this.unreadCount = 0;
                    this.notifications.forEach(n => n.read_at = new Date().toISOString());
                }
            } catch (e) {
                console.warn('Mark all read failed', e);
            }
        },

        notificationLink(n) {
            if (n.link) return n.link;
            return '/teacher/hub';
        },

        async handleNotificationClick(n) {
            await this.markAsRead(n.id);
            this.showNotifications = false;

            const href = this.notificationLink(n);
            try {
                const url = new URL(href, window.location.origin);
                const activityId = Number(
                    url.searchParams.get('open_activity')
                    || url.searchParams.get('activity')
                    || 0
                );
                if (activityId > 0) {
                    await this.openActivityModalFromExternal({ id: activityId });
                    return;
                }
            } catch (e) {}

            if (href && href !== window.location.href) {
                window.location.href = href;
            }
        },

        closeSidebarMobile() {
            if (window.matchMedia('(max-width: 767px)').matches) {
                this.sidebarOpen = false;
            }
        },

        async init() {
            // Initialize theme state
            this.isDarkMode = document.documentElement.classList.contains('dark');
            this.currentThemeId = document.documentElement.getAttribute('data-theme') || 'light';
            window.addEventListener('aula-theme-changed', (event) => {
                this.currentThemeId = event.detail?.id || document.documentElement.getAttribute('data-theme') || 'light';
                this.isDarkMode = document.documentElement.classList.contains('dark');
            });
            const params = new URLSearchParams(window.location.search);
            const shouldOpenGrades = params.get('open_grades') === '1';
            const targetActivityId = Number(params.get('activity') || 0);
            const openActivityId = Number(params.get('open_activity') || 0);
            const requestedView = params.get('view');

            await this.refreshCourseSidebar();

            const urlCourse = {{ $initialCourseId ?? 'null' }};
            const deepLinkActivityId = openActivityId > 0 ? openActivityId : (shouldOpenGrades ? 0 : targetActivityId);

            if (this.planBlockFilter || requestedView === 'calendar') {
                await this.loadCalendar();
            } else if (urlCourse) {
                await this.loadCourse(urlCourse);
                if (shouldOpenGrades && this.courseData?.activities?.length) {
                    let targetActivity = null;
                    if (targetActivityId > 0) {
                        targetActivity = this.courseData.activities.find(a => Number(a.id) === targetActivityId) || null;
                    }
                    if (!targetActivity) {
                        targetActivity = this.courseData.activities.find(a => a.type !== 'clase') || null;
                    }
                    if (targetActivity) {
                        this.openGradesSlideover(targetActivity);
                    }
                }
            } else {
                await this.loadWelcome();
            }

            // Deep-link: abrir modal de clase desde notificación (?open_activity=ID)
            if (deepLinkActivityId > 0 && !shouldOpenGrades) {
                this.$nextTick(() => this.openActivityModalFromExternal({ id: deepLinkActivityId }));
            }

            this.setNovaContext(null);

            if (this.courses.length === 0 && !this.planBlockFilter) {
                setTimeout(() => this.openBubbleWithFocus(), 1800);
            }

            window.addEventListener('ai-ui-pref', (e) => {
                const { widget, visible } = e.detail ?? {};
                if (!widget) return;
                if (visible) {
                    this.hiddenWidgets = this.hiddenWidgets.filter(w => w !== widget);
                } else {
                    if (!this.hiddenWidgets.includes(widget)) this.hiddenWidgets.push(widget);
                }
            });

            window.addEventListener('ai-canvas-refresh', () => {
                if (this.view === 'course' && this.currentCourseId) {
                    this.loadCourse(this.currentCourseId);
                } else if (this.view === 'calendar') {
                    this.loadCalendar(this.calendarMonth);
                } else {
                    this.loadWelcome();
                }
                this.refreshCourseSidebar();
                return true;
            });

            window.addEventListener('open-activity-modal', (e) => {
                this.openActivityModalFromExternal(e.detail ?? {});
            });

            window.matchMedia('(min-width: 768px)').addEventListener('change', (e) => {
                if (e.matches) {
                    this.sidebarOpen = false;
                }
            });

            this.loadNotifications();
            if (!this._notificationPollTimer) {
                this._notificationPollTimer = setInterval(() => {
                    if (!document.hidden && !this.showNotifications) {
                        this.loadNotifications();
                    }
                }, 10000);
                document.addEventListener('visibilitychange', () => {
                    if (!document.hidden) {
                        this.loadNotifications();
                    }
                });
            }
        },

        async loadWelcome() {
            this.view          = 'welcome';
            this.courseData    = null;
            this.currentCourseId = null;
            this.canvasLoading = true;
            this.setNovaContext(null);

            try {
                const res  = await fetch('{{ route('teacher.api.stats') }}', {
                    headers: { 'Accept': 'application/json' }
                });
                this.stats = await res.json();
            } catch (e) {
                console.warn('Stats fetch failed', e);
            } finally {
                this.canvasLoading = false;
                this.closeSidebarMobile();
            }
        },

        openEnrollModal() {
            this.showEnrollModal = true;
            this.enrollTab = 'existing';
            this.enrollNotice = '';
            this.enrollError = '';
            this.enrollSearch = '';
            this.enrollHits = [];
        },

        async searchSchoolStudents() {
            const q = this.enrollSearch.trim();
            if (q.length < 2) { this.enrollHits = []; return; }
            const res = await fetch(`/teacher/api/school-students?q=${encodeURIComponent(q)}`, { headers: { 'Accept': 'application/json' } });
            const data = await res.json();
            this.enrollHits = data.students || [];
        },

        async submitEnroll() {
            if (!this.enrollForm.name.trim()) { this.enrollError = 'Escribe el nombre completo.'; return; }
            this.enrollSaving = true;
            this.enrollError = '';
            this.enrollNotice = '';
            try {
                const res = await fetch('/teacher/api/students', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        name: this.enrollForm.name,
                        document_id: this.enrollForm.document_id || null,
                        birthdate: this.enrollForm.birthdate || null,
                        sibling_student_id: this.enrollForm.sibling_student_id || null,
                        course_id: this.currentCourseId,
                    }),
                });
                const data = await res.json();
                if (!data.success) { this.enrollError = data.message || data.error || 'No se pudo matricular.'; return; }
                this.enrollNotice = data.message;
                await this.loadCourse(this.currentCourseId);
                await this.refreshCourseSidebar();
            } catch (e) {
                this.enrollError = 'Error de conexión.';
            } finally {
                this.enrollSaving = false;
            }
        },

        async enrollExisting(studentId) {
            const res = await fetch(`/teacher/api/courses/${this.currentCourseId}/enroll`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ student_id: studentId }),
            });
            const data = await res.json();
            if (!data.success) { this.enrollError = data.message || 'No se pudo inscribir.'; return; }
            this.enrollNotice = data.message;
            await this.loadCourse(this.currentCourseId);
            await this.refreshCourseSidebar();
            if (data.students_count != null) {
                const course = (this.courses || []).find((c) => Number(c.id) === Number(this.currentCourseId));
                if (course) course.students_count = data.students_count;
            }
        },

        async loadCourse(id) {
            this.canvasLoading   = true;
            this.courseData      = null;
            this.currentCourseId = id;
            this.view            = 'course';

            try {
                const res  = await fetch(`/teacher/api/courses/${id}`, {
                    headers: { 'Accept': 'application/json' }
                });
                this.courseData = await res.json();

                const ctx = {
                    type:         'course',
                    id:           this.courseData.id,
                    name:         this.courseData.name,
                    subject_name: this.courseData.subject_name,
                    grade:        this.courseData.grade,
                    section:      this.courseData.section,
                    grading_scale: this.courseData.grading_scale,
                    grading_scale_max: this.courseData.grading_scale_max,
                    grading_scale_label: this.courseData.grading_scale_label,
                };
                this.setNovaContext(ctx);

            } catch (e) {
                console.warn('Course fetch failed', e);
                this.view = 'welcome';
            } finally {
                this.canvasLoading = false;
                this.closeSidebarMobile();
            }
        },

        openBubbleWithFocus() {
            const root = document.getElementById('ai-assistant-root')?.__x;
            if (root) {
                root.$data.open = true;
                this.$nextTick(() => {
                    document.querySelector('#ai-assistant-root textarea')?.focus();
                });
            }
        },

        courseScaleMax() {
            return Number(this.courseData?.grading_scale_max ?? 20);
        },

        formatAcum(value) {
            if (value === null || value === undefined || value === '' || value === '—') {
                return '—';
            }
            const num = Number(value);
            if (Number.isNaN(num)) {
                return '—';
            }
            return `${num.toFixed(1)} / ${this.courseScaleMax()}`;
        },

        gradeMaxForActivity(activity) {
            const scaleMax = this.courseScaleMax();
            const actMax = Number(activity?.max_score ?? scaleMax);
            return Math.min(actMax, scaleMax);
        },

        openCourseSettings() {
            this.courseSettingsForm.grading_scale = this.courseData?.grading_scale || '1-20';
            this.showCourseSettingsModal = true;
        },

        async saveCourseGradingScale() {
            if (!this.courseData?.id || this.courseSettingsSaving) {
                return;
            }

            this.courseSettingsSaving = true;
            try {
                const res = await fetch(`/teacher/api/courses/${this.courseData.id}/grading-scale`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    },
                    body: JSON.stringify({
                        grading_scale: this.courseSettingsForm.grading_scale,
                    }),
                });
                const json = await res.json();
                if (!res.ok || !json.success) {
                    throw new Error(json.error || json.message || 'No se pudo guardar la escala.');
                }

                this.courseData.grading_scale = json.grading_scale;
                this.courseData.grading_scale_max = json.grading_scale_max;
                this.courseData.grading_scale_label = json.grading_scale_label;

                this.setNovaContext({
                    type: 'course',
                    id: this.courseData.id,
                    name: this.courseData.name,
                    subject_name: this.courseData.subject_name,
                    grade: this.courseData.grade,
                    section: this.courseData.section,
                    grading_scale: json.grading_scale,
                    grading_scale_max: json.grading_scale_max,
                    grading_scale_label: json.grading_scale_label,
                });

                this.showCourseSettingsModal = false;
                this.showToast('Escala de calificación actualizada.', 'success', 'fa-check');
            } catch (e) {
                this.showToast(e.message || 'Error al guardar la escala.', 'error', 'fa-exclamation-triangle');
            } finally {
                this.courseSettingsSaving = false;
            }
        },

        // ── AulaSync Intelligence: saludo, resumen e insights (Fase 1) ──────

        greetingTitle() {
            const hour = new Date().getHours();
            const name = {{ Illuminate\Support\Js::from(explode(' ', auth()->user()->name)[0] ?? auth()->user()->name) }};
            const greeting = hour < 12 ? 'Buenos días' : (hour < 19 ? 'Buenas tardes' : 'Buenas noches');
            return `${greeting}, ${name}`;
        },

        greetingHello() {
            const name = {{ Illuminate\Support\Js::from(explode(' ', auth()->user()->name)[0] ?? auth()->user()->name) }};
            return `¡Hola, ${name}! 👋`;
        },

        formatDueParts(dateStr) {
            if (!dateStr) return { day: '—', mon: '' };
            const d = this.parseLocalDate(dateStr);
            if (!d) return { day: '—', mon: '' };
            return {
                day: String(d.getDate()).padStart(2, '0'),
                mon: d.toLocaleDateString('es', { month: 'short' }).replace('.', '').toUpperCase(),
            };
        },

        parseLocalDate(dateStr) {
            if (!dateStr) return null;
            const raw = String(dateStr);
            const iso = /^\d{4}-\d{2}-\d{2}$/.test(raw) ? `${raw}T12:00:00` : raw;
            const d = new Date(iso);
            return Number.isNaN(d.getTime()) ? null : d;
        },

        formatQueueWhen(dateStr) {
            const d = this.parseLocalDate(dateStr);
            if (!d) return '';
            const today = new Date();
            const startToday = new Date(today.getFullYear(), today.getMonth(), today.getDate());
            const startDate = new Date(d.getFullYear(), d.getMonth(), d.getDate());
            const diff = Math.round((startDate - startToday) / 86400000);
            if (diff === 0) return 'Hoy';
            if (diff === 1) return 'Mañana';
            return d.toLocaleDateString('es-VE', { weekday: 'short', day: 'numeric', month: 'short' });
        },

        gradeColor(grade) {
            const map = { 1: '#2563EB', 2: '#059669', 3: '#7C3AED', 4: '#D97706', 5: '#DB2777', 6: '#0891B2' };
            const n = parseInt(String(grade || '').replace(/\D/g, ''), 10);
            return map[n] || '#64748B';
        },

        gradeChipStyle(grade, active) {
            if (grade === 'all') {
                return active
                    ? 'background:#0F172A;color:#fff;border-color:#0F172A'
                    : '';
            }
            const color = this.gradeColor(grade);
            return active
                ? `background:${color};color:#fff;border-color:${color}`
                : `color:${color};border-color:${color}55;background:${color}18`;
        },

        pendingTotal() {
            return (this.courses || []).reduce((sum, course) => sum + (course.pending_grading_count || 0), 0);
        },

        filteredCourses() {
            const query = (this.dashQuery || '').trim().toLowerCase();
            if (!query) return this.courses || [];
            return (this.courses || []).filter((course) => {
                const haystack = `${course.subject_name} ${course.grade} ${course.section || ''}`.toLowerCase();
                return haystack.includes(query);
            });
        },

        greetingSubtitle() {
            const stats = this.stats;
            if (!stats) return 'Cargando tu resumen académico…';
            if (!stats.total_courses) return 'Crea tu primer curso para empezar a ver tu resumen aquí.';
            if (stats.next_activity) {
                const next = stats.next_activity;
                const when = this.formatQueueWhen(next.due_date);
                const time = next.time_label ? ` a las ${next.time_label}` : '';
                const grade = next.grade ? ` · ${next.grade}` : '';
                return `Próxima: ${next.title}${grade}${time}${when ? ` (${when})` : ''}.`;
            }
            const courseCount = stats.total_courses;
            return `Tienes ${courseCount} curso${courseCount === 1 ? '' : 's'} activo${courseCount === 1 ? '' : 's'} y ninguna entrega pendiente por ahora.`;
        },

        aiSummaryText() {
            const stats = this.stats;
            if (!stats) return 'Analizando tu semana…';
            const parts = [];
            if (stats.activities_this_week > 0) {
                parts.push(`tienes ${stats.activities_this_week} actividad${stats.activities_this_week === 1 ? '' : 'es'} esta semana`);
            }
            if (stats.avg_grade !== null && stats.avg_grade !== undefined) {
                parts.push(`tu promedio general está en ${stats.avg_grade} (${stats.climate?.label ?? 'sin datos'})`);
            }
            if (parts.length === 0) {
                return 'Aún no tengo suficientes datos. Crea tu primera actividad para que pueda ayudarte a analizar tu semana.';
            }
            return `He revisado tu semana: ${parts.join(' y ')}.`;
        },

        insightsList() {
            const stats = this.stats;
            const courses = this.courses || [];
            const insights = [];

            if (stats?.next_activity) {
                insights.push({
                    id: 'next-activity',
                    type: 'atencion',
                    chipLabel: 'Atención',
                    course: stats.next_activity.course_name,
                    text: `"${stats.next_activity.title}" vence el ${stats.next_activity.due_date}.`,
                    actionLabel: 'Ver calendario',
                    actionType: 'calendar',
                    actionPayload: null,
                });
            }

            const pendingCourse = [...courses]
                .filter(c => (c.pending_grading_count || 0) > 0)
                .sort((a, b) => (b.pending_grading_count || 0) - (a.pending_grading_count || 0))[0];
            if (pendingCourse) {
                insights.push({
                    id: 'pending-grading-' + pendingCourse.id,
                    type: 'atencion',
                    chipLabel: 'Atención',
                    course: pendingCourse.name,
                    text: `${pendingCourse.pending_grading_count} actividad${pendingCourse.pending_grading_count === 1 ? '' : 'es'} sin calificar todavía.`,
                    actionLabel: 'Investigar',
                    actionType: 'course',
                    actionPayload: pendingCourse.id,
                });
            }

            if (stats?.grade_trend) {
                const delta = stats.grade_trend.delta;
                const improving = delta > 0.4;
                const dropping = delta < -0.4;
                insights.push({
                    id: 'grade-trend',
                    type: dropping ? 'atencion' : (improving ? 'logro' : 'tendencia'),
                    chipLabel: dropping ? 'Atención' : (improving ? 'Logro' : 'Tendencia'),
                    course: null,
                    text: dropping
                        ? `Tu promedio bajó de ${stats.grade_trend.previous_week_avg} a ${stats.grade_trend.current_week_avg} esta semana.`
                        : (improving
                            ? `Tu promedio subió de ${stats.grade_trend.previous_week_avg} a ${stats.grade_trend.current_week_avg} esta semana.`
                            : `Tu promedio se mantiene estable en ${stats.grade_trend.current_week_avg}.`),
                    actionLabel: null,
                    actionType: null,
                    actionPayload: null,
                });
            } else if (stats?.avg_grade !== null && stats?.avg_grade !== undefined) {
                const attention = ['Atención', 'Intervención'].includes(stats.climate?.label);
                insights.push({
                    id: 'grade-overview',
                    type: attention ? 'atencion' : 'logro',
                    chipLabel: attention ? 'Atención' : 'Logro',
                    course: null,
                    text: `Promedio general: ${stats.avg_grade}/20 (${stats.climate?.label ?? 'Sin datos'}).`,
                    actionLabel: null,
                    actionType: null,
                    actionPayload: null,
                });
            }

            const att = stats?.attendance;
            if (att) {
                if (att.last_alert) {
                    insights.push({
                        id: 'attendance-alert',
                        type: 'logro',
                        chipLabel: 'Asistencia',
                        course: null,
                        text: att.last_alert,
                        actionLabel: 'Ver asistencia',
                        actionType: 'href',
                        actionPayload: @json(route('teacher.attendance.index')),
                    });
                } else if (att.family_reports > 0) {
                    insights.push({
                        id: 'attendance-family',
                        type: 'atencion',
                        chipLabel: 'Familia',
                        course: null,
                        text: `${att.family_reports} reporte${att.family_reports === 1 ? '' : 's'} de ausencia pendiente${att.family_reports === 1 ? '' : 's'} de la familia.`,
                        actionLabel: 'Tomar asistencia',
                        actionType: 'href',
                        actionPayload: @json(route('teacher.attendance.index')),
                    });
                } else if (att.pending_courses > 0) {
                    insights.push({
                        id: 'attendance-pending',
                        type: 'atencion',
                        chipLabel: 'Asistencia',
                        course: null,
                        text: `Falta tomar asistencia en ${att.pending_courses} curso${att.pending_courses === 1 ? '' : 's'} hoy` + (att.absent_today ? ` · ${att.absent_today} ausente${att.absent_today === 1 ? '' : 's'} registrados.` : '.'),
                        actionLabel: 'Tomar asistencia',
                        actionType: 'href',
                        actionPayload: @json(route('teacher.attendance.index')),
                    });
                } else if (att.taken_courses > 0) {
                    insights.push({
                        id: 'attendance-today',
                        type: 'logro',
                        chipLabel: 'Asistencia',
                        course: null,
                        text: att.absent_today
                            ? `Asistencia tomada. ${att.absent_today} ausencia${att.absent_today === 1 ? '' : 's'} y ${att.tardy_today} retraso${att.tardy_today === 1 ? '' : 's'} hoy.`
                            : 'Asistencia del día tomada. El representante ya recibe alerta automática si hay una falta.',
                        actionLabel: 'Ver lista',
                        actionType: 'href',
                        actionPayload: @json(route('teacher.attendance.index')),
                    });
                } else {
                    insights.push({
                        id: 'attendance-start',
                        type: 'tendencia',
                        chipLabel: 'Asistencia',
                        course: null,
                        text: 'Toma asistencia en un toque. Si marcas una ausencia, el representante recibe el aviso al instante.',
                        actionLabel: 'Tomar asistencia',
                        actionType: 'href',
                        actionPayload: @json(route('teacher.attendance.index')),
                    });
                }
            }

            return insights;
        },

        activeInsightsCount() {
            return this.insightsList().filter(i => i.type !== 'proximamente').length;
        },

        visibleInsights() {
            return this.insightsList().filter(i => i.type !== 'proximamente').slice(0, 2);
        },

        runInsightAction(insight) {
            if (!insight?.actionType) return;
            if (insight.actionType === 'course') this.loadCourse(insight.actionPayload);
            else if (insight.actionType === 'calendar') this.loadCalendar();
            else if (insight.actionType === 'ai') this.sendAICommand(insight.actionPayload);
            else if (insight.actionType === 'href' && insight.actionPayload) window.location.href = insight.actionPayload;
        },

        scrollToInsights() {
            this.$refs.insightsSection?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        },

        sendAICommand(text) {
            this.openBubbleWithFocus();
            setTimeout(() => {
                const root = document.getElementById('ai-assistant-root')?.__x;
                if (root) {
                    root.$data.input = text;
                    root.$data.sendCommand();
                }
            }, 250);
        },

        async loadCalendar(month = null, grade = null) {
            this.view          = 'calendar';
            this.canvasLoading = true;
            this.courseData    = null;
            this.currentCourseId = null;
            this.calendarMonth = month || new Date().toISOString().slice(0, 7);
            if (grade !== null) {
                this.calendarGradeFilter = grade || 'all';
            }
            this.setNovaContext({ type: 'calendar', month: this.calendarMonth, grade: this.calendarGradeFilter });

            try {
                const params = new URLSearchParams({
                    month: this.calendarMonth,
                    grade: this.calendarGradeFilter || 'all',
                });
                const res = await fetch(`/teacher/api/calendar?${params.toString()}`, {
                    headers: { 'Accept': 'application/json' }
                });
                this.calendarData = await res.json();
                if (this.calendarData?.selected_grade) {
                    this.calendarGradeFilter = this.calendarData.selected_grade;
                }
            } catch (e) {
                console.warn('Calendar fetch failed', e);
            } finally {
                this.canvasLoading = false;
                this.closeSidebarMobile();
            }
        },

        calendarGradeOptions() {
            const options = this.calendarData?.grade_options || [];
            return ['all', ...options];
        },

        setCalendarGradeFilter(grade) {
            const target = grade || 'all';
            if (this.calendarGradeFilter === target && this.view === 'calendar') return;
            this.calendarGradeFilter = target;
            this.loadCalendar(this.calendarMonth, target);
        },

        requestDeleteActivity(id, title) {
            if (!id) return;
            this.deleteConfirm = {
                open: true,
                id,
                title: title || 'esta actividad',
                deleting: false,
            };
        },

        cancelDeleteActivity() {
            if (this.deleteConfirm.deleting) return;
            this.deleteConfirm = { open: false, id: null, title: '', deleting: false };
        },

        async confirmDeleteActivity() {
            const { id, deleting } = this.deleteConfirm;
            if (!id || deleting) return;

            this.deleteConfirm.deleting = true;

            try {
                const res = await fetch(`/teacher/activities/${id}`, {
                    method: 'DELETE',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                    },
                });
                const data = await res.json().catch(() => ({}));

                if (!res.ok || data.success === false) {
                    throw new Error(data.message || 'Error al eliminar la actividad.');
                }

                if (this.activityModal?.id === id) {
                    this.activityModal = null;
                }

                this.deleteConfirm = { open: false, id: null, title: '', deleting: false };
                this.showToast(data.message || 'Actividad eliminada correctamente.', 'success', 'fa-trash-check');

                if (this.view === 'calendar' && this.calendarMonth) {
                    await this.loadCalendar(this.calendarMonth);
                } else if (this.view === 'course' && this.currentCourseId) {
                    await this.loadCourse(this.currentCourseId);
                } else if (this.view === 'welcome') {
                    await this.loadWelcome();
                }

                await this.refreshCourseSidebar();
                this.loadNotifications();
            } catch (e) {
                console.error('confirmDeleteActivity', e);
                this.deleteConfirm.deleting = false;
                this.showToast(e.message || 'Error al eliminar la actividad.', 'error', 'fa-exclamation-triangle');
            }
        },

        initials(name = '') {
            return String(name)
                .trim()
                .split(/\s+/)
                .slice(0, 2)
                .map(part => part[0] || '')
                .join('')
                .toUpperCase();
        },

        showToast(message, type = 'success', icon = 'fa-circle-check') {
            window.dispatchEvent(new CustomEvent('ai-toast', {
                detail: { message, type, icon }
            }));
        },

        async openStudentSlideover(student) {
            if (!student?.id || !this.currentCourseId) return;

            if (this.studentSlideover.familyTimer) clearInterval(this.studentSlideover.familyTimer);

            this.studentSlideover.open = true;
            this.studentSlideover.loading = true;
            this.studentSlideover.error = null;
            this.studentSlideover.showPin = false;
            this.studentSlideover.pin = '';
            this.studentSlideover.pinError = '';
            this.studentSlideover.familyUnlocked = false;
            this.studentSlideover.familyCode = '';
            this.studentSlideover.familySeconds = 0;
            this.studentSlideover.student = {
                id: student.id,
                name: student.name,
                grade: student.grade ?? null,
                section: student.section ?? null,
                document_id: student.document_id ?? null,
                has_family_code: !!(student.family_code || student.has_family_code),
                promedio_acumulado: student.promedio_acumulado ?? student.nota_actual ?? student.avg_score ?? null,
            };
            this.studentSlideover.activities = [];
            this.studentSlideover.courseName = this.courseData?.name ?? '';

            try {
                const res = await fetch(`/teacher/api/courses/${this.currentCourseId}/students/${student.id}/grades`, {
                    headers: { 'Accept': 'application/json' }
                });
                const json = await res.json().catch(() => ({}));

                if (json.student) {
                    this.studentSlideover.student = {
                        ...this.studentSlideover.student,
                        ...json.student,
                    };
                }
                if (json.course?.name) {
                    this.studentSlideover.courseName = json.course.name;
                }
                this.studentSlideover.activities = json.activities || [];

                if (!res.ok || json.success === false) {
                    // Keep profile visible; only warn if we truly have nothing useful.
                    if (!this.studentSlideover.activities.length) {
                        this.studentSlideover.error = json.error || null;
                    }
                }
            } catch (e) {
                console.error('openStudentSlideover', e);
                this.studentSlideover.error = 'Error al cargar el panel del alumno.';
            } finally {
                this.studentSlideover.loading = false;
            }
        },

        closeStudentSlideover() {
            if (this.studentSlideover.familyTimer) clearInterval(this.studentSlideover.familyTimer);
            this.studentSlideover.open = false;
            this.studentSlideover.loading = false;
            this.studentSlideover.error = null;
            this.studentSlideover.activities = [];
            this.studentSlideover.showPin = false;
            this.studentSlideover.pin = '';
            this.studentSlideover.pinError = '';
            this.studentSlideover.familyUnlocked = false;
            this.studentSlideover.familyCode = '';
            this.studentSlideover.familySeconds = 0;
        },

        async revealStudentFamilyCode() {
            const studentId = this.studentSlideover.student?.id;
            if (!studentId || !this.studentSlideover.pin) return;

            this.studentSlideover.pinError = '';
            try {
                const res = await fetch(@json(route('codes.reveal')), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                    },
                    body: JSON.stringify({
                        pin: this.studentSlideover.pin,
                        type: 'family',
                        student_id: studentId,
                    }),
                });
                const json = await res.json();
                if (!res.ok || !json.ok) {
                    this.studentSlideover.pinError = json.error || 'PIN incorrecto.';
                    return;
                }

                this.studentSlideover.familyCode = json.code;
                this.studentSlideover.familyUnlocked = true;
                this.studentSlideover.showPin = false;
                this.studentSlideover.pin = '';
                this.studentSlideover.familySeconds = json.ttl_seconds || 20;
                if (this.studentSlideover.familyTimer) clearInterval(this.studentSlideover.familyTimer);
                this.studentSlideover.familyTimer = setInterval(() => {
                    this.studentSlideover.familySeconds -= 1;
                    if (this.studentSlideover.familySeconds <= 0) {
                        clearInterval(this.studentSlideover.familyTimer);
                        this.studentSlideover.familyTimer = null;
                        this.studentSlideover.familyUnlocked = false;
                        this.studentSlideover.familyCode = '';
                    }
                }, 1000);
            } catch (e) {
                this.studentSlideover.pinError = 'Error de conexión.';
            }
        },

        async openGradesSlideover(activity) {
            if (!activity?.id) return;

            this.gradesSlideover.open = true;
            this.gradesSlideover.loading = true;
            this.gradesSlideover.publishing = false;
            this.gradesSlideover.confirmPublish = false;
            this.gradesSlideover.error = null;
            this.gradesSlideover.activity = {
                id: activity.id,
                title: activity.title,
                max_score: activity.max_score ?? 20,
                avg_score: activity.avg_score ?? null,
                graded_count: activity.graded_count ?? 0,
                total_students: activity.total_students ?? this.courseData?.students?.length ?? 0,
                course_name: activity.course_name ?? this.courseData?.name ?? '',
            };
            this.gradesSlideover.students = [];
            this.gradesSlideover.savedPulse = {};
            this.gradesSlideover.rowState = {};

            try {
                const res = await fetch(`/teacher/grades/activity/${activity.id}/panel`, {
                    headers: { 'Accept': 'application/json' }
                });
                const json = await res.json();
                if (!res.ok || !json.success) {
                    this.gradesSlideover.error = json.error || 'No se pudo cargar la lista de notas.';
                    return;
                }

                this.gradesSlideover.activity = {
                    id: json.activity.id,
                    title: json.activity.title,
                    max_score: json.activity.max_score,
                    avg_score: activity.avg_score ?? null,
                    graded_count: activity.graded_count ?? 0,
                    total_students: json.students.length,
                    course_name: json.activity.course_name ?? '',
                };

                this.gradesSlideover.students = (json.students || []).map(student => ({
                    id: student.id,
                    name: student.name,
                    score: student.score ?? '',
                    avg_score: student.avg_score ?? null,
                    nota_actual: student.nota_actual ?? 0,
                    status: student.status ?? null,
                }));

                this.gradesSlideover.rowState = this.gradesSlideover.students.reduce((acc, student) => {
                    const hasScore = student.score !== '' && student.score !== null && student.score !== undefined;
                    if (!hasScore) {
                        acc[student.id] = 'pending';
                    } else if (student.status === 'published') {
                        acc[student.id] = 'published';
                    } else if (student.status === 'draft') {
                        acc[student.id] = 'draft';
                    } else {
                        acc[student.id] = 'draft';
                    }
                    return acc;
                }, {});

                this.$nextTick(() => this.focusGradeInput(0));
            } catch (e) {
                console.error('openGradesSlideover', e);
                this.gradesSlideover.error = 'Error al cargar el panel de notas.';
            } finally {
                this.gradesSlideover.loading = false;
            }
        },

        closeGradesSlideover() {
            this.gradesSlideover.open = false;
            this.gradesSlideover.loading = false;
            this.gradesSlideover.publishing = false;
            this.gradesSlideover.confirmPublish = false;
            this.gradesSlideover.error = null;
            this.gradesSlideover.savedPulse = {};
            this.gradesSlideover.rowState = {};

            if (this.currentCourseId) {
                this.loadCourse(this.currentCourseId);
            }
            if (this.view === 'calendar' && this.calendarMonth) {
                this.loadCalendar(this.calendarMonth);
            }
            this.refreshCourseSidebar();
        },

        async openClassAttendance(activity) {
            if (!activity?.course_id) {
                this.classAttendance = { ...this.classAttendance, open: true, error: 'Esta clase no tiene un curso asignado.', roster: [] };
                return;
            }
            this.classAttendance = {
                open: true,
                loading: true,
                saving: false,
                activity_id: activity.id,
                course_id: activity.course_id,
                course_name: activity.course_name || this.courseData?.name || '',
                date: activity.due_date || new Date().toISOString().slice(0, 10),
                taken: false,
                roster: [],
                error: '',
                message: '',
            };
            try {
                const params = new URLSearchParams({ course_id: this.classAttendance.course_id, date: this.classAttendance.date });
                const res = await fetch(`{{ route('teacher.attendance.roster') }}?${params.toString()}`, {
                    headers: { Accept: 'application/json' },
                });
                const json = await res.json();
                if (!res.ok || !json.success) {
                    this.classAttendance.error = json.error || 'No se pudo cargar la asistencia.';
                    return;
                }
                this.classAttendance.taken = !!json.taken;
                this.classAttendance.roster = (json.roster || []).map(r => ({ ...r }));
            } catch (e) {
                this.classAttendance.error = 'Error de red al cargar la asistencia.';
            } finally {
                this.classAttendance.loading = false;
            }
        },

        closeClassAttendance() {
            this.classAttendance.open = false;
        },

        setClassAttendanceStatus(row, status) {
            row.status = status;
            if (status !== 'absent') row.reason_id = null;
        },

        async saveClassAttendance() {
            if (!this.classAttendance.course_id || this.classAttendance.saving) return;
            this.classAttendance.saving = true;
            this.classAttendance.error = '';
            this.classAttendance.message = '';
            try {
                const res = await fetch('{{ route('teacher.attendance.save') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        course_id: this.classAttendance.course_id,
                        date: this.classAttendance.date,
                        entries: this.classAttendance.roster.map(r => ({
                            student_id: r.student_id,
                            status: r.status || 'present',
                            reason_id: r.reason_id || null,
                            note: r.note || null,
                            client_uuid: r.client_uuid || null,
                        })),
                    }),
                });
                const json = await res.json();
                if (!res.ok || !json.success) {
                    this.classAttendance.error = json.error || 'No se pudo guardar la asistencia.';
                    return;
                }
                this.classAttendance.taken = true;
                this.classAttendance.message = 'Asistencia guardada correctamente.';
                if (this.classAttendance.activity_id) {
                    this.refreshCourseSidebar();
                }
            } catch (e) {
                this.classAttendance.error = 'Error de red al guardar la asistencia.';
            } finally {
                this.classAttendance.saving = false;
            }
        },

        async saveActivityNotes() {
            if (!this.activityModal?.id || this.notesSaving) return;
            this.notesSaving = true;
            try {
                const res = await fetch(`/teacher/activities/${this.activityModal.id}/notes`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ notes: this.activityModal.notes || '' }),
                });
                const json = await res.json();
                if (json.success && this.courseData?.activities) {
                    const idx = this.courseData.activities.findIndex(a => Number(a.id) === Number(this.activityModal.id));
                    if (idx !== -1) this.courseData.activities[idx].notes = json.notes;
                }
            } catch (e) {
                console.error('saveActivityNotes', e);
            } finally {
                this.notesSaving = false;
            }
        },

        updateActivityGradeMeta(activityId, payload = {}) {
            if (!this.courseData?.activities) return;
            const idx = this.courseData.activities.findIndex(a => Number(a.id) === Number(activityId));
            if (idx === -1) return;

            if (payload.activity_avg_score !== undefined) {
                this.courseData.activities[idx].avg_score = payload.activity_avg_score;
            }
            if (payload.graded_count !== undefined) {
                this.courseData.activities[idx].graded_count = payload.graded_count;
            }
            if (payload.total_students !== undefined) {
                this.courseData.activities[idx].total_students = payload.total_students;
            }

            if (this.activityModal && Number(this.activityModal.id) === Number(activityId)) {
                this.activityModal = { ...this.activityModal, ...this.courseData.activities[idx] };
            }
        },

        updateStudentAverage(studentId, avgScore) {
            if (!this.courseData?.students) return;
            const idx = this.courseData.students.findIndex(s => Number(s.id) === Number(studentId));
            if (idx === -1) return;
            this.courseData.students[idx].avg_score = avgScore;
        },

        updateStudentAccumulated(studentId, notaActual) {
            if (!this.courseData?.students) return;
            const idx = this.courseData.students.findIndex(s => Number(s.id) === Number(studentId));
            if (idx === -1) return;
            this.courseData.students[idx].nota_actual = notaActual;
            this.courseData.students[idx].promedio_acumulado = notaActual;
        },

        setStudentRowState(studentId, state) {
            this.gradesSlideover.rowState = {
                ...this.gradesSlideover.rowState,
                [studentId]: state,
            };
        },

        gradeStatusLabel(student) {
            const state = this.gradesSlideover.rowState?.[student.id] ?? 'pending';
            if (state === 'saving') return 'Guardando...';
            if (state === 'draft') return 'Borrador';
            if (state === 'saved') return 'Guardado';
            if (state === 'published') return 'Publicado';
            if (state === 'error') return 'Error al guardar';
            return 'Pendiente';
        },

        gradeStatusClass(student) {
            const state = this.gradesSlideover.rowState?.[student.id] ?? 'pending';
            if (state === 'saving') return 'border-blue-400/35 bg-blue-500/10 text-blue-200';
            if (state === 'draft') return 'border-amber-400/45 bg-amber-500/10 text-amber-300';
            if (state === 'saved') return 'border-cyan-400/45 bg-cyan-500/10 text-cyan-200';
            if (state === 'published') return 'border-emerald-400/45 bg-emerald-500/10 text-emerald-200';
            if (state === 'error') return 'border-red-400/45 bg-red-500/10 text-red-200';
            return 'border-amber-400/35 bg-amber-500/10 text-amber-200';
        },

        focusGradeInput(index) {
            const el = document.querySelector(`.grades-slideover-panel input[data-grade-index="${index}"]`);
            if (!el) return;
            el.focus();
            el.select?.();
        },

        async handleGradeInputKeydown(event, student, index) {
            if (event.key === 'ArrowDown') {
                event.preventDefault();
                this.focusGradeInput(index + 1);
                return;
            }
            if (event.key === 'ArrowUp') {
                event.preventDefault();
                this.focusGradeInput(index - 1);
                return;
            }
            if (event.key === 'Enter') {
                event.preventDefault();
                await this.persistGrade(student);
                this.focusGradeInput(index + 1);
            }
        },

        async persistGrade(student) {
            const activityId = this.gradesSlideover.activity?.id;
            if (!activityId) return;

            const raw = student?.score;
            if (raw === '' || raw === null || raw === undefined) {
                this.setStudentRowState(student.id, 'pending');
                return;
            }
            const score = Number(raw);
            if (Number.isNaN(score)) {
                this.setStudentRowState(student.id, 'error');
                return;
            }

            this.setStudentRowState(student.id, 'saving');

            try {
                const res = await fetch(`/teacher/grades/activity/${activityId}/quick-store`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    },
                    body: JSON.stringify({
                        student_id: student.id,
                        score,
                    }),
                });
                const json = await res.json();
                if (!res.ok || !json.success) {
                    this.setStudentRowState(student.id, 'error');
                    this.showToast(json.error || 'No se pudo guardar la nota.', 'error', 'fa-exclamation-triangle');
                    return;
                }

                student.score = json.score;
                student.status = json.status ?? 'draft';
                student.avg_score = json.student_avg_score;
                student.nota_actual = json.nota_actual ?? student.nota_actual ?? 0;
                this.updateStudentAverage(json.student_id, json.student_avg_score);
                this.updateStudentAccumulated(json.student_id, json.nota_actual ?? 0);
                this.updateActivityGradeMeta(json.activity_id, json);
                this.setStudentRowState(student.id, student.status === 'published' ? 'published' : 'draft');

                this.gradesSlideover.savedPulse = { ...this.gradesSlideover.savedPulse, [student.id]: true };
                setTimeout(() => {
                    this.gradesSlideover.savedPulse = { ...this.gradesSlideover.savedPulse, [student.id]: false };
                }, 1000);
            } catch (e) {
                console.error('persistGrade', e);
                this.setStudentRowState(student.id, 'error');
                this.showToast('Error al guardar la nota.', 'error', 'fa-exclamation-triangle');
            }
        },

        async publishGrades() {
            const activityId = this.gradesSlideover.activity?.id;
            if (!activityId || this.gradesSlideover.publishing) return;

            this.gradesSlideover.publishing = true;
            try {
                const res = await fetch(`/teacher/grades/activity/${activityId}/publish`, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    },
                });
                const json = await res.json();
                if (!res.ok || !json.success) {
                    this.showToast(json.error || 'No se pudieron publicar las notas.', 'error', 'fa-exclamation-triangle');
                    return;
                }

                this.gradesSlideover.students = this.gradesSlideover.students.map(student => ({
                    ...student,
                    status: student.score === '' || student.score === null || student.score === undefined
                        ? student.status
                        : 'published',
                }));
                this.gradesSlideover.rowState = this.gradesSlideover.students.reduce((acc, student) => {
                    const hasScore = student.score !== '' && student.score !== null && student.score !== undefined;
                    acc[student.id] = hasScore ? 'published' : 'pending';
                    return acc;
                }, {});
                this.gradesSlideover.confirmPublish = false;
                this.showToast('Notas publicadas correctamente.', 'success', 'fa-cloud-arrow-up');
            } catch (e) {
                console.error('publishGrades', e);
                this.showToast('Error al publicar notas.', 'error', 'fa-exclamation-triangle');
            } finally {
                this.gradesSlideover.publishing = false;
            }
        },

        async openTaskIdeaModal() {
            if (!this.activityModal?.id) return;
            this.taskIdeaModalOpen = true;
            this.taskAccepted = false;
            this.taskForm.fecha_entrega = this.activityModal?.due_date || '';
            this.taskForm.puntos = 20;
            await this.generateTaskIdea();
        },

        openNeeModal() {
            if (!this.activityModal?.id) return;
            this.neeModalOpen = true;
            this.neeAccepted = false;
            this.neeForm.tipo = this.activityModal?.nee_type || '';
            this.neeForm.texto = this.activityModal?.nee_adaptation || '';
        },

        async generateTaskIdea() {
            if (!this.activityModal?.id) return;

            this.taskLoading = true;
            this.taskAccepted = false;

            try {
                const res = await fetch('{{ route('teacher.tareas.generate') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    },
                    body: JSON.stringify({ activity_id: this.activityModal.id }),
                });
                const json = await res.json();

                if (!res.ok || !json.success) {
                    alert(json.error || 'No se pudo generar sugerencia.');
                    return;
                }

                this.taskForm.titulo = json.idea?.titulo || 'Tarea sugerida';
                this.taskForm.descripcion = json.idea?.descripcion || '';
            } catch (e) {
                console.error('generateTaskIdea', e);
                alert('Error al generar sugerencia de tarea.');
            } finally {
                this.taskLoading = false;
            }
        },

        acceptTaskIdea() {
            this.taskAccepted = true;
            if (!this.taskForm.fecha_entrega) {
                this.taskForm.fecha_entrega = this.activityModal?.due_date || '';
            }
            if (!this.taskForm.puntos) {
                this.taskForm.puntos = 20;
            }
        },

        async saveTask() {
            if (!this.activityModal?.id || !this.taskAccepted) return;

            this.taskSaving = true;
            try {
                const payload = {
                    activity_id: this.activityModal.id,
                    titulo: this.taskForm.titulo,
                    descripcion: this.taskForm.descripcion,
                    fecha_entrega: this.taskForm.fecha_entrega,
                    puntos: this.taskForm.puntos,
                    mirror_activity: true,
                };

                const res = await fetch('{{ route('teacher.tareas.store') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    },
                    body: JSON.stringify(payload),
                });

                const json = await res.json();
                if (!res.ok || !json.success) {
                    alert(json.error || 'No se pudo guardar la tarea.');
                    return;
                }

                if (!Array.isArray(this.activityModal.tareas)) {
                    this.activityModal.tareas = [];
                }
                this.activityModal.tareas.unshift(json.tarea);
                this.taskIdeaModalOpen = false;
                window.dispatchEvent(new CustomEvent('ai-canvas-refresh'));
            } catch (e) {
                console.error('saveTask', e);
                alert('Error al guardar la tarea.');
            } finally {
                this.taskSaving = false;
            }
        },

        async generateNeeAdaptation() {
            if (!this.activityModal?.id || !this.neeForm.tipo) return;

            this.neeLoading = true;
            this.neeAccepted = false;
            try {
                const res = await fetch(`/teacher/activities/${this.activityModal.id}/nee/generate`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    },
                    body: JSON.stringify({ nee_type: this.neeForm.tipo }),
                });
                const json = await res.json();
                if (!res.ok || !json.success) {
                    alert(json.error || 'No se pudo generar la adaptación.');
                    return;
                }
                this.neeForm.texto = json.adaptation || '';
            } catch (e) {
                console.error('generateNeeAdaptation', e);
                alert('Error al generar adaptación NEE.');
            } finally {
                this.neeLoading = false;
            }
        },

        acceptNeeAdaptation() {
            this.neeAccepted = true;
        },

        async saveNeeAdaptation() {
            if (!this.activityModal?.id || !this.neeAccepted || !this.neeForm.tipo || !this.neeForm.texto) return;

            this.neeSaving = true;
            try {
                const res = await fetch(`/teacher/activities/${this.activityModal.id}/nee/save`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    },
                    body: JSON.stringify({
                        nee_type: this.neeForm.tipo,
                        nee_adaptation: this.neeForm.texto,
                    }),
                });
                const json = await res.json();
                if (!res.ok || !json.success) {
                    alert(json.error || 'No se pudo guardar la adaptación.');
                    return;
                }
                this.activityModal.nee_type = json.nee_type;
                this.activityModal.nee_adaptation = json.nee_adaptation;
                this.neeModalOpen = false;
                window.dispatchEvent(new CustomEvent('ai-canvas-refresh'));
            } catch (e) {
                console.error('saveNeeAdaptation', e);
                alert('Error al guardar adaptación NEE.');
            } finally {
                this.neeSaving = false;
            }
        },

        openDayModal(day) {
            if (!day) return;
            const acts = this.activitiesForDay(day);
            this.dayModal = {
                day,
                dateLabel: this.calendarDayLabel(day),
                activities: acts,
                agendaRows: this.dayAgendaRows(day),
            };
        },

        calendarDayLabel(day) {
            if (!day || !this.calendarData?.month) return `Día ${day}`;
            const iso = `${this.calendarData.month}-${String(day).padStart(2, '0')}`;
            try {
                return new Date(`${iso}T12:00:00`).toLocaleDateString('es-VE', {
                    weekday: 'long', day: 'numeric', month: 'long'
                });
            } catch (e) {
                return iso;
            }
        },

        calNavigate(direction) {
            if (direction === 0) {
                this.loadCalendar(new Date().toISOString().slice(0, 7));
                return;
            }
            if (!this.calendarMonth) return;
            const [y, m] = this.calendarMonth.split('-').map(Number);
            let nm = m + direction, ny = y;
            if (nm > 12) { nm = 1;  ny++; }
            if (nm < 1)  { nm = 12; ny--; }
            this.loadCalendar(`${ny}-${String(nm).padStart(2,'0')}`);
        },

        get calendarDays() {
            if (!this.calendarData) return [];
            const { days_in_month, first_weekday } = this.calendarData;
            const offset = first_weekday === 0 ? 6 : first_weekday - 1;
            const cells = [];
            for (let i = 0; i < offset; i++) cells.push(null);
            for (let d = 1; d <= days_in_month; d++) cells.push(d);
            return cells;
        },

        activitiesForDay(day) {
            if (!day || !this.calendarData) return [];
            const key = this.calendarData.month + '-' + String(day).padStart(2, '0');
            const list = this.calendarData.activities_by_day?.[key] ?? [];
            return [...list].sort((a, b) => {
                const ai = Number(a.slot_index ?? 0);
                const bi = Number(b.slot_index ?? 0);
                return ai - bi;
            });
        },

        dayGradeBuckets(day) {
            const counts = {};
            for (const act of this.activitiesForDay(day)) {
                const grade = String(act.grade || 'Sin grado');
                counts[grade] = (counts[grade] || 0) + 1;
            }
            return Object.entries(counts)
                .map(([grade, count]) => ({ grade, count }))
                .sort((a, b) => a.grade.localeCompare(b.grade, 'es'));
        },

        dayAgendaRows(day) {
            const acts = this.activitiesForDay(day);
            const buckets = {};
            for (const act of acts) {
                const key = String(act.time_label || 'Sin hora');
                if (!buckets[key]) buckets[key] = [];
                buckets[key].push(act);
            }

            const rows = [];
            for (let hour = 7; hour <= 19; hour++) {
                const key = `${String(hour).padStart(2, '0')}:00`;
                rows.push({
                    hour: key,
                    activities: buckets[key] || [],
                });
            }

            const extra = Object.entries(buckets)
                .filter(([hour]) => !rows.some(row => row.hour === hour))
                .map(([hour, list]) => ({ hour, activities: list }))
                .sort((a, b) => a.hour.localeCompare(b.hour));

            return [...rows, ...extra];
        },

        setNovaContext(ctx = null) {
            window.novaContext = ctx;
            window.AI_PAGE_CONTEXT = ctx;
            window.dispatchEvent(new CustomEvent('ai-context-changed', { detail: ctx }));
        },

        setActivityContext(activity) {
            const fallbackCourse = this.courseData ?? null;
            const context = {
                type: 'activity',
                id: activity?.id ?? null,
                title: activity?.title ?? '',
                description: activity?.description ?? '',
                due_date: activity?.due_date ?? null,
                max_score: activity?.max_score ?? null,
                activity_type: activity?.type ?? 'actividad',
                course_id: activity?.course_id ?? fallbackCourse?.id ?? null,
                course_name: activity?.course_name ?? fallbackCourse?.name ?? '',
                grade: activity?.grade ?? fallbackCourse?.grade ?? '',
                section: activity?.section ?? fallbackCourse?.section ?? '',
            };
            this.setNovaContext(context);
        },

        findActivityByIdLocal(activityId) {
            const id = Number(activityId);
            if (!id) return null;

            if (this.courseData?.activities?.length) {
                const foundInCourse = this.courseData.activities.find(a => Number(a.id) === id);
                if (foundInCourse) return foundInCourse;
            }

            if (this.calendarData?.activities_by_day) {
                for (const dayKey in this.calendarData.activities_by_day) {
                    const list = this.calendarData.activities_by_day?.[dayKey] ?? [];
                    const foundInCalendar = list.find(a => Number(a.id) === id);
                    if (foundInCalendar) return foundInCalendar;
                }
            }

            return null;
        },

        parsePhasesFromDescription(description) {
            const text = String(description || '');
            const defs = this.lessonPhaseDefsFor(this.detectLessonTemplate(text));
            const names = defs.map(d => d.header);
            const out = {};
            defs.forEach(d => { out[d.key] = ''; });

            for (let i = 0; i < names.length; i++) {
                const header = '**' + names[i] + '**';
                const start = text.indexOf(header);
                if (start === -1) continue;
                const contentStart = start + header.length;
                let contentEnd = text.length;
                for (let j = i + 1; j < names.length; j++) {
                    const next = text.indexOf('**' + names[j] + '**', contentStart);
                    if (next !== -1 && next < contentEnd) contentEnd = next;
                }
                out[defs[i].key] = text.slice(contentStart, contentEnd).trim();
            }

            if (!Object.values(out).some(v => v) && text.trim()) {
                const fallback = defs[Math.min(1, defs.length - 1)];
                out[fallback.key] = text.trim();
            }
            return out;
        },

        detectLessonTemplate(description) {
            const text = String(description || '');
            const templates = {
                clasica: ['INICIO', 'DESARROLLO', 'CIERRE'],
                directa: ['MOTIVACIÓN', 'PRESENTACIÓN', 'PRÁCTICA GUIADA', 'CIERRE REFLEXIVO'],
                constructivista: ['ACTIVACIÓN', 'EXPLORACIÓN', 'EXPLICACIÓN', 'APLICACIÓN', 'EVALUACIÓN'],
                proyecto: ['DESAFÍO', 'INVESTIGACIÓN', 'CREACIÓN', 'PRESENTACIÓN', 'REFLEXIÓN'],
            };
            let best = window.novaLessonTemplate || 'clasica';
            let bestCount = 0;
            for (const [id, names] of Object.entries(templates)) {
                const count = names.filter(n => text.includes('**' + n + '**')).length;
                if (count > bestCount) {
                    bestCount = count;
                    best = id;
                }
            }
            return best;
        },

        lessonPhaseDefs() {
            return this.lessonPhaseDefsFor(this.phaseEdit?.template || window.novaLessonTemplate || 'clasica');
        },

        lessonPhaseDefsFor(id) {
            const defs = {
                clasica: [
                    { key: 'inicio', header: 'INICIO', label: 'Inicio', color: '#7C3AED', icon: 'fa-solid fa-play', placeholder: 'Motivación, activación de saberes previos…' },
                    { key: 'desarrollo', header: 'DESARROLLO', label: 'Desarrollo', color: '#06B6D4', icon: 'fa-solid fa-layer-group', placeholder: 'Actividades principales, práctica guiada…' },
                    { key: 'cierre', header: 'CIERRE', label: 'Cierre', color: '#22C55E', icon: 'fa-solid fa-flag-checkered', placeholder: 'Síntesis, evaluación formativa, tarea…' },
                ],
                directa: [
                    { key: 'motivacion', header: 'MOTIVACIÓN', label: 'Motivación', color: '#F59E0B', icon: 'fa-solid fa-bolt', placeholder: 'Enlace con la experiencia previa y propósito…' },
                    { key: 'presentacion', header: 'PRESENTACIÓN', label: 'Presentación', color: '#7C3AED', icon: 'fa-solid fa-chalkboard-user', placeholder: 'El docente modela el contenido paso a paso…' },
                    { key: 'practica', header: 'PRÁCTICA GUIADA', label: 'Práctica guiada', color: '#06B6D4', icon: 'fa-solid fa-people-group', placeholder: 'El alumno practica con apoyo y corrección…' },
                    { key: 'cierre_reflexivo', header: 'CIERRE REFLEXIVO', label: 'Cierre reflexivo', color: '#22C55E', icon: 'fa-solid fa-flag-checkered', placeholder: 'Reflexión, aplicación autónoma y autoevaluación…' },
                ],
                constructivista: [
                    { key: 'activacion', header: 'ACTIVACIÓN', label: 'Activación', color: '#EF4444', icon: 'fa-solid fa-lightbulb', placeholder: 'Pregunta provocadora o situación problemática…' },
                    { key: 'exploracion', header: 'EXPLORACIÓN', label: 'Exploración', color: '#F59E0B', icon: 'fa-solid fa-magnifying-glass', placeholder: 'Los alumnos exploran el fenómeno o concepto…' },
                    { key: 'explicacion', header: 'EXPLICACIÓN', label: 'Explicación', color: '#7C3AED', icon: 'fa-solid fa-book-open', placeholder: 'Se formaliza el concepto con lenguaje disciplinar…' },
                    { key: 'aplicacion', header: 'APLICACIÓN', label: 'Aplicación', color: '#06B6D4', icon: 'fa-solid fa-puzzle-piece', placeholder: 'Transferencia a situaciones nuevas…' },
                    { key: 'evaluacion', header: 'EVALUACIÓN', label: 'Evaluación', color: '#22C55E', icon: 'fa-solid fa-clipboard-check', placeholder: 'Verificación del aprendizaje logrado…' },
                ],
                proyecto: [
                    { key: 'desafio', header: 'DESAFÍO', label: 'Desafío', color: '#F59E0B', icon: 'fa-solid fa-bullseye', placeholder: 'Pregunta esencial o reto auténtico…' },
                    { key: 'investigacion', header: 'INVESTIGACIÓN', label: 'Investigación', color: '#7C3AED', icon: 'fa-solid fa-magnifying-glass', placeholder: 'Búsqueda de información y trabajo colaborativo…' },
                    { key: 'creacion', header: 'CREACIÓN', label: 'Creación', color: '#06B6D4', icon: 'fa-solid fa-hammer', placeholder: 'Diseño y elaboración del producto…' },
                    { key: 'presentacion', header: 'PRESENTACIÓN', label: 'Presentación', color: '#EC4899', icon: 'fa-solid fa-users', placeholder: 'Comunicación de resultados…' },
                    { key: 'reflexion', header: 'REFLEXIÓN', label: 'Reflexión', color: '#22C55E', icon: 'fa-solid fa-seedling', placeholder: 'Metacognición y aprendizajes transferibles…' },
                ],
            };
            return defs[id] || defs.clasica;
        },

        lessonTemplateLabel() {
            const labels = { clasica: 'Clásica', directa: 'Instrucción Directa', constructivista: 'Modelo 5E', proyecto: 'Basado en Proyectos' };
            return labels[this.phaseEdit?.template] || labels.clasica;
        },

        buildDescriptionFromPhases() {
            const parts = [];
            for (const phase of this.lessonPhaseDefs()) {
                const value = String(this.phaseEdit.values?.[phase.key] || '').trim();
                if (value) {
                    parts.push('**' + phase.header + '**\n' + value);
                }
            }
            return parts.join('\n\n');
        },

        evaluationBriefCards() {
            const act = this.activityModal;
            if (!act) return [];
            const topic = String(act.evaluation_topic || act.title || 'esta evaluación').replace(/^Examen:\s*/i, '');
            const parsed = this.parseEvalMarkdown(act.description || '');
            const raw = String(act.description || '').trim();
            const amateur = this.isAmateurEvalText(raw);

            let purpose = parsed.purpose;
            let instructions = parsed.instructions;
            if (!purpose) {
                purpose = amateur || !raw
                    ? `Comprobar el dominio de **${topic}** en ${act.course_name || 'el curso'}, con evidencia clara y criterios de logro.`
                    : raw;
            }
            if (!instructions) {
                instructions = amateur || parsed.purpose
                    ? (raw && amateur ? raw : `Responde cada ítem demostrando comprensión de **${topic}**. Justifica cuando se pida explicación y revisa antes de entregar.`)
                    : '';
            }
            if (!instructions) {
                instructions = 'Demuestra lo aprendido con respuestas precisas. En las preguntas abiertas explica el procedimiento y usa el vocabulario de la unidad.';
            }

            const instrument = parsed.instrument || [
                act.evaluation_question_count ? `- **${act.evaluation_question_count} preguntas** alineadas al tema.` : null,
                act.max_score ? `- Puntaje máximo: **${act.max_score}**.` : null,
                act.weight_percentage ? `- Peso en el plan: **${act.weight_percentage}%**.` : null,
                act.due_date ? `- Fecha: **${act.due_date}**.` : null,
                `- Formato: **${act.evaluation_mode === 'physical' ? 'impresa / física' : 'digital en AulaSync'}**.`,
            ].filter(Boolean).join('\n');

            const md = (text) => typeof window.renderMarkdown === 'function'
                ? window.renderMarkdown(text)
                : String(text || '').replace(/</g, '&lt;');

            return [
                { key: 'purpose', label: 'Propósito', icon: 'fa-solid fa-bullseye', color: '#F59E0B', html: md(purpose) },
                { key: 'guide', label: 'Indicaciones', icon: 'fa-solid fa-list-check', color: '#8B5CF6', html: md(instructions) },
                { key: 'instrument', label: 'Instrumento', icon: 'fa-solid fa-clipboard-list', color: '#06B6D4', html: md(instrument) },
            ];
        },

        parseEvalMarkdown(text) {
            const src = String(text || '').trim();
            const out = { purpose: '', instructions: '', instrument: '' };
            if (!src) return out;
            const parts = src.split(/\n(?=\*\*(Propósito|Indicaciones|Instrumento)\*\*)/i);
            if (parts.length < 2 && !/\*\*(Propósito|Indicaciones|Instrumento)\*\*/i.test(src)) {
                return out;
            }
            for (const part of parts) {
                const match = part.match(/^\*\*(Propósito|Indicaciones|Instrumento)\*\*\s*([\s\S]*)$/i);
                if (!match) continue;
                const key = match[1].toLowerCase();
                const body = match[2].trim();
                if (key === 'propósito' || key === 'proposito') out.purpose = body;
                if (key === 'indicaciones') out.instructions = body;
                if (key === 'instrumento') out.instrument = body;
            }
            return out;
        },

        isAmateurEvalText(text) {
            const plain = String(text || '').replace(/\s+/g, ' ').trim().toLowerCase();
            if (!plain) return true;
            return plain.length < 220 && /responde las siguientes|lee cada pregunta|cuidadosamente|selecciona la respuesta correcta|según corresponda/.test(plain);
        },

        openActivityModal(activity) {
            this.activityModal = activity;
            const template = this.detectLessonTemplate(activity?.description);
            const values = this.parsePhasesFromDescription(activity?.description);
            this.phaseEdit = {
                template,
                values,
                draft: {},
                editing: null,
                saving: null,
            };
        },

        renderPhaseMarkdown(text) {
            if (typeof window.renderPhaseMarkdown === 'function') {
                return window.renderPhaseMarkdown(text);
            }
            const trimmed = String(text ?? '').trim();
            return trimmed ? `<p>${trimmed.replace(/</g, '&lt;')}</p>` : '<p class="phase-empty">Sin contenido. Haz clic en el lápiz para añadir.</p>';
        },

        startPhaseEdit(key) {
            if (this.phaseEdit.editing && this.phaseEdit.editing !== key) {
                this.cancelPhaseEdit(this.phaseEdit.editing);
            }
            this.phaseEdit.draft[key] = this.phaseEdit.values?.[key] ?? '';
            this.phaseEdit.editing = key;
        },

        cancelPhaseEdit(key) {
            this.phaseEdit.draft[key] = this.phaseEdit.values?.[key] ?? '';
            if (this.phaseEdit.editing === key) {
                this.phaseEdit.editing = null;
            }
        },

        async savePhaseSection(key) {
            if (!this.activityModal?.id || this.phaseEdit.saving) return;
            this.phaseEdit.values[key] = this.phaseEdit.draft[key] ?? '';
            this.phaseEdit.saving = key;
            try {
                await this.persistActivityPhases();
                this.phaseEdit.editing = null;
                const labels = Object.fromEntries(this.lessonPhaseDefs().map(p => [p.key, p.label]));
                window.dispatchEvent(new CustomEvent('ai-toast', {
                    detail: { message: `${labels[key] || 'Fase'} guardada correctamente`, type: 'success', icon: 'fa-check' },
                }));
            } catch (e) {
                window.dispatchEvent(new CustomEvent('ai-toast', {
                    detail: { message: e.message || 'Error al guardar', type: 'error' },
                }));
            } finally {
                this.phaseEdit.saving = null;
            }
        },

        async persistActivityPhases() {
            if (!this.activityModal?.id) return;
            const description = this.buildDescriptionFromPhases();
            const res = await fetch(`/teacher/activities/${this.activityModal.id}/phases`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                },
                body: JSON.stringify({
                    phases: this.phaseEdit.values,
                    template: this.phaseEdit.template,
                    description,
                }),
            });
            const json = await res.json();
            if (!res.ok || !json.success) {
                throw new Error(json.message || 'No se pudieron guardar los cambios');
            }
            this.activityModal.description = json.activity?.description ?? description;

            const id = Number(this.activityModal.id);
            if (this.courseData?.activities) {
                const idx = this.courseData.activities.findIndex(a => Number(a.id) === id);
                if (idx >= 0) {
                    this.courseData.activities[idx] = {
                        ...this.courseData.activities[idx],
                        description: this.activityModal.description,
                    };
                }
            }
            if (this.calendarData?.activities_by_day) {
                for (const dayKey in this.calendarData.activities_by_day) {
                    const list = this.calendarData.activities_by_day[dayKey] || [];
                    const idx = list.findIndex(a => Number(a.id) === id);
                    if (idx >= 0) {
                        list[idx] = { ...list[idx], description: this.activityModal.description };
                    }
                }
            }
        },

        async saveActivityPhases() {
            if (!this.activityModal?.id || this.phaseEdit.saving) return;
            this.phaseEdit.saving = 'all';
            try {
                await this.persistActivityPhases();
                window.dispatchEvent(new CustomEvent('ai-toast', {
                    detail: { message: 'Fases guardadas correctamente', type: 'success', icon: 'fa-check' },
                }));
            } catch (e) {
                window.dispatchEvent(new CustomEvent('ai-toast', {
                    detail: { message: e.message || 'Error al guardar', type: 'error' },
                }));
            } finally {
                this.phaseEdit.saving = null;
            }
        },

        async openActivityModalFromExternal(payload = {}) {
            const activityId = Number(payload?.id ?? 0);
            if (!activityId) return;

            let activity = this.findActivityByIdLocal(activityId);
            const targetCourseId = Number(payload?.course_id ?? activity?.course_id ?? 0);
            const targetDate = payload?.due_date ?? activity?.due_date ?? null;

            if (!activity && targetCourseId > 0) {
                await this.loadCourse(targetCourseId);
                activity = this.findActivityByIdLocal(activityId);
            }

            if (!activity && targetDate) {
                const month = String(targetDate).slice(0, 7);
                if (month) {
                    await this.loadCalendar(month);
                    activity = this.findActivityByIdLocal(activityId);
                }
            }

            // Fallback: cargar actividad desde API (p. ej. clic en notificación)
            if (!activity) {
                try {
                    const res = await fetch(`/teacher/api/activities/${activityId}`, {
                        headers: { Accept: 'application/json' },
                    });
                    const json = await res.json();
                    if (res.ok && json.success && json.activity) {
                        activity = json.activity;
                        if (activity.due_date) {
                            const month = String(activity.due_date).slice(0, 7);
                            if (month) await this.loadCalendar(month);
                        }
                    }
                } catch (e) {
                    console.warn('fetch activity failed', e);
                }
            }

            if (!activity) {
                window.dispatchEvent(new CustomEvent('ai-toast', {
                    detail: { message: 'No se encontró la actividad en la vista actual.', type: 'error' }
                }));
                return;
            }

            if (this.view !== 'calendar' && (targetDate || activity.due_date)) {
                const month = String(targetDate || activity.due_date).slice(0, 7);
                if (month) {
                    await this.loadCalendar(month);
                    activity = this.findActivityByIdLocal(activityId) ?? activity;
                }
            }

            this.setActivityContext(activity);
            this.openActivityModal(activity);

            this.$nextTick(() => {
                const calendarGrid = document.querySelector('.calendar-grid');
                if (calendarGrid) {
                    calendarGrid.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        },

        isToday(day) {
            if (!day || !this.calendarData) return false;
            const today = new Date();
            const todayStr = `${today.getFullYear()}-${String(today.getMonth()+1).padStart(2,'0')}-${String(today.getDate()).padStart(2,'0')}`;
            const cellStr  = this.calendarData.month + '-' + String(day).padStart(2,'0');
            return todayStr === cellStr;
        },

        async refreshCourseSidebar() {
            this.coursesLoading = true;
            try {
                const res    = await fetch('{{ route('teacher.api.courses') }}', {
                    headers: { 'Accept': 'application/json' }
                });
                this.courses = await res.json();
            } catch (e) {
                console.warn('Courses sidebar fetch failed', e);
            } finally {
                this.coursesLoading = false;
            }
        },
    };
}
</script>
<div id="ai-modal" class="fixed inset-0 z-[130] flex items-center justify-center hidden bg-black/60 backdrop-blur-sm animate__animated animate__fadeIn">
    <div class="bg-[#12122B] border border-[#6C4AE0]/30 w-full max-w-md rounded-3xl p-6 shadow-2xl relative overflow-hidden">
        <div class="absolute -top-24 -right-24 w-48 h-48 bg-[#6C4AE0]/20 blur-[60px] rounded-full"></div>
        
        <div class="relative z-10">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 bg-gradient-to-br from-[#6C4AE0] to-[#3BC9DB] rounded-xl flex items-center justify-center shadow-lg shadow-violet-900/20">
                    <i class="fas fa-robot text-white"></i>
                </div>
                <h3 class="text-white font-bold text-lg">Asistente AulaSync IA</h3>
            </div>

            <p class="text-gray-400 text-sm mb-4">¿Cómo quieres mejorar esta clase? Solo escribe y yo me encargo del resto.</p>
            
            <div class="relative">
                <textarea id="ai-prompt-input" rows="3" 
                    class="w-full bg-[#0A0A1F] border border-[#2D1F4A] rounded-2xl p-4 text-white text-sm focus:outline-none focus:border-[#6C4AE0] transition-all placeholder:text-gray-600 resize-none"
                    placeholder="Ej: Hazla más dinámica para niños de 6 años..."></textarea>
            </div>

            <div class="flex gap-3 mt-4">
                <button onclick="cerrarAI()" class="flex-1 px-4 py-3 rounded-xl bg-white/5 text-gray-400 text-sm font-semibold hover:bg-white/10 transition-all">Cancelar</button>
                <button onclick="procesarCambioIA()" class="flex-[2] px-4 py-3 rounded-xl bg-gradient-to-r from-[#6C4AE0] to-[#C455ED] text-white text-sm font-bold shadow-lg shadow-violet-900/40 hover:scale-[1.02] active:scale-95 transition-all">
                    Aplicar Magia <i class="fas fa-sparkles ml-1"></i>
                </button>
            </div>
        </div>

        <div id="ai-loading" class="absolute inset-0 bg-[#12122B]/90 z-20 flex flex-col items-center justify-center hidden">
            <div class="relative w-16 h-16 mb-4">
                <div class="absolute inset-0 border-4 border-[#6C4AE0]/20 rounded-full"></div>
                <div class="absolute inset-0 border-4 border-t-[#3BC9DB] rounded-full animate-spin"></div>
            </div>
            <p class="text-white font-medium animate-pulse text-sm">Reescribiendo la clase...</p>
        </div>
    </div>
</div>

    <script defer>
        if ('serviceWorker' in navigator && navigator.serviceWorker) {
            try {
                navigator.serviceWorker.register('/sw.js').catch(function () {});
            } catch (e) {}
        }

        // Pass saved lesson template to JS (used by renderMarkdown in app.js)
        window.novaLessonTemplate = '{{ auth()->user()->preferred_lesson_structure ?? "clasica" }}';
    </script>

    @include('components.lesson-template-picker')
</body>
</html>
