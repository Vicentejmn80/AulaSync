<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Nova · Hub Académico Inteligente</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <style>
        /* ── Design System Nova ─────────────────────────────── */
        :root {
            /* Dark mode (default) */
            --nova-deep: #0A0A1F;
            --nova-dark: #12122B;
            --nova-medium: #1E1A3A;
            --nova-light: #2D1F4A;
            --nova-violet: #6C4AE0;
            --nova-fuchsia: #C455ED;
            --nova-cyan: #3BC9DB;
            --nova-success: #22C55E;
            --nova-warning: #F59E0B;
            --nova-gradient: linear-gradient(135deg, #6C4AE0 0%, #C455ED 70%, #3BC9DB 100%);
            --nova-glass: rgba(255, 255, 255, 0.03);
            --nova-glass-border: rgba(108, 74, 224, 0.15);
            --nova-shadow: 0 20px 40px -15px rgba(0, 0, 0, 0.5);
            
            /* Text colors dark */
            --text-primary: rgba(255, 255, 255, 1);
            --text-secondary: rgba(255, 255, 255, 0.7);
            --text-tertiary: rgba(255, 255, 255, 0.4);
            --text-inverse: #0A0A1F;
            
            /* Backgrounds dark */
            --bg-primary: #0A0A1F;
            --bg-secondary: #12122B;
            --bg-tertiary: #1E1A3A;
            --bg-card: rgba(18, 18, 43, 0.8);
            --bg-sidebar: rgba(18, 18, 43, 0.8);
        }

        /* Light theme - Super llamativo e innovador */
        html.light {
            --nova-deep: #F0F4FF;
            --nova-dark: #FFFFFF;
            --nova-medium: #F5F0FF;
            --nova-light: #EAE6FF;
            --nova-violet: #7C3AED;
            --nova-fuchsia: #C026D3;
            --nova-cyan: #06B6D4;
            --nova-gradient: linear-gradient(135deg, #7C3AED 0%, #C026D3 50%, #06B6D4 100%);
            --nova-glass: rgba(124, 58, 237, 0.03);
            --nova-glass-border: rgba(124, 58, 237, 0.15);
            --nova-shadow: 0 20px 40px -15px rgba(124, 58, 237, 0.25);
            
            /* Text colors light */
            --text-primary: #1E1B4B;
            --text-secondary: #4C4A6E;
            --text-tertiary: #8B89A6;
            --text-inverse: #FFFFFF;
            
            /* Backgrounds light */
            --bg-primary: #F0F4FF;
            --bg-secondary: #FFFFFF;
            --bg-tertiary: #F5F0FF;
            --bg-card: rgba(255, 255, 255, 0.9);
            --bg-sidebar: rgba(255, 255, 255, 0.95);
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
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            overflow-x: hidden;
            overflow-y: auto;
            transition: background-color 0.3s ease, color 0.2s ease;
        }

/* ── Theme Toggle Button (CORREGIDO) ───────────────────── */
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

        html.light .nova-bg-orb {
            opacity: 0.7;
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
            min-height: 100vh;
            min-height: 100dvh;
            width: 100%;
            position: relative;
            backdrop-filter: blur(20px);
            background: rgba(0, 0, 0, 0.2);
            overflow-x: hidden;
        }

        html.light #hub-root {
            background: rgba(255, 255, 255, 0.3);
        }

        /* ── Sidebar Nova ───────────────────────────────────── */
        #hub-sidebar {
            width: 300px;
            min-width: 300px;
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
                padding: 4rem 0.75rem 1.5rem;
                min-height: calc(100dvh - 56px);
                overflow-y: auto;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .content-grid-2 {
                grid-template-columns: 1fr !important;
            }

            /* ── Calendario Mobile ───────────────────────────────── */
            .calendar-header {
                flex-direction: column;
                gap: 12px;
                margin-bottom: 15px;
            }

            .calendar-title h2 {
                font-size: 18px;
                text-align: center;
            }

            .calendar-title p {
                font-size: 12px;
                text-align: center;
            }

            .calendar-nav {
                width: 100%;
                justify-content: center;
                flex-wrap: wrap;
                gap: 6px;
            }

            .calendar-nav-btn {
                width: 36px;
                height: 36px;
                font-size: 14px;
            }

            .today-btn {
                padding: 0 12px;
                font-size: 12px;
            }

            .calendar-stats {
                width: 100%;
                text-align: center;
                margin-left: 0;
                margin-top: 8px;
            }

            .calendar-grid {
                padding: 12px;
                border-radius: 16px;
                min-height: 320px;
            }

            .weekdays {
                gap: 4px;
                margin-bottom: 8px;
            }

            .weekday {
                font-size: 10px;
            }

            .calendar-days {
                gap: 4px;
            }

            .calendar-day {
                min-height: 60px;
                padding: 6px;
                border-radius: 10px;
            }

            .day-number {
                top: 4px;
                right: 5px;
                font-size: 10px;
            }

            .day-content {
                margin-top: 14px;
                gap: 2px;
            }

            .cal-event {
                font-size: 8px;
                padding: 2px 4px;
                border-radius: 4px;
            }

            /* AI hint oculto en mobile */
            .ai-hint-cal {
                display: none;
            }

            /* Ajuste del modal de día */
            .day-modal-content {
                max-height: 70vh;
                overflow-y: auto;
            }

            /* Scroll mobile: mantener flujo natural sin duplicar reglas */
        }

        @media (min-width: 768px) {
            #hub-sidebar {
                position: relative;
                left: auto;
                top: auto;
                height: auto;
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
        .nav-section {
            padding: 20px 16px;
            border-bottom: 1px solid var(--nova-glass-border);
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            width: 100%;
            border: none;
            background: transparent;
            border-radius: 14px;
            color: var(--text-secondary);
            font-size: 14px;
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

        /* ── Cursos Sidebar ─────────────────────────────────── */
        .courses-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 20px 8px;
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
            overflow-y: auto;
            padding: 8px 12px 20px;
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
            gap: 12px;
            padding: 12px 12px;
            width: 100%;
            border: none;
            background: transparent;
            border-radius: 14px;
            margin-bottom: 4px;
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
            border-left: 3px solid var(--nova-fuchsia);
        }

        .course-avatar {
            width: 40px;
            height: 40px;
            background: var(--nova-gradient);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            font-weight: 700;
            color: white;
            position: relative;
            overflow: hidden;
            box-shadow: 0 5px 15px -5px var(--nova-violet);
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
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 4px;
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
            min-height: 100vh;
            min-height: 100dvh;
            overflow-y: auto;
            overflow-x: hidden;
            padding: 30px 35px;
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
            margin-bottom: 20px;
        }

        .section-title i {
            color: var(--nova-violet);
            font-size: 18px;
        }

        .section-title h2 {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-primary);
        }

        .courses-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }

        .course-card {
            background: var(--bg-card);
            backdrop-filter: blur(10px);
            border: 1px solid var(--nova-glass-border);
            border-radius: 24px;
            padding: 20px;
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
            height: 3px;
            background: var(--nova-gradient);
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }

        .course-card:hover {
            transform: translateY(-5px);
            border-color: var(--nova-violet);
            box-shadow: var(--nova-shadow);
        }

        .course-card:hover::before {
            transform: scaleX(1);
        }

        .course-card-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }

        .course-card-avatar {
            width: 50px;
            height: 50px;
            background: var(--nova-gradient);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: 700;
            color: white;
        }

        .course-card-info h3 {
            font-size: 16px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 4px;
        }

        .course-card-info p {
            font-size: 12px;
            color: var(--text-tertiary);
        }

        .course-stats {
            display: flex;
            gap: 15px;
            margin: 15px 0;
            padding: 10px 0;
            border-top: 1px solid var(--nova-glass-border);
            border-bottom: 1px solid var(--nova-glass-border);
        }

        .course-stat {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            color: var(--text-secondary);
        }

        .course-stat i {
            color: var(--nova-cyan);
        }

        .card-footer {
            display: flex;
            justify-content: flex-end;
            margin-top: 15px;
        }

        .card-footer span {
            font-size: 12px;
            color: var(--nova-violet);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: gap 0.2s ease;
        }

        .course-card:hover .card-footer span {
            gap: 10px;
            color: var(--nova-fuchsia);
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
            color: var(--text-tertiary);
        }

        .activity-weight {
            font-size: 11px;
            font-weight: 700;
            color: var(--nova-cyan);
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
            align-items: center;
            justify-content: space-between;
            margin-bottom: 25px;
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
            min-height: 100px;
            padding: 8px;
            position: relative;
            transition: all 0.2s ease;
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
        }

        .modal-nova {
            background: var(--bg-card);
            backdrop-filter: blur(20px);
            border: 1px solid var(--nova-glass-border);
            border-radius: 32px;
            width: 100%;
            max-width: 500px;
            overflow: hidden;
            box-shadow: var(--nova-shadow);
        }

        .modal-header {
            padding: 24px;
            border-bottom: 1px solid var(--nova-glass-border);
            display: flex;
            align-items: center;
            justify-content: space-between;
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
        }

        .modal-footer {
            padding: 20px 24px;
            border-top: 1px solid var(--nova-glass-border);
            display: flex;
            gap: 12px;
            justify-content: flex-end;
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
            background: linear-gradient(160deg, rgba(18, 18, 43, 0.95), rgba(9, 12, 28, 0.96));
            border-left: 1px solid var(--nova-glass-border);
            box-shadow: -20px 0 60px rgba(0, 0, 0, 0.45);
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
            background: rgba(108, 74, 224, 0.09);
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
            color: var(--text-secondary);
            background: rgba(255, 255, 255, 0.02);
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
            background: rgba(255, 255, 255, 0.02);
        }

        .grades-table {
            width: 100%;
            border-collapse: collapse;
        }

        .grades-table th,
        .grades-table td {
            padding: 12px 14px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
            color: var(--text-primary);
            font-size: 13px;
            text-align: left;
        }

        .grades-table th {
            background: rgba(108, 74, 224, 0.12);
            color: var(--text-secondary);
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.07em;
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
            border: 1px solid rgba(59, 201, 219, 0.35);
            color: #9be7f5;
            background: linear-gradient(130deg, rgba(108, 74, 224, 0.35), rgba(59, 201, 219, 0.22));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
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
    <script>
        // Theme initialization
        (function() {
            const savedTheme = localStorage.getItem('nova-theme');
            if (savedTheme === 'light') {
                document.documentElement.classList.add('light');
            } else {
                document.documentElement.classList.remove('light');
            }
        })();
    </script>
    @pwaHead
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
        <span class="min-w-0 truncate text-sm font-bold" style="color: var(--text-primary);">Nova Academy</span>
    </header>

    {{-- SIDEBAR NOVA --}}
    <aside
        id="hub-sidebar"
        :class="{ 'hub-sidebar-open': sidebarOpen }"
    >
    <div class="sidebar-brand" style="position: relative;">
    <div style="display: flex; align-items: center; justify-content: space-between; padding: 20px 20px 0 20px; position: relative; z-index: 10;">
        <button @click="loadWelcome()" class="brand-button" style="width: auto; flex: 1; padding: 0;">
            <div class="brand-icon">
                <i class="fa-solid fa-robot"></i>
            </div>
            <div class="brand-text">
                <div class="brand-title">Nova Academy</div>
                <div class="brand-subtitle">
                    <i class="fa-solid fa-circle"></i>
                    <span>{{ auth()->user()->name }}</span>
                </div>
            </div>
        </button>
        {{-- Theme Toggle Button --}}
        <button @click="toggleTheme" class="theme-toggle" title="Cambiar tema" style="position: relative; z-index: 20;">
            <i :class="isDarkMode ? 'fa-solid fa-sun' : 'fa-solid fa-moon'"></i>
        </button>
    </div>
    <div class="user-panel" style="position: relative; z-index: 5;">
        @include('components.user-control-panel')
    </div>
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
                <span>Mis Planificaciones</span>
                <i class="fa-solid fa-arrow-up-right-from-square" style="margin-left: auto; font-size: 10px; opacity: 0.4;"></i>
            </a>
            <a href="{{ route('teacher.activities.index') }}" class="nav-item">
                <i class="fa-solid fa-clipboard-list"></i>
                <span>Actividades</span>
                <i class="fa-solid fa-arrow-up-right-from-square" style="margin-left: auto; font-size: 10px; opacity: 0.4;"></i>
            </a>
        </nav>

        <div class="courses-header">
            <h4>Mis Cursos</h4>
            <button @click="showNewCourseModal = true" class="add-course-btn" title="Nuevo curso">
                <i class="fa-solid fa-plus"></i>
            </button>
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
                    <p style="color: var(--text-tertiary); font-size: 13px; margin-bottom: 10px;">Sin cursos aún</p>
                    <button @click="showNewCourseModal = true" style="color: var(--nova-cyan); font-size: 12px; font-weight: 600;">
                        Crear primer curso <i class="fa-solid fa-arrow-right"></i>
                    </button>
                </div>
            </template>
            <template x-for="c in courses" :key="c.id">
                <button @click="loadCourse(c.id)" :class="{ active: view === 'course' && currentCourseId === c.id }" class="course-btn">
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
    </aside>

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
                {{-- Header --}}
                <div class="flex items-center justify-between mt-4 mb-6">
                    <div>
                        <h1 style="font-size: 32px; font-weight: 800; background: linear-gradient(135deg, var(--text-primary), var(--nova-violet)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; margin-bottom: 6px;">
                            ¡Hola, {{ auth()->user()->name }}!
                        </h1>
                        <p style="color: var(--text-tertiary); font-size: 15px;">
                            {{ now()->isoFormat('dddd, D [de] MMMM') }} · Tu resumen académico
                        </p>
                    </div>
                    <a href="{{ route('teacher.planner.manual') }}" class="btn-primary">
                        <i class="fa-solid fa-wand-magic-sparkles"></i>
                        Generar Planificación
                    </a>
                </div>

                {{-- Stats --}}
                <template x-if="stats">
                    <div class="stats-grid">
                        <div class="stat-card-nova">
                            <div class="stat-header">
                                <span class="stat-label">Cursos activos</span>
                                <div class="stat-icon">📚</div>
                            </div>
                            <div class="stat-value" x-text="stats.total_courses"></div>
                            <div class="stat-footer">
                                <i class="fa-regular fa-calendar"></i>
                                <span>este año</span>
                            </div>
                        </div>
                        <div class="stat-card-nova">
                            <div class="stat-header">
                                <span class="stat-label">Alumnos</span>
                                <div class="stat-icon">👥</div>
                            </div>
                            <div class="stat-value" x-text="stats.total_students"></div>
                            <div class="stat-footer">
                                <i class="fa-regular fa-user"></i>
                                <span>inscritos total</span>
                            </div>
                        </div>
                        <div class="stat-card-nova">
                            <div class="stat-header">
                                <span class="stat-label">Promedio</span>
                                <div class="stat-icon" x-text="stats.climate?.icon ?? '📊'"></div>
                            </div>
                            <div class="stat-value" x-text="stats.avg_grade ?? '—'"></div>
                            <div class="stat-footer" x-text="stats.climate?.label ?? 'Sin datos'"></div>
                        </div>
                        <div class="stat-card-nova">
                            <div class="stat-header">
                                <span class="stat-label">Esta semana</span>
                                <div class="stat-icon">📅</div>
                            </div>
                            <div class="stat-value" x-text="stats.activities_this_week"></div>
                            <div class="stat-footer">
                                <i class="fa-regular fa-clock"></i>
                                <span>actividades</span>
                            </div>
                        </div>
                    </div>
                </template>

                {{-- Próxima entrega + Frase --}}
                <div class="content-grid-2">
                    <div class="content-card">
                        <div class="card-header">
                            <i class="fa-regular fa-clock"></i>
                            <div>
                                <h3>Próxima Entrega</h3>
                                <p>Actividades pendientes</p>
                            </div>
                        </div>
                        <template x-if="stats?.next_activity">
                            <div class="next-activity-content">
                                <div class="activity-tag">
                                    <i class="fa-regular fa-calendar"></i>
                                    <span x-text="stats.next_activity.title"></span>
                                </div>
                                <p style="color: var(--text-secondary); font-size: 13px;" x-text="stats.next_activity.course_name"></p>
                                <div style="margin-top: 10px;">
                                    <span style="background: var(--nova-glass); color: var(--nova-cyan); padding: 6px 14px; border-radius: 30px; font-size: 12px;">
                                        <i class="fa-regular fa-calendar mr-1"></i>
                                        <span x-text="stats.next_activity.due_date"></span>
                                    </span>
                                </div>
                            </div>
                        </template>
                        <template x-if="!stats?.next_activity">
                            <p style="color: var(--text-tertiary); font-style: italic;">Sin entregas pendientes próximamente.</p>
                        </template>
                    </div>

                    <div class="content-card quote-card">
                        <div class="quote-text">"{{ $dailyQuote }}"</div>
                        <div class="quote-author">Frase del día</div>
                    </div>
                </div>

                {{-- Mis Cursos --}}
                <template x-if="courses.length > 0">
                    <div>
                        <div class="section-title">
                            <i class="fa-solid fa-layer-group"></i>
                            <h2>Mis Cursos</h2>
                        </div>
                        <div class="courses-grid">
                            <template x-for="c in courses" :key="c.id">
                                <div @click="loadCourse(c.id)" class="course-card">
                                    <div class="course-card-header">
                                        <div class="course-card-avatar" x-text="c.subject_name.charAt(0).toUpperCase()"></div>
                                        <div class="course-card-info">
                                            <h3 x-text="c.subject_name"></h3>
                                            <p x-text="c.grade + (c.section ? ' / ' + c.section : '')"></p>
                                        </div>
                                    </div>
                                    <div class="course-stats">
                                        <span class="course-stat">
                                            <i class="fa-solid fa-users"></i>
                                            <span x-text="c.students_count"></span>
                                        </span>
                                        <span class="course-stat">
                                            <i class="fa-solid fa-clipboard-list"></i>
                                            <span x-text="c.activities_count"></span>
                                        </span>
                                    </div>
                                    <div class="card-footer">
                                        <span>Ver detalle <i class="fa-solid fa-arrow-right"></i></span>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>

                {{-- Empty State AI --}}
                <template x-if="courses.length === 0 && !coursesLoading">
                    <div class="empty-state-nova">
                        <div class="ai-orb">
                            <i class="fa-solid fa-robot"></i>
                        </div>
                        <h2 class="empty-title">Tu planificador está vacío</h2>
                        <p class="empty-subtitle">El asistente de IA está listo y esperando. Dile qué necesitas para empezar.</p>
                        
                        <div class="command-grid">
                            <button @click="sendAICommand('Crea mi primer curso de Matemáticas 3ro A')" class="command-btn">
                                <small>Sugerencia</small>
                                <p>"Crea mi primer curso de Matemáticas 3ro A"</p>
                            </button>
                            <button @click="sendAICommand('Crea Inglés 2do grado y agrega a María, Pedro y Luis')" class="command-btn">
                                <small>Encadenar</small>
                                <p>"Crea Inglés 2do y agrega a María, Pedro y Luis"</p>
                            </button>
                        </div>

                        <div class="action-buttons">
                            <button @click="openBubbleWithFocus()" class="btn-primary">
                                <i class="fa-solid fa-robot"></i>
                                Hablar con el Asistente
                            </button>
                            <button @click="showNewCourseModal = true" class="btn-secondary">
                                <i class="fa-solid fa-plus"></i>
                                Crear manualmente
                            </button>
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
                            <span class="action-badge">
                                <i class="fa-regular fa-calendar"></i>
                                <span x-text="courseData.school_year ?? ''"></span>
                            </span>
                        </div>
                    </div>
                    <div style="display: flex; gap: 10px;">
                        <a :href="'{{ route('teacher.grades.index') }}'" class="btn-secondary" style="padding: 10px 20px;">
                            <i class="fa-solid fa-table-cells"></i>
                            Notas
                        </a>
                        <a href="{{ route('teacher.courses.index') }}" class="btn-primary" style="padding: 10px 20px;">
                            <i class="fa-solid fa-pen-to-square"></i>
                            Gestionar
                        </a>
                    </div>
                </div>

                {{-- Paneles --}}
                <div class="panel-grid">
                    {{-- Alumnos --}}
                    <div class="panel-card">
                        <div class="panel-header">
                            <h3><i class="fa-solid fa-users"></i> Alumnos</h3>
                            <span class="panel-count" x-text="courseData.students.length"></span>
                            <a :href="`/teacher/courses`" class="panel-link">
                                Importar <i class="fa-solid fa-plus"></i>
                            </a>
                        </div>
                        <template x-if="courseData.students.length === 0">
                            <div style="padding: 40px 20px; text-align: center;">
                                <i class="fa-solid fa-user-graduate" style="font-size: 30px; color: var(--text-tertiary); margin-bottom: 10px;"></i>
                                <p style="color: var(--text-tertiary);">Sin alumnos inscritos.</p>
                                <p style="color: var(--text-tertiary); font-size: 12px; margin-top: 5px;">"Agrega a Juan en este curso"</p>
                            </div>
                        </template>
                        <div style="max-height: 300px; overflow-y: auto;">
                            <template x-for="(s, idx) in courseData.students" :key="s.id">
                                <div class="student-item">
                                    <div class="student-index" x-text="idx + 1"></div>
                                    <div style="display:flex; align-items:center; justify-content:space-between; width:100%; gap:10px;">
                                        <span class="student-name" x-text="s.name"></span>
                                        <span class="inline-flex items-center rounded-full border border-cyan-400/30 bg-cyan-500/10 px-2.5 py-1 text-[11px] font-semibold text-cyan-200"
                                            x-text="`Acum: ${s.promedio_acumulado ?? s.nota_actual ?? s.avg_score ?? '—'}`"></span>
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
                        
                        <button @click="deleteActivity(a.id, a.title)" class="action-btn warning">
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
                {{-- Filtro de plan block --}}
                <template x-if="planBlockFilter">
                    <div style="background: var(--nova-glass); border: 1px solid var(--nova-glass-border); border-radius: 20px; padding: 12px 20px; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between;">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <i class="fa-solid fa-calendar-check" style="color: var(--nova-cyan);"></i>
                            <span style="color: var(--text-primary);">Mostrando Plan Mensual #<span x-text="planBlockFilter"></span></span>
                        </div>
                        <button @click="planBlockFilter = null; loadCalendar()" style="color: var(--nova-fuchsia); font-size: 12px;">
                            <i class="fa-solid fa-xmark"></i> Ver todo
                        </button>
                    </div>
                </template>

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
                                        'today': cell !== null && isToday(cell)
                                     }">
                                    <template x-if="cell !== null">
                                        <div>
                                            <span class="day-number" x-text="cell"></span>
                                            <div class="day-content">
                                                <template x-for="act in activitiesForDay(cell).slice(0,2)" :key="act.id">
                                                    <button @click.stop="setActivityContext(act); activityModal = act" 
                                                            class="cal-event"
                                                            :class="act.type === 'clase' ? 'clase' : (act.is_homework ? 'homework' : 'actividad')"
                                                            :title="act.title">
                                                        <span x-text="act.title.length > 15 ? act.title.substring(0,12)+'...' : act.title"></span>
                                                    </button>
                                                </template>
                                                <template x-if="activitiesForDay(cell).length > 2">
                                                    <button @click.stop="openDayModal(cell)" class="more-events">
                                                        +<span x-text="activitiesForDay(cell).length - 2"></span> más
                                                    </button>
                                                </template>
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
    <div class="modal-nova">
        <div class="modal-header">
            <h3 x-text="activityModal?.title"></h3>
            <button @click="activityModal = null" class="modal-close">
                <i class="fa-solid fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 15px;">
                <span class="activity-type-badge" x-text="activityModal?.type === 'clase' ? 'CLASE' : 'ACTIVIDAD'"></span>
                <span style="color: var(--text-tertiary); font-size: 12px;" x-text="activityModal?.course_name"></span>
            </div>
            <div class="markdown-body" style="color: var(--text-secondary); font-size: 14px; line-height: 1.6; margin-bottom: 20px;" x-html="activityModal?.description ? renderMarkdown(activityModal.description) : '<p>Sin descripción.</p>'"></div>
            <div style="display: flex; gap: 15px; color: var(--text-secondary); font-size: 13px;">
                <span><i class="fa-regular fa-calendar" style="color: var(--nova-cyan); margin-right: 5px;"></i> <span x-text="activityModal?.due_date ?? '—'"></span></span>
                <span x-show="activityModal?.max_score > 0"><i class="fa-solid fa-star" style="color: #F59E0B; margin-right: 5px;"></i> Máx: <span x-text="activityModal?.max_score"></span></span>
            </div>

            <div id="tareas-container" class="tareas-box">
                <div class="tareas-title">Tareas asociadas</div>
                <template x-if="(activityModal?.tareas ?? []).length === 0">
                    <p style="font-size: 12px; color: var(--text-tertiary); margin: 0;">
                        Aún no hay tareas guardadas para esta clase.
                    </p>
                </template>
                <template x-for="task in (activityModal?.tareas ?? [])" :key="task.id">
                    <div class="tarea-row">
                        <div style="font-size: 13px; font-weight: 700; color: var(--text-primary);" x-text="task.titulo"></div>
                        <div style="font-size: 12px; color: var(--text-secondary); margin-top: 4px;" x-text="task.descripcion || 'Sin descripción'"></div>
                        <div style="margin-top: 6px; display: flex; gap: 10px; font-size: 11px; color: var(--text-tertiary);">
                            <span><i class="fa-regular fa-calendar"></i> <span x-text="task.fecha_entrega || 'Sin fecha'"></span></span>
                            <span><i class="fa-solid fa-award"></i> <span x-text="task.puntos"></span> pts</span>
                        </div>
                    </div>
                </template>
            </div>

            <div id="nee-container" class="tareas-box" style="margin-top: 14px;">
                <div class="tareas-title">Adaptaciones asociadas</div>
                <template x-if="!activityModal?.nee_adaptation">
                    <p style="font-size: 12px; color: var(--text-tertiary); margin: 0;">
                        Aún no hay adaptaciones NEE guardadas para esta clase.
                    </p>
                </template>
                <template x-if="activityModal?.nee_adaptation">
                    <div class="tarea-row">
                        <div style="font-size: 13px; font-weight: 700; color: var(--text-primary);">
                            <span x-text="activityModal?.nee_type ? `🧠 ${activityModal.nee_type}` : '🧠 NEE'"></span>
                        </div>
                        <div style="font-size: 12px; color: var(--text-secondary); margin-top: 4px;" x-text="activityModal?.nee_adaptation"></div>
                    </div>
                </template>
            </div>
        </div>
        <div class="modal-footer">
            <template x-if="activityModal?.type === 'clase'">
                <button @click="openTaskIdeaModal()" class="btn-secondary">
                    <i class="fa-solid fa-dice"></i>
                    Sugerir Tarea 🎲
                </button>
            </template>
            <template x-if="activityModal?.type === 'clase'">
                <button @click="openNeeModal()" class="btn-secondary">
                    <i class="fa-solid fa-wand-magic-sparkles"></i>
                    ✨ Adaptación NEE
                </button>
            </template>
            <template x-if="activityModal?.type === 'clase'">
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
                }); activityModal = null" class="btn-secondary">
                    <i class="fa-solid fa-robot"></i>
                    Modificar con IA
                </button>
            </template>
            <template x-if="activityModal?.type !== 'clase'">
                <button @click="openGradesSlideover(activityModal)" class="btn-primary">
                    <i class="fa-solid fa-table-cells"></i>
                    Cargar Notas
                </button>
            </template>
            <button @click="deleteActivity(activityModal?.id, activityModal?.title)" class="action-btn warning">
                <i class="fa-solid fa-trash-alt"></i>
                Eliminar
            </button>
            <button @click="activityModal = null" class="btn-secondary">
                Cerrar
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
                    <p class="grades-slideover-eyebrow">Nova Academy · Cargar Notas</p>
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
                    Máx: <strong x-text="gradesSlideover.activity?.max_score ?? 20"></strong>
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
                                                    :max="gradesSlideover.activity?.max_score ?? 20"
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
        <div class="modal-nova" style="max-width: 400px;">
            <div class="modal-header" style="background: var(--nova-gradient);">
                <h3 style="color: white;">Eventos del día <span x-text="dayModal?.day"></span></h3>
                <button @click="dayModal = null" class="modal-close" style="background: rgba(255,255,255,0.2); color: white;">
                    <i class="fa-solid fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div style="display: flex; flex-direction: column; gap: 10px;">
                    <template x-for="act in (dayModal?.activities ?? [])" :key="act.id">
                        <div style="display: flex; align-items: center; gap: 12px; padding: 10px; background: var(--nova-glass); border-radius: 16px;">
                            <div style="width: 35px; height: 35px; border-radius: 12px; background: var(--nova-gradient); display: flex; align-items: center; justify-content: center;">
                                <i class="fa-regular fa-calendar" style="color: white; font-size: 14px;"></i>
                            </div>
                            <div style="flex: 1;">
                                <p style="font-weight: 600; color: var(--text-primary); margin-bottom: 4px;" x-text="act.title"></p>
                                <p style="font-size: 11px; color: var(--text-tertiary);" x-text="act.course_name"></p>
                            </div>
                            <button @click="setActivityContext(act); activityModal = act; dayModal = null" style="color: var(--nova-cyan); font-size: 12px;">
                                Ver
                            </button>
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
        hiddenWidgets:   [],
        activityModal:   null,
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
        isDarkMode:      true,

        // Theme toggle method
        toggleTheme() {
            this.isDarkMode = !this.isDarkMode;
            if (this.isDarkMode) {
                document.documentElement.classList.remove('light');
                localStorage.setItem('nova-theme', 'dark');
            } else {
                document.documentElement.classList.add('light');
                localStorage.setItem('nova-theme', 'light');
            }
        },

        closeSidebarMobile() {
            if (window.matchMedia('(max-width: 767px)').matches) {
                this.sidebarOpen = false;
            }
        },

        async init() {
            // Initialize theme state
            this.isDarkMode = !document.documentElement.classList.contains('light');

            await this.refreshCourseSidebar();

            const urlCourse = {{ $initialCourseId ?? 'null' }};
            if (this.planBlockFilter) {
                await this.loadCalendar();
            } else if (urlCourse) {
                await this.loadCourse(urlCourse);
            } else {
                await this.loadWelcome();
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

        async loadCalendar(month = null) {
            this.view          = 'calendar';
            this.canvasLoading = true;
            this.courseData    = null;
            this.currentCourseId = null;
            this.calendarMonth = month || new Date().toISOString().slice(0, 7);
            this.setNovaContext({ type: 'calendar', month: this.calendarMonth });

            try {
                let url = `/teacher/api/calendar?month=${this.calendarMonth}`;
                if (this.planBlockFilter) {
                    url += `&plan_block=${this.planBlockFilter}`;
                }
                const res = await fetch(url, {
                    headers: { 'Accept': 'application/json' }
                });
                this.calendarData = await res.json();
            } catch (e) {
                console.warn('Calendar fetch failed', e);
            } finally {
                this.canvasLoading = false;
                this.closeSidebarMobile();
            }
        },

        async deleteActivity(id, title) {
            if (!confirm(`¿Eliminar "${title}"?\nEsta acción no se puede deshacer.`)) return;
            try {
                const res = await fetch(`/teacher/activities/${id}`, {
                    method:  'DELETE',
                    headers: {
                        'Accept':       'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    },
                });
                if (res.ok || res.status === 302) {
                    await this.loadCourse(this.currentCourseId);
                    await this.refreshCourseSidebar();
                } else {
                    alert('Error al eliminar la actividad. Inténtalo de nuevo.');
                }
            } catch (e) {
                console.error('deleteActivity', e);
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
            const acts = this.activitiesForDay(day);
            if (acts.length === 0) return;
            this.dayModal = { day, activities: acts };
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
            return this.calendarData.activities_by_day?.[key] ?? [];
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

            if (!activity) {
                window.dispatchEvent(new CustomEvent('ai-toast', {
                    detail: { message: 'No se encontró la actividad en la vista actual.', type: 'error' }
                }));
                return;
            }

            if (this.view !== 'calendar' && targetDate) {
                const month = String(targetDate).slice(0, 7);
                if (month) {
                    await this.loadCalendar(month);
                    activity = this.findActivityByIdLocal(activityId) ?? activity;
                }
            }

            this.setActivityContext(activity);
            this.activityModal = activity;

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
                <h3 class="text-white font-bold text-lg">Asistente Nova IA</h3>
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

    @laravelPwa
    @pwaInstallButton
</body>
</html>