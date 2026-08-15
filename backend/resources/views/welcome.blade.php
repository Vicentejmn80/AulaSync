<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>AulaSync | La plataforma que sincroniza a tu colegio</title>
    <meta name="description" content="Centraliza planificación, tareas, calificaciones, asistencia, calendario y comunicación escolar con AulaSync.">
    <meta name="theme-color" content="#7C3AED">

    <!-- Open Graph -->
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="AulaSync">
    <meta property="og:title" content="AulaSync | La plataforma que sincroniza a tu colegio">
    <meta property="og:description" content="Planificación, tareas, calificaciones, asistencia y comunicación en un solo lugar.">
    <meta property="og:locale" content="es_VE">

    <link rel="icon" href="/favicon.ico?v=3" sizes="any">
    <link rel="icon" type="image/png" href="/favicon-32x32.png?v=3" sizes="32x32">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png?v=3">
    <link rel="shortcut icon" href="/favicon.png?v=3">

    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@500;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>
        /* ============================================================
           AulaSync — Design tokens
           ============================================================ */
        :root {
            --az-ink: #1E1133;
            --az-indigo-900: #3D105D;
            --az-indigo-700: #6B21A8;
            --az-indigo-600: #8B2FC9;
            --az-indigo-500: #A855F7;
            --az-violet-500: #C455ED;
            --az-violet-300: #E7C4FA;
            --az-violet-100: #F9EEFF;
            --az-rose-500: #EC4899;
            --az-rose-300: #F8B6D9;
            --az-rose-100: #FDE7F4;
            --az-mint-600: #159A79;
            --az-mint-500: #1FBE93;
            --az-mint-100: #DFF6EC;
            --az-bg: #FBFAF7;
            --az-surface: #FFFFFF;
            --az-border: #EEDDF7;
            --az-text-secondary: #5B4B72;
            --az-text-tertiary: #9D89B6;
            --az-shadow-sm: 0 1px 2px rgba(104, 33, 168, 0.08);
            --az-shadow-md: 0 16px 40px -20px rgba(107, 33, 168, 0.24);
            --az-shadow-lg: 0 32px 64px -24px rgba(107, 33, 168, 0.32);
            --az-radius-lg: 26px;
            --az-radius-md: 18px;
            --az-radius-sm: 12px;
            --az-gradient-brand: linear-gradient(135deg, #7C3AED 0%, #C455ED 55%, #EC4899 100%);
            --az-gradient-soft: linear-gradient(135deg, rgba(168, 85, 247, 0.12) 0%, rgba(236, 72, 153, 0.10) 100%);
            --az-container: 1180px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        html { scroll-behavior: smooth; }

        @media (prefers-reduced-motion: reduce) {
            html { scroll-behavior: auto; }
            *, *::before, *::after {
                animation-duration: 0.001ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.001ms !important;
                scroll-behavior: auto !important;
            }
        }

        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: var(--az-bg);
            color: var(--az-ink);
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
            position: relative;
        }

        body::before {
            content: '';
            position: fixed;
            inset: 0;
            z-index: -2;
            pointer-events: none;
            background:
                radial-gradient(540px 380px at 6% 18%, rgba(196, 85, 237, 0.16), transparent 68%),
                radial-gradient(500px 380px at 94% 14%, rgba(236, 72, 153, 0.14), transparent 68%),
                radial-gradient(520px 420px at 90% 80%, rgba(139, 126, 240, 0.14), transparent 72%);
        }

        h1, h2, h3, h4 {
            font-family: 'Manrope', 'Inter', sans-serif;
            font-weight: 800;
            color: var(--az-ink);
            letter-spacing: -0.01em;
            line-height: 1.15;
        }

        a { color: inherit; }

        img, svg { display: block; max-width: 100%; }

        button { font-family: inherit; }

        :focus-visible {
            outline: 2.5px solid var(--az-indigo-500);
            outline-offset: 3px;
            border-radius: 6px;
        }

        .az-container {
            max-width: var(--az-container);
            margin: 0 auto;
            padding: 0 24px;
        }

        .az-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 12.5px;
            font-weight: 700;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: var(--az-indigo-600);
            background: var(--az-violet-100);
            border: 1px solid var(--az-violet-300);
            border-radius: 30px;
            padding: 7px 16px;
        }

        .az-eyebrow i { font-size: 11px; }

        .az-section-head {
            max-width: 720px;
            margin-bottom: 48px;
        }

        .az-section-head.center { margin-inline: auto; text-align: center; }

        .az-section-title {
            font-size: clamp(1.9rem, 3.4vw, 2.6rem);
            margin: 16px 0 14px;
        }

        .az-section-text {
            font-size: 1.05rem;
            color: var(--az-text-secondary);
            max-width: 620px;
        }

        .az-section-head.center .az-section-text { margin-inline: auto; }

        section { padding: 88px 0; }

        /* ── Buttons ─────────────────────────────────────────────── */
        .az-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-weight: 700;
            font-size: 15.5px;
            padding: 14px 26px;
            border-radius: 999px;
            border: 1px solid transparent;
            cursor: pointer;
            text-decoration: none;
            transition: transform 0.22s ease, box-shadow 0.22s ease, background-color 0.22s ease, color 0.22s ease, border-color 0.22s ease;
            white-space: nowrap;
        }

        .az-btn-primary {
            background: var(--az-gradient-brand);
            color: #fff;
            box-shadow: 0 14px 30px -12px rgba(139, 46, 201, 0.56);
        }

        .az-btn-primary:hover { transform: translateY(-2px); box-shadow: 0 18px 36px -12px rgba(139, 46, 201, 0.66); }

        .az-btn-secondary {
            background: var(--az-surface);
            border-color: var(--az-border);
            color: var(--az-ink);
        }

        .az-btn-secondary:hover { border-color: var(--az-indigo-500); transform: translateY(-2px); box-shadow: var(--az-shadow-sm); }

        .az-btn-ghost {
            background: transparent;
            color: var(--az-indigo-700);
            padding: 10px 4px;
            font-size: 14.5px;
        }

        .az-btn-ghost:hover { color: var(--az-indigo-900); }

        .az-btn-sm { padding: 10px 18px; font-size: 14px; }

        .az-btn-block { width: 100%; }

        /* ============================================================
           Navbar
           ============================================================ */
        .az-nav {
            position: sticky;
            top: 0;
            z-index: 60;
            background: rgba(255, 251, 255, 0.7);
            border-bottom: 1px solid transparent;
            transition: background-color 0.25s ease, border-color 0.25s ease, box-shadow 0.25s ease;
        }

        .az-nav.is-scrolled {
            background: rgba(255, 250, 255, 0.9);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            border-bottom-color: var(--az-border);
            box-shadow: 0 8px 24px -18px rgba(107, 33, 168, 0.34);
        }

        .az-nav-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
            min-height: 64px;
            padding: 12px 24px;
        }

        .az-logo {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            flex-shrink: 0;
        }


        /* Mark: cuadro fucsia + emoji leyendo */
        .az-nav .az-logo-mark {
            width: 52px;
            height: 52px;
            min-width: 52px;
            min-height: 52px;
            border-radius: 13px;
            background: var(--az-gradient-brand);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            box-shadow: 0 8px 20px -8px rgba(139, 46, 201, 0.55);
            overflow: hidden;
            padding: 0;
        }

        .az-logo-mark {
            width: 44px;
            height: 44px;
            min-width: 44px;
            min-height: 44px;
            border-radius: 11px;
            background: var(--az-gradient-brand);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            box-shadow: 0 6px 16px -6px rgba(139, 46, 201, 0.45);
            overflow: hidden;
            padding: 0;
        }

        .az-logo-mark img {
            width: 100%;
            height: 100%;
            max-width: none;
            object-fit: contain;
            display: block;
            transform: scale(1.65);
            transform-origin: center center;
        }

        .az-nav .az-logo-text {
            font-family: 'Manrope', 'Inter', sans-serif;
            font-size: 1.25rem;
            font-weight: 900;
            letter-spacing: 0.08em;
            line-height: 1;
            text-transform: uppercase;
            color: #0f172a;
            margin: 0;
            padding-top: 1px;
        }

        .az-logo-text {
            font-family: 'Manrope', sans-serif;
            font-weight: 800;
            font-size: 1.125rem;
            letter-spacing: 0.04em;
            line-height: 1;
            color: var(--az-ink);
        }

        .az-nav-links {
            display: flex;
            align-items: center;
            gap: 30px;
            list-style: none;
        }

        .az-nav-links a {
            text-decoration: none;
            color: var(--az-text-secondary);
            font-weight: 600;
            font-size: 14.5px;
            transition: color 0.2s ease;
        }

        .az-nav-links a:hover { color: var(--az-indigo-700); }

        .az-nav-actions { display: flex; align-items: center; gap: 12px; flex-shrink: 0; }

        .az-burger {
            display: none;
            width: 42px;
            height: 42px;
            border-radius: 10px;
            border: 1px solid var(--az-border);
            background: var(--az-surface);
            align-items: center;
            justify-content: center;
            cursor: pointer;
            flex-shrink: 0;
        }

        .az-burger span,
        .az-burger span::before,
        .az-burger span::after {
            content: '';
            display: block;
            width: 18px;
            height: 2px;
            background: var(--az-ink);
            border-radius: 2px;
            position: relative;
            transition: transform 0.25s ease, opacity 0.25s ease;
        }

        .az-burger span::before { position: absolute; top: -6px; }
        .az-burger span::after { position: absolute; top: 6px; }

        .az-burger[aria-expanded="true"] span { background: transparent; }
        .az-burger[aria-expanded="true"] span::before { transform: translateY(6px) rotate(45deg); }
        .az-burger[aria-expanded="true"] span::after { transform: translateY(-6px) rotate(-45deg); }

        .az-mobile-menu {
            display: none;
            flex-direction: column;
            gap: 4px;
            padding: 10px 24px 22px;
            border-top: 1px solid var(--az-border);
            background: var(--az-surface);
        }

        .az-mobile-menu.is-open { display: flex; }

        .az-mobile-menu a {
            text-decoration: none;
            color: var(--az-ink);
            font-weight: 600;
            padding: 12px 4px;
            border-bottom: 1px solid var(--az-border);
        }

        .az-mobile-menu .az-nav-actions { flex-direction: column; align-items: stretch; margin-top: 12px; }
        .az-mobile-menu .az-btn { width: 100%; }

        @media (max-width: 900px) {
            .az-nav-links { display: none; }
            .az-nav-actions .az-btn-secondary { display: none; }
            .az-burger { display: flex; }
        }

        /* ============================================================
           Hero
           ============================================================ */
        .az-hero { padding: 64px 0 40px; position: relative; overflow: hidden; }

        .az-hero-bg {
            position: absolute;
            inset: 0;
            z-index: -1;
            background:
                radial-gradient(620px 440px at 86% -12%, rgba(236, 72, 153, 0.20), transparent 70%),
                radial-gradient(580px 460px at -12% 26%, rgba(168, 85, 247, 0.18), transparent 72%),
                radial-gradient(500px 360px at 72% 84%, rgba(196, 85, 237, 0.15), transparent 70%);
        }

        .az-hero-inner {
            display: grid;
            grid-template-columns: 1.05fr 0.95fr;
            gap: 56px;
            align-items: center;
        }

        .az-hero-title {
            font-size: clamp(2.3rem, 4.4vw, 3.4rem);
            margin: 20px 0 20px;
        }

        .az-hero-text {
            font-size: 1.12rem;
            color: var(--az-text-secondary);
            max-width: 540px;
            margin-bottom: 30px;
        }

        .az-hero-actions {
            display: flex;
            gap: 14px;
            flex-wrap: wrap;
            margin-bottom: 18px;
        }

        .az-hero-trust {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13.5px;
            color: var(--az-text-tertiary);
        }

        .az-hero-trust i { color: var(--az-mint-500); }

        /* ── Dashboard mockup ────────────────────────────────────── */
        .az-hero-visual { position: relative; }

        .az-dashboard-mock {
            background: var(--az-surface);
            border: 1px solid var(--az-border);
            border-radius: var(--az-radius-lg);
            box-shadow: var(--az-shadow-lg);
            overflow: hidden;
            position: relative;
        }

        .az-mock-topbar {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 14px 18px;
            border-bottom: 1px solid var(--az-border);
            background: linear-gradient(180deg, #FFFFFF 0%, #FAF9FE 100%);
        }

        .az-mock-dot { width: 9px; height: 9px; border-radius: 50%; }
        .az-mock-dot.a { background: #F5B25E; }
        .az-mock-dot.b { background: #1FBE93; }
        .az-mock-dot.c { background: #C7C0F7; }
        .az-mock-topbar span {
            margin-left: 8px;
            font-size: 12px;
            color: var(--az-text-tertiary);
            font-weight: 600;
        }

        .az-mock-body { display: grid; grid-template-columns: 56px 1fr; min-height: 380px; }

        .az-mock-sidebar {
            background: linear-gradient(180deg, #4B1A78 0%, #721CA3 65%, #8B2FC9 100%);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 18px;
            padding: 18px 0;
        }

        .az-mock-sidebar i {
            color: rgba(255,255,255,0.55);
            font-size: 14px;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 9px;
        }

        .az-mock-sidebar i.active { color: #fff; background: rgba(255,255,255,0.14); }

        .az-mock-main { padding: 20px; display: flex; flex-direction: column; gap: 14px; }

        .az-mock-row { display: grid; grid-template-columns: 1.2fr 1fr; gap: 14px; }

        .az-mock-card {
            border: 1px solid var(--az-border);
            border-radius: var(--az-radius-sm);
            padding: 14px 16px;
            background: #FEFEFD;
        }

        .az-mock-card h4 {
            font-family: 'Inter', sans-serif;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--az-text-tertiary);
            margin-bottom: 10px;
        }

        .az-mock-line {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 13px;
            padding: 6px 0;
            border-bottom: 1px dashed var(--az-border);
            color: var(--az-ink);
        }

        .az-mock-line:last-child { border-bottom: none; }

        .az-mock-tag {
            font-size: 10.5px;
            font-weight: 700;
            padding: 3px 9px;
            border-radius: 20px;
        }

        .az-mock-tag.mint { background: var(--az-mint-100); color: var(--az-mint-600); }
        .az-mock-tag.violet { background: var(--az-violet-100); color: var(--az-indigo-700); }
        .az-mock-tag.amber { background: #FDF0DD; color: #B4761F; }

        .az-mock-week {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 6px;
        }

        .az-mock-week-day {
            border-radius: 8px;
            background: #F4F3FB;
            padding: 8px 4px;
            text-align: center;
            font-size: 10.5px;
            color: var(--az-text-secondary);
            font-weight: 600;
        }

        .az-mock-week-day.today { background: var(--az-indigo-700); color: #fff; }

        .az-mock-announcement {
            display: flex;
            gap: 10px;
            align-items: flex-start;
            border: 1px solid var(--az-violet-300);
            background: var(--az-violet-100);
            border-radius: var(--az-radius-sm);
            padding: 12px 14px;
        }

        .az-mock-announcement i { color: var(--az-indigo-600); margin-top: 2px; }

        .az-mock-announcement p { font-size: 12.5px; color: var(--az-ink); font-weight: 600; }
        .az-mock-announcement span { font-size: 11.5px; color: var(--az-text-tertiary); }

        .az-floating-chip {
            position: absolute;
            display: flex;
            align-items: center;
            gap: 8px;
            background: var(--az-surface);
            border: 1px solid var(--az-border);
            border-radius: 999px;
            padding: 9px 15px;
            font-size: 12.5px;
            font-weight: 700;
            color: var(--az-ink);
            box-shadow: var(--az-shadow-md);
        }

        .az-floating-chip i { font-size: 12px; }
        .az-floating-chip.mint i { color: var(--az-mint-500); }
        .az-floating-chip.indigo i { color: var(--az-indigo-600); }
        .az-floating-chip.violet i { color: var(--az-violet-500); }

        .az-hero-mascot {
            position: absolute;
            right: 14px;
            top: 52%;
            width: 76px;
            height: 76px;
            object-fit: contain;
            filter: drop-shadow(0 14px 24px rgba(139, 46, 201, 0.4));
            z-index: 5;
            transform: translateY(-50%);
            pointer-events: none;
        }

        .chip-1 { top: -18px; left: -22px; }
        .chip-2 { bottom: 22%; right: -30px; }
        .chip-3 { bottom: -18px; left: 14%; }

        @media (prefers-reduced-motion: no-preference) {
            .az-floating-chip { animation: az-float 5s ease-in-out infinite; }
            .chip-2 { animation-delay: 1.2s; }
            .chip-3 { animation-delay: 2.4s; }
        }

        @keyframes az-float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-8px); }
        }

        @media (max-width: 980px) {
            .az-hero-inner { grid-template-columns: 1fr; }
            .az-hero-visual { order: -1; }
            .az-hero-title, .az-hero-text, .az-hero-actions, .az-hero-trust { text-align: left; }
            .chip-1 { left: 0; }
            .chip-2 { right: 0; }
        }

        /* ============================================================
           Trust band
           ============================================================ */
        .az-trustband { padding: 56px 0; }

        .az-chip-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 14px;
            margin-top: 32px;
            position: relative;
        }

        .az-trust-mascot {
            position: absolute;
            right: -10px;
            bottom: -38px;
            width: 76px;
            opacity: 0.9;
            pointer-events: none;
            filter: drop-shadow(0 8px 18px rgba(196, 85, 237, 0.28));
        }

        .az-chip {
            border: 1px solid var(--az-border);
            background: var(--az-surface);
            border-radius: var(--az-radius-md);
            padding: 20px 18px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            transition: transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease;
        }

        .az-chip:hover { transform: translateY(-3px); box-shadow: var(--az-shadow-md); border-color: var(--az-violet-300); }

        .az-chip i {
            width: 34px;
            height: 34px;
            border-radius: 10px;
            background: var(--az-violet-100);
            color: var(--az-indigo-700);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }

        .az-chip span { font-weight: 700; font-size: 14.5px; }

        @media (max-width: 900px) { .az-chip-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 560px) { .az-chip-grid { grid-template-columns: 1fr; } }

        /* ============================================================
           Problem / Solution
           ============================================================ */
        .az-problem { background: var(--az-surface); border-top: 1px solid var(--az-border); border-bottom: 1px solid var(--az-border); }

        .az-ps-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-top: 16px;
        }

        .az-ps-col {
            border-radius: var(--az-radius-lg);
            padding: 30px 28px;
            border: 1px solid var(--az-border);
        }

        .az-ps-col-neg { background: #FBF8F6; }
        .az-ps-col-pos { background: var(--az-violet-100); border-color: var(--az-violet-300); }

        .az-ps-col h3 {
            font-size: 1.2rem;
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .az-ps-col-neg h3 i { color: #C0644B; }
        .az-ps-col-pos h3 i { color: var(--az-mint-600); }

        .az-ps-list { list-style: none; display: flex; flex-direction: column; gap: 14px; }

        .az-ps-list li {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            font-size: 14.5px;
            color: var(--az-text-secondary);
        }

        .az-ps-list li i { margin-top: 3px; font-size: 12px; flex-shrink: 0; }
        .az-ps-col-neg li i { color: #C0644B; }
        .az-ps-col-pos li i { color: var(--az-mint-600); }
        .az-ps-col-pos li { color: var(--az-ink); font-weight: 500; }

        @media (max-width: 800px) { .az-ps-grid { grid-template-columns: 1fr; } }

        /* ============================================================
           Features (tabs)
           ============================================================ */
        .az-tabs {
            display: flex;
            gap: 8px;
            overflow-x: auto;
            padding-bottom: 6px;
            margin-bottom: 28px;
            scrollbar-width: thin;
        }

        .az-tab-btn {
            flex-shrink: 0;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 11px 18px;
            border-radius: 999px;
            border: 1px solid var(--az-border);
            background: var(--az-surface);
            color: var(--az-text-secondary);
            font-weight: 700;
            font-size: 13.5px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .az-tab-btn i { font-size: 12px; }

        .az-tab-btn:hover { border-color: var(--az-violet-300); color: var(--az-indigo-700); }

        .az-tab-btn[aria-selected="true"] {
            background: var(--az-gradient-brand);
            border-color: transparent;
            color: #fff;
            box-shadow: 0 10px 22px -10px rgba(45,52,148,0.5);
        }

        .az-tab-btn .az-soon-dot {
            width: 6px; height: 6px; border-radius: 50%; background: var(--az-mint-500); flex-shrink: 0;
        }

        .az-tab-panel { display: none; }
        .az-tab-panel.is-active { display: block; animation: az-fade-in 0.35s ease; }

        @keyframes az-fade-in {
            from { opacity: 0; transform: translateY(6px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .az-panel-grid {
            display: grid;
            grid-template-columns: 0.95fr 1.05fr;
            gap: 48px;
            align-items: center;
            border: 1px solid var(--az-border);
            background: var(--az-surface);
            border-radius: var(--az-radius-lg);
            padding: 40px;
        }

        .az-panel-copy h3 { font-size: 1.6rem; margin-bottom: 14px; }
        .az-panel-copy p { color: var(--az-text-secondary); font-size: 1.02rem; margin-bottom: 20px; }

        .az-panel-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 11.5px;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--az-mint-600);
            background: var(--az-mint-100);
            border-radius: 20px;
            padding: 5px 12px;
            margin-bottom: 14px;
        }

        .az-panel-visual {
            background: #FAF9FE;
            border: 1px solid var(--az-border);
            border-radius: var(--az-radius-md);
            padding: 22px;
            min-height: 260px;
        }

        /* Feature mockups (shared bits) */
        .az-fm-row { display: flex; align-items: center; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid var(--az-border); font-size: 13.5px; }
        .az-fm-row:last-child { border-bottom: none; }
        .az-fm-title { font-weight: 700; color: var(--az-ink); }
        .az-fm-sub { color: var(--az-text-tertiary); font-size: 12px; margin-top: 2px; }
        .az-fm-badge { font-size: 11px; font-weight: 700; padding: 4px 10px; border-radius: 20px; white-space: nowrap; }
        .az-fm-badge.ok { background: var(--az-mint-100); color: var(--az-mint-600); }
        .az-fm-badge.warn { background: #FDF0DD; color: #B4761F; }
        .az-fm-badge.late { background: #FBE7E4; color: #C0644B; }
        .az-fm-badge.info { background: var(--az-violet-100); color: var(--az-indigo-700); }

        .az-fm-bars { display: flex; align-items: flex-end; gap: 10px; height: 130px; padding-top: 10px; }
        .az-fm-bar { flex: 1; border-radius: 8px 8px 3px 3px; background: linear-gradient(180deg, var(--az-violet-500), var(--az-indigo-600)); position: relative; }
        .az-fm-bar span { position: absolute; bottom: -22px; left: 0; right: 0; text-align: center; font-size: 11px; color: var(--az-text-tertiary); font-weight: 600; }

        .az-fm-month { display: grid; grid-template-columns: repeat(7, 1fr); gap: 5px; }
        .az-fm-month div { aspect-ratio: 1; border-radius: 7px; background: #F4F3FB; font-size: 10.5px; display: flex; align-items: flex-start; justify-content: flex-end; padding: 3px 5px; color: var(--az-text-tertiary); }
        .az-fm-month div.hl { background: var(--az-indigo-700); color: #fff; font-weight: 700; }
        .az-fm-month div.evt { background: var(--az-mint-100); color: var(--az-mint-600); font-weight: 700; }

        .az-fm-msg { display: flex; gap: 12px; padding: 12px 0; border-bottom: 1px solid var(--az-border); }
        .az-fm-msg:last-child { border-bottom: none; }
        .az-fm-msg i { width: 30px; height: 30px; border-radius: 9px; background: var(--az-violet-100); color: var(--az-indigo-700); display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 12px; }
        .az-fm-msg p { font-weight: 700; font-size: 13.5px; }
        .az-fm-msg span { font-size: 12px; color: var(--az-text-tertiary); }

        .az-attendance-dot { width: 9px; height: 9px; border-radius: 50%; display: inline-block; margin-right: 6px; }
        .az-attendance-dot.present { background: var(--az-mint-500); }
        .az-attendance-dot.absent { background: #D9705B; }
        .az-attendance-dot.excused { background: #E8B65B; }

        .az-ai-panel {
            position: relative;
            border-radius: var(--az-radius-sm);
            overflow: hidden;
            border: 1px dashed var(--az-violet-300);
            padding: 26px;
            text-align: center;
        }

        .az-ai-panel i { font-size: 26px; color: var(--az-violet-500); margin-bottom: 12px; }
        .az-ai-panel p { color: var(--az-text-secondary); font-size: 13.5px; max-width: 320px; margin: 0 auto; }

        @media (max-width: 900px) {
            .az-panel-grid { grid-template-columns: 1fr; padding: 28px; gap: 28px; }
        }

        /* ============================================================
           Roles
           ============================================================ */
        .az-roles { background: var(--az-surface); border-top: 1px solid var(--az-border); border-bottom: 1px solid var(--az-border); }

        .az-roles-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
        }

        .az-role-card {
            border: 1px solid var(--az-border);
            border-radius: var(--az-radius-lg);
            padding: 28px 24px;
            background: var(--az-bg);
            transition: transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease;
        }

        .az-role-card:hover { transform: translateY(-4px); box-shadow: var(--az-shadow-md); border-color: var(--az-violet-300); background: var(--az-surface); }

        .az-role-icon {
            width: 46px;
            height: 46px;
            border-radius: 13px;
            background: var(--az-gradient-brand);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            margin-bottom: 18px;
        }

        .az-role-card h3 { font-size: 1.02rem; margin-bottom: 6px; }
        .az-role-card .az-role-tag { font-size: 13px; font-weight: 700; color: var(--az-indigo-600); margin-bottom: 14px; display: block; }

        .az-role-card ul { list-style: none; display: flex; flex-direction: column; gap: 10px; }
        .az-role-card li { display: flex; align-items: flex-start; gap: 8px; font-size: 13.5px; color: var(--az-text-secondary); }
        .az-role-card li i { color: var(--az-mint-600); font-size: 10px; margin-top: 4px; flex-shrink: 0; }

        @media (max-width: 980px) { .az-roles-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 560px) { .az-roles-grid { grid-template-columns: 1fr; } }

        /* ============================================================
           Implementation steps
           ============================================================ */
        .az-steps-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 24px;
            margin-bottom: 28px;
        }

        .az-step {
            border: 1px solid var(--az-border);
            border-radius: var(--az-radius-lg);
            padding: 30px 26px;
            background: var(--az-surface);
            position: relative;
        }

        .az-step-number {
            font-family: 'Manrope', sans-serif;
            font-size: 2.4rem;
            font-weight: 800;
            background: var(--az-gradient-brand);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 14px;
        }

        .az-step h3 { font-size: 1.1rem; margin-bottom: 10px; }
        .az-step p { color: var(--az-text-secondary); font-size: 14.5px; }

        .az-steps-footnote {
            text-align: center;
            color: var(--az-text-tertiary);
            font-size: 14.5px;
        }

        @media (max-width: 900px) { .az-steps-grid { grid-template-columns: 1fr; } }

        /* ============================================================
           Security
           ============================================================ */
        .az-security { background: var(--az-surface); border-top: 1px solid var(--az-border); border-bottom: 1px solid var(--az-border); }

        .az-security-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }

        .az-security-card {
            border: 1px solid var(--az-border);
            border-radius: var(--az-radius-lg);
            padding: 28px 24px;
            background: var(--az-bg);
        }

        .az-security-card i {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            background: var(--az-violet-100);
            color: var(--az-indigo-700);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 17px;
            margin-bottom: 16px;
        }

        .az-security-card h3 { font-size: 1.02rem; margin-bottom: 8px; }
        .az-security-card p { color: var(--az-text-secondary); font-size: 14px; }

        @media (max-width: 900px) { .az-security-grid { grid-template-columns: 1fr; } }

        /* ============================================================
           Pilot program
           ============================================================ */
        .az-pilot-card {
            background: var(--az-gradient-brand);
            border-radius: var(--az-radius-lg);
            padding: 48px;
            color: #fff;
            display: grid;
            grid-template-columns: 1.3fr 0.7fr;
            gap: 32px;
            align-items: center;
            position: relative;
            overflow: hidden;
        }

        .az-pilot-card::before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(420px 300px at 90% 10%, rgba(255,255,255,0.16), transparent 70%);
        }

        .az-pilot-card h2 { color: #fff; font-size: 1.9rem; margin-bottom: 14px; position: relative; }
        .az-pilot-card p { color: rgba(255,255,255,0.82); font-size: 1.02rem; margin-bottom: 0; position: relative; max-width: 480px; }

        .az-pilot-actions { display: flex; justify-content: flex-end; position: relative; }

        .az-pilot-card .az-btn-secondary { background: #fff; color: var(--az-indigo-700); border-color: transparent; }

        @media (max-width: 900px) {
            .az-pilot-card { grid-template-columns: 1fr; padding: 36px 28px; text-align: left; }
            .az-pilot-actions { justify-content: flex-start; }
        }

        /* ============================================================
           FAQ
           ============================================================ */
        .az-faq-list { display: flex; flex-direction: column; gap: 12px; max-width: 780px; }

        .az-faq-item {
            border: 1px solid var(--az-border);
            border-radius: var(--az-radius-md);
            background: var(--az-surface);
            overflow: hidden;
        }

        .az-faq-item summary {
            list-style: none;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            padding: 18px 22px;
            cursor: pointer;
            font-weight: 700;
            font-size: 15px;
        }

        .az-faq-item summary::-webkit-details-marker { display: none; }

        .az-faq-item summary i {
            transition: transform 0.25s ease;
            color: var(--az-indigo-600);
            flex-shrink: 0;
        }

        .az-faq-item[open] summary i { transform: rotate(180deg); }

        .az-faq-answer-wrap {
            display: grid;
            grid-template-rows: 0fr;
            transition: grid-template-rows 0.28s ease;
        }

        .az-faq-item[open] .az-faq-answer-wrap { grid-template-rows: 1fr; }

        .az-faq-answer {
            overflow: hidden;
            min-height: 0;
        }

        .az-faq-answer p {
            padding: 0 22px 20px;
            color: var(--az-text-secondary);
            font-size: 14.5px;
            max-width: 640px;
        }

        /* ============================================================
           Final CTA
           ============================================================ */
        .az-final-cta {
            background: linear-gradient(160deg, #4B166F 0%, #7A2BB7 56%, #C026D3 100%);
            color: #fff;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .az-final-cta::before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(560px 380px at 15% 0%, rgba(196,85,237,0.34), transparent 70%),
                        radial-gradient(500px 380px at 100% 100%, rgba(236,72,153,0.24), transparent 70%);
        }

        .az-final-cta::after {
            content: '';
            position: absolute;
            width: 320px;
            height: 320px;
            right: -130px;
            bottom: -140px;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.22), transparent 70%);
            pointer-events: none;
        }

        .az-final-cta-mascot {
            position: absolute;
            right: 34px;
            bottom: 22px;
            width: 72px;
            z-index: 2;
            filter: drop-shadow(0 8px 20px rgba(0,0,0,0.35));
            pointer-events: none;
        }

        .az-final-cta h2 { color: #fff; font-size: clamp(1.9rem, 3.6vw, 2.6rem); position: relative; max-width: 700px; margin: 0 auto 16px; }
        .az-final-cta p { color: rgba(255,255,255,0.78); font-size: 1.08rem; max-width: 560px; margin: 0 auto 30px; position: relative; }

        .az-final-cta-actions { display: flex; gap: 14px; justify-content: center; flex-wrap: wrap; position: relative; margin-bottom: 20px; }

        .az-final-cta .az-btn-secondary { background: transparent; color: #fff; border-color: rgba(255,255,255,0.3); }
        .az-final-cta .az-btn-secondary:hover { border-color: #fff; background: rgba(255,255,255,0.06); }

        .az-final-cta-support { color: rgba(255,255,255,0.55); font-size: 13.5px; position: relative; }

        /* ============================================================
           Footer
           ============================================================ */
        .az-footer { padding: 64px 0 32px; border-top: 1px solid var(--az-border); }

        .az-footer-top {
            display: grid;
            grid-template-columns: 1.4fr 1fr 1fr 1fr;
            gap: 32px;
            margin-bottom: 40px;
        }

        .az-footer-brand p { color: var(--az-text-secondary); font-size: 14px; margin-top: 14px; max-width: 260px; }

        .az-footer-col h4 { font-size: 12.5px; text-transform: uppercase; letter-spacing: 0.08em; color: var(--az-text-tertiary); margin-bottom: 16px; }
        .az-footer-col a, .az-footer-col button {
            display: block;
            background: none;
            border: none;
            text-align: left;
            padding: 0;
            cursor: pointer;
            color: var(--az-text-secondary);
            text-decoration: none;
            font-size: 14px;
            margin-bottom: 12px;
            font-family: inherit;
        }

        .az-footer-col a:hover, .az-footer-col button:hover { color: var(--az-indigo-700); }

        .az-footer-bottom {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            padding-top: 24px;
            border-top: 1px solid var(--az-border);
            font-size: 13px;
            color: var(--az-text-tertiary);
            flex-wrap: wrap;
        }

        @media (max-width: 900px) {
            .az-footer-top { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 560px) {
            .az-footer-top { grid-template-columns: 1fr; gap: 28px; }
        }

        /* ============================================================
           Demo modal
           ============================================================ */
        .az-modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(18, 20, 58, 0.55);
            backdrop-filter: blur(4px);
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
            z-index: 200;
        }

        .az-modal-overlay.is-open { display: flex; }

        .az-modal {
            background: var(--az-surface);
            border-radius: var(--az-radius-lg);
            width: 100%;
            max-width: 540px;
            max-height: 90vh;
            overflow-y: auto;
            padding: 36px;
            position: relative;
            box-shadow: var(--az-shadow-lg);
        }

        .az-modal-close {
            position: absolute;
            top: 20px;
            right: 20px;
            width: 36px;
            height: 36px;
            border-radius: 10px;
            border: 1px solid var(--az-border);
            background: var(--az-bg);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--az-text-secondary);
        }

        .az-modal-close:hover { color: var(--az-ink); border-color: var(--az-indigo-500); }

        .az-modal h3 { font-size: 1.4rem; margin-bottom: 6px; }
        .az-modal .az-modal-sub { color: var(--az-text-secondary); font-size: 14px; margin-bottom: 26px; }

        .az-form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .az-form-field { display: flex; flex-direction: column; gap: 6px; margin-bottom: 16px; }
        .az-form-field.full { grid-column: 1 / -1; }

        .az-form-field label { font-size: 13px; font-weight: 700; color: var(--az-ink); }

        .az-form-field input,
        .az-form-field select {
            border: 1px solid var(--az-border);
            border-radius: 11px;
            padding: 11px 14px;
            font-size: 14.5px;
            font-family: inherit;
            color: var(--az-ink);
            background: var(--az-bg);
            transition: border-color 0.2s ease;
        }

        .az-form-field input:focus,
        .az-form-field select:focus { border-color: var(--az-indigo-500); outline: none; }

        .az-form-field.has-error input,
        .az-form-field.has-error select { border-color: #C0644B; }

        .az-form-error { font-size: 12.5px; color: #C0644B; min-height: 15px; }

        .az-form-status {
            display: none;
            border-radius: 12px;
            padding: 12px 16px;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 18px;
        }

        .az-form-status.is-visible { display: block; }
        .az-form-status.success { background: var(--az-mint-100); color: var(--az-mint-600); }
        .az-form-status.error { background: #FBE7E4; color: #C0644B; }

        @media (max-width: 560px) {
            .az-form-grid { grid-template-columns: 1fr; }
        }

        /* ============================================================
           Reveal on scroll
           ============================================================ */
        [data-reveal] {
            opacity: 0;
            transform: translateY(18px);
            transition: opacity 0.6s ease, transform 0.6s ease;
        }

        [data-reveal].is-visible { opacity: 1; transform: translateY(0); }

        @media (prefers-reduced-motion: reduce) {
            [data-reveal] { opacity: 1; transform: none; transition: none; }
        }

        /* ============================================================
           Responsive base
           ============================================================ */
        @media (max-width: 640px) {
            section { padding: 60px 0; }
            .az-hero { padding-top: 40px; }
            .az-hero-mascot,
            .az-trust-mascot,
            .az-final-cta-mascot { display: none; }
        }
    </style>
</head>
<body>

    <!-- ============================================================
         NAVBAR
         ============================================================ -->
    <header>
        <nav class="az-nav" id="az-nav" aria-label="Navegación principal">
            <div class="az-container az-nav-inner">
                <a href="{{ route('welcome') }}" class="az-logo" aria-label="AulaSync — Inicio">
                    <span class="az-logo-mark" aria-hidden="true">
                        <img src="/images/emoji leyendo sin fondo.png" alt="">
                    </span>
                    <span class="az-logo-text">AulaSync</span>
                </a>

                <ul class="az-nav-links">
                    <li><a href="#funciones">Funciones</a></li>
                    <li><a href="#roles">Para colegios</a></li>
                    <li><a href="#implementacion">Recursos</a></li>
                    <li><a href="#faq">Preguntas frecuentes</a></li>
                </ul>

                <div class="az-nav-actions">
                    <a href="{{ route('login') }}" class="az-btn az-btn-secondary az-btn-sm">Iniciar sesión</a>
                    <button type="button" class="az-btn az-btn-primary az-btn-sm" data-open-demo>Agendar demo</button>
                </div>

                <button type="button" class="az-burger" id="az-burger" aria-expanded="false" aria-controls="az-mobile-menu" aria-label="Abrir menú de navegación">
                    <span></span>
                </button>
            </div>

            <div class="az-mobile-menu" id="az-mobile-menu">
                <a href="#funciones">Funciones</a>
                <a href="#roles">Para colegios</a>
                <a href="#implementacion">Recursos</a>
                <a href="#faq">Preguntas frecuentes</a>
                <div class="az-nav-actions">
                    <a href="{{ route('login') }}" class="az-btn az-btn-secondary">Iniciar sesión</a>
                    <button type="button" class="az-btn az-btn-primary" data-open-demo>Agendar demo</button>
                </div>
            </div>
        </nav>
    </header>

    <main>
        <!-- ============================================================
             HERO
             ============================================================ -->
        <section class="az-hero">
            <div class="az-hero-bg" aria-hidden="true"></div>
            <div class="az-container az-hero-inner">
                <div class="az-hero-copy">
                    <span class="az-eyebrow"><i class="fa-solid fa-circle-nodes"></i> Gestión académica, sincronizada</span>
                    <h1 class="az-hero-title">La plataforma que sincroniza a todo tu colegio.</h1>
                    <p class="az-hero-text">
                        Centraliza la planificación docente, las tareas, las calificaciones, la asistencia y la
                        comunicación con las familias en un solo lugar.
                    </p>
                    <div class="az-hero-actions">
                        <button type="button" class="az-btn az-btn-primary" data-open-demo>
                            <i class="fa-regular fa-calendar-check"></i> Solicitar una demo
                        </button>
                        <a href="#funciones" class="az-btn az-btn-secondary">
                            <i class="fa-regular fa-compass"></i> Explorar funciones
                        </a>
                    </div>
                    <p class="az-hero-trust"><i class="fa-solid fa-check"></i> Diseñada para simplificar la gestión de colegios en Venezuela.</p>
                </div>

                <div class="az-hero-visual" data-reveal>
                    <div class="az-dashboard-mock" role="img" aria-label="Vista ilustrativa del panel de AulaSync con clases, tareas, asistencia, calendario y un comunicado. Datos de ejemplo.">
                        <div class="az-mock-topbar">
                            <span class="az-mock-dot a"></span>
                            <span class="az-mock-dot b"></span>
                            <span class="az-mock-dot c"></span>
                            <span>Panel de AulaSync · Colegio Demo</span>
                        </div>
                        <div class="az-mock-body">
                            <div class="az-mock-sidebar" aria-hidden="true">
                                <i class="fa-solid fa-house active"></i>
                                <i class="fa-solid fa-book-open"></i>
                                <i class="fa-solid fa-list-check"></i>
                                <i class="fa-solid fa-chart-simple"></i>
                                <i class="fa-solid fa-calendar-days"></i>
                                <i class="fa-solid fa-comments"></i>
                            </div>
                            <div class="az-mock-main">
                                <div class="az-mock-row">
                                    <div class="az-mock-card">
                                        <h4>Próximas evaluaciones</h4>
                                        <div class="az-mock-line"><span>Matemática · 3° B</span><span class="az-mock-tag violet">Jue</span></div>
                                        <div class="az-mock-line"><span>Ciencias · 4° A</span><span class="az-mock-tag violet">Vie</span></div>
                                    </div>
                                    <div class="az-mock-card">
                                        <h4>Tareas pendientes</h4>
                                        <div class="az-mock-line"><span>Ensayo de Lengua</span><span class="az-mock-tag amber">3 días</span></div>
                                        <div class="az-mock-line"><span>Guía de Ciencias</span><span class="az-mock-tag mint">Entregada</span></div>
                                    </div>
                                </div>
                                <div class="az-mock-card">
                                    <h4>Asistencia de la semana</h4>
                                    <div class="az-mock-week">
                                        <div class="az-mock-week-day">Lun</div>
                                        <div class="az-mock-week-day">Mar</div>
                                        <div class="az-mock-week-day">Mié</div>
                                        <div class="az-mock-week-day today">Jue</div>
                                        <div class="az-mock-week-day">Vie</div>
                                    </div>
                                </div>
                                <div class="az-mock-announcement">
                                    <i class="fa-regular fa-bell" aria-hidden="true"></i>
                                    <div>
                                        <p>Reunión de representantes · 3° grado</p>
                                        <span>Enviado a 42 familias · Hoy, 10:00 a.m.</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="az-floating-chip chip-1 mint">
                        <i class="fa-solid fa-square-check"></i> 12 tareas entregadas
                    </div>
                    <div class="az-floating-chip chip-2 indigo">
                        <i class="fa-solid fa-user-check"></i> Asistencia 96%
                    </div>
                    <div class="az-floating-chip chip-3 violet">
                        <i class="fa-solid fa-file-pen"></i> 3 evaluaciones esta semana
                    </div>
                    <img class="az-hero-mascot" src="/images/emoji viendo fijo sin fondo.png" alt="" aria-hidden="true">
                </div>
            </div>
        </section>

        <!-- ============================================================
             TRUST BAND
             ============================================================ -->
        <section class="az-trustband">
            <div class="az-container az-section-head center" data-reveal>
                <span class="az-eyebrow"><i class="fa-regular fa-lightbulb"></i> Etapa temprana</span>
                <h2 class="az-section-title">Creada para los retos reales de la gestión escolar.</h2>
                <p class="az-section-text">Una experiencia más clara para dirección, docentes, estudiantes y representantes.</p>
            </div>
            <div class="az-container">
                <div class="az-chip-grid" data-reveal>
                    <div class="az-chip"><i class="fa-solid fa-layer-group"></i><span>Planificación centralizada</span></div>
                    <div class="az-chip"><i class="fa-solid fa-comments"></i><span>Comunicación organizada</span></div>
                    <div class="az-chip"><i class="fa-solid fa-database"></i><span>Información siempre disponible</span></div>
                    <div class="az-chip"><i class="fa-solid fa-feather"></i><span>Menos carga administrativa</span></div>
                    <img class="az-trust-mascot" src="/images/emoji leyendo sin fondo.png" alt="" aria-hidden="true">
                </div>
            </div>
        </section>

        <!-- ============================================================
             PROBLEM / SOLUTION
             ============================================================ -->
        <section class="az-problem">
            <div class="az-container">
                <div class="az-section-head center" data-reveal>
                    <span class="az-eyebrow"><i class="fa-solid fa-puzzle-piece"></i> El problema</span>
                    <h2 class="az-section-title">Cuando todo está disperso, enseñar se vuelve más difícil.</h2>
                    <p class="az-section-text">
                        Mensajes perdidos, tareas por distintos canales, notas en hojas de cálculo y calendarios
                        desactualizados hacen que la operación escolar pierda tiempo y claridad.
                    </p>
                </div>

                <div class="az-ps-grid" data-reveal>
                    <div class="az-ps-col az-ps-col-neg">
                        <h3><i class="fa-solid fa-xmark"></i> Sin AulaSync</h3>
                        <ul class="az-ps-list">
                            <li><i class="fa-solid fa-xmark"></i> Tareas dispersas entre WhatsApp, cuadernos y correos.</li>
                            <li><i class="fa-solid fa-xmark"></i> Calificaciones y asistencia registradas manualmente.</li>
                            <li><i class="fa-solid fa-xmark"></i> Representantes con información incompleta.</li>
                            <li><i class="fa-solid fa-xmark"></i> Coordinación académica con poca visibilidad.</li>
                        </ul>
                    </div>
                    <div class="az-ps-col az-ps-col-pos">
                        <h3><i class="fa-solid fa-check"></i> Con AulaSync</h3>
                        <ul class="az-ps-list">
                            <li><i class="fa-solid fa-check"></i> Un espacio único para cada proceso académico.</li>
                            <li><i class="fa-solid fa-check"></i> Información actualizada para cada rol.</li>
                            <li><i class="fa-solid fa-check"></i> Procesos claros para docentes y coordinación.</li>
                            <li><i class="fa-solid fa-check"></i> Familias más informadas y conectadas.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </section>

        <!-- ============================================================
             FEATURES / PRODUCT (tabs)
             ============================================================ -->
        <section id="funciones">
            <div class="az-container">
                <div class="az-section-head center" data-reveal>
                    <span class="az-eyebrow"><i class="fa-solid fa-layer-group"></i> Producto</span>
                    <h2 class="az-section-title">Todo lo que tu colegio necesita para avanzar sincronizado.</h2>
                    <p class="az-section-text">
                        Desde la planificación diaria hasta la comunicación con las familias, AulaSync reúne los
                        procesos académicos esenciales en una experiencia simple.
                    </p>
                </div>

                <div role="tablist" aria-label="Funciones de AulaSync" class="az-tabs" id="az-feature-tabs">
                    <button class="az-tab-btn" role="tab" id="tab-plan" aria-controls="panel-plan" aria-selected="true" data-tab="plan">
                        <i class="fa-solid fa-clipboard-list"></i> Planificación
                    </button>
                    <button class="az-tab-btn" role="tab" id="tab-tasks" aria-controls="panel-tasks" aria-selected="false" tabindex="-1" data-tab="tasks">
                        <i class="fa-solid fa-list-check"></i> Tareas y evaluaciones
                    </button>
                    <button class="az-tab-btn" role="tab" id="tab-grades" aria-controls="panel-grades" aria-selected="false" tabindex="-1" data-tab="grades">
                        <i class="fa-solid fa-chart-simple"></i> Calificaciones
                    </button>
                    <button class="az-tab-btn" role="tab" id="tab-attendance" aria-controls="panel-attendance" aria-selected="false" tabindex="-1" data-tab="attendance">
                        <i class="fa-solid fa-user-check"></i> Asistencia
                    </button>
                    <button class="az-tab-btn" role="tab" id="tab-calendar" aria-controls="panel-calendar" aria-selected="false" tabindex="-1" data-tab="calendar">
                        <i class="fa-solid fa-calendar-days"></i> Calendario
                    </button>
                    <button class="az-tab-btn" role="tab" id="tab-comms" aria-controls="panel-comms" aria-selected="false" tabindex="-1" data-tab="comms">
                        <i class="fa-solid fa-comments"></i> Comunicación
                    </button>
                    <button class="az-tab-btn" role="tab" id="tab-ai" aria-controls="panel-ai" aria-selected="false" tabindex="-1" data-tab="ai">
                        <span class="az-soon-dot" aria-hidden="true"></span> IA educativa
                    </button>
                </div>

                <!-- Panel: Planificación docente -->
                <div class="az-tab-panel is-active" role="tabpanel" id="panel-plan" aria-labelledby="tab-plan">
                    <div class="az-panel-grid">
                        <div class="az-panel-copy">
                            <h3>Planifica mejor. Enseña con más claridad.</h3>
                            <p>Crea planificaciones por materia, sección y período académico, con objetivos, actividades y recursos organizados.</p>
                            <a href="#roles" class="az-btn-ghost">Conocer esta función <i class="fa-solid fa-arrow-right"></i></a>
                        </div>
                        <div class="az-panel-visual">
                            <div class="az-fm-row"><span class="az-fm-title">Matemática · 3° B<br><span class="az-fm-sub">Objetivo: resolver ecuaciones simples</span></span><span class="az-fm-badge info">Semana 12</span></div>
                            <div class="az-fm-row"><span class="az-fm-title">Ciencias · 4° A<br><span class="az-fm-sub">Recursos: guía impresa + laboratorio</span></span><span class="az-fm-badge info">Semana 12</span></div>
                            <div class="az-fm-row"><span class="az-fm-title">Lengua · 5° C<br><span class="az-fm-sub">Actividad: análisis de texto narrativo</span></span><span class="az-fm-badge ok">Aprobada</span></div>
                        </div>
                    </div>
                </div>

                <!-- Panel: Tareas y evaluaciones -->
                <div class="az-tab-panel" role="tabpanel" id="panel-tasks" aria-labelledby="tab-tasks" hidden>
                    <div class="az-panel-grid">
                        <div class="az-panel-copy">
                            <h3>Tareas y evaluaciones sin confusión.</h3>
                            <p>Publica actividades, fechas de entrega, rúbricas y evaluaciones para que cada estudiante sepa exactamente qué hacer.</p>
                            <a href="#roles" class="az-btn-ghost">Conocer esta función <i class="fa-solid fa-arrow-right"></i></a>
                        </div>
                        <div class="az-panel-visual">
                            <div class="az-fm-row"><span class="az-fm-title">Ensayo de Lengua<br><span class="az-fm-sub">Entrega: 18 de agosto</span></span><span class="az-fm-badge warn">Pendiente</span></div>
                            <div class="az-fm-row"><span class="az-fm-title">Guía de Ciencias<br><span class="az-fm-sub">Entregada el 12 de agosto</span></span><span class="az-fm-badge ok">Entregada</span></div>
                            <div class="az-fm-row"><span class="az-fm-title">Proyecto de Sociales<br><span class="az-fm-sub">Entrega: 8 de agosto</span></span><span class="az-fm-badge late">Vencida</span></div>
                        </div>
                    </div>
                </div>

                <!-- Panel: Calificaciones -->
                <div class="az-tab-panel" role="tabpanel" id="panel-grades" aria-labelledby="tab-grades" hidden>
                    <div class="az-panel-grid">
                        <div class="az-panel-copy">
                            <h3>Notas claras para docentes, estudiantes y familias.</h3>
                            <p>Registra, consulta y comparte calificaciones de forma ordenada, reduciendo procesos manuales.</p>
                            <a href="#roles" class="az-btn-ghost">Conocer esta función <i class="fa-solid fa-arrow-right"></i></a>
                        </div>
                        <div class="az-panel-visual">
                            <div class="az-fm-bars">
                                <div class="az-fm-bar" style="height:64%"><span>Lapso 1</span></div>
                                <div class="az-fm-bar" style="height:78%"><span>Lapso 2</span></div>
                                <div class="az-fm-bar" style="height:71%"><span>Lapso 3</span></div>
                                <div class="az-fm-bar" style="height:85%"><span>Actual</span></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Panel: Asistencia -->
                <div class="az-tab-panel" role="tabpanel" id="panel-attendance" aria-labelledby="tab-attendance" hidden>
                    <div class="az-panel-grid">
                        <div class="az-panel-copy">
                            <h3>Asistencia registrada en minutos.</h3>
                            <p>Lleva el control de asistencia por clase y consulta tendencias para tomar decisiones a tiempo.</p>
                            <a href="#roles" class="az-btn-ghost">Conocer esta función <i class="fa-solid fa-arrow-right"></i></a>
                        </div>
                        <div class="az-panel-visual">
                            <div class="az-fm-row"><span><span class="az-attendance-dot present"></span>María G.</span><span class="az-fm-badge ok">Presente</span></div>
                            <div class="az-fm-row"><span><span class="az-attendance-dot absent"></span>Carlos R.</span><span class="az-fm-badge late">Ausente</span></div>
                            <div class="az-fm-row"><span><span class="az-attendance-dot excused"></span>Ana P.</span><span class="az-fm-badge warn">Justificado</span></div>
                            <div class="az-fm-row"><span><span class="az-attendance-dot present"></span>José M.</span><span class="az-fm-badge ok">Presente</span></div>
                        </div>
                    </div>
                </div>

                <!-- Panel: Calendario escolar -->
                <div class="az-tab-panel" role="tabpanel" id="panel-calendar" aria-labelledby="tab-calendar" hidden>
                    <div class="az-panel-grid">
                        <div class="az-panel-copy">
                            <h3>Todo el calendario académico en un solo lugar.</h3>
                            <p>Clases, reuniones, evaluaciones, entregas y eventos importantes visibles para toda la comunidad.</p>
                            <a href="#roles" class="az-btn-ghost">Conocer esta función <i class="fa-solid fa-arrow-right"></i></a>
                        </div>
                        <div class="az-panel-visual">
                            <div class="az-fm-month" aria-hidden="true">
                                <div>1</div><div>2</div><div>3</div><div class="evt">4</div><div>5</div><div>6</div><div>7</div>
                                <div>8</div><div>9</div><div class="hl">10</div><div>11</div><div>12</div><div>13</div><div>14</div>
                                <div>15</div><div class="evt">16</div><div>17</div><div>18</div><div>19</div><div>20</div><div>21</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Panel: Comunicación -->
                <div class="az-tab-panel" role="tabpanel" id="panel-comms" aria-labelledby="tab-comms" hidden>
                    <div class="az-panel-grid">
                        <div class="az-panel-copy">
                            <h3>Comunicación que llega a quien la necesita.</h3>
                            <p>Envía comunicados segmentados a docentes, estudiantes o representantes y mantén cada novedad organizada.</p>
                            <a href="#roles" class="az-btn-ghost">Conocer esta función <i class="fa-solid fa-arrow-right"></i></a>
                        </div>
                        <div class="az-panel-visual">
                            <div class="az-fm-msg"><i class="fa-regular fa-bell"></i><div><p>Reunión de representantes · 3° grado</p><span>Leído por 38 de 42 familias</span></div></div>
                            <div class="az-fm-msg"><i class="fa-regular fa-bell"></i><div><p>Cambio de horario · Educación Física</p><span>Enviado a docentes de 4° y 5°</span></div></div>
                            <div class="az-fm-msg"><i class="fa-regular fa-bell"></i><div><p>Recordatorio de evaluación</p><span>Enviado a estudiantes de 3° B</span></div></div>
                        </div>
                    </div>
                </div>

                <!-- Panel: IA educativa (próximamente) -->
                <div class="az-tab-panel" role="tabpanel" id="panel-ai" aria-labelledby="tab-ai" hidden>
                    <div class="az-panel-grid">
                        <div class="az-panel-copy">
                            <span class="az-panel-badge"><i class="fa-solid fa-clock"></i> Próximamente</span>
                            <h3>IA que apoya el trabajo docente.</h3>
                            <p>Próximamente: herramientas para ayudar a estructurar planificaciones, crear borradores de actividades y ahorrar tiempo administrativo.</p>
                        </div>
                        <div class="az-panel-visual">
                            <div class="az-ai-panel">
                                <i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i>
                                <p>Estamos construyendo esta función. Pronto podrás generar borradores de planificación asistidos por IA directamente desde tu panel.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ============================================================
             ROLES
             ============================================================ -->
        <section class="az-roles" id="roles">
            <div class="az-container">
                <div class="az-section-head center" data-reveal>
                    <span class="az-eyebrow"><i class="fa-solid fa-people-group"></i> Para cada rol</span>
                    <h2 class="az-section-title">Una experiencia clara para cada persona del colegio.</h2>
                </div>

                <div class="az-roles-grid" data-reveal>
                    <div class="az-role-card">
                        <div class="az-role-icon"><i class="fa-solid fa-user-tie"></i></div>
                        <span class="az-role-tag">Dirección y coordinación</span>
                        <h3>Visibilidad para tomar mejores decisiones.</h3>
                        <ul>
                            <li><i class="fa-solid fa-circle"></i> Consulta información académica relevante.</li>
                            <li><i class="fa-solid fa-circle"></i> Identifica pendientes y seguimiento.</li>
                            <li><i class="fa-solid fa-circle"></i> Organiza períodos, secciones y procesos.</li>
                        </ul>
                    </div>
                    <div class="az-role-card">
                        <div class="az-role-icon"><i class="fa-solid fa-chalkboard-user"></i></div>
                        <span class="az-role-tag">Docentes</span>
                        <h3>Menos administración. Más tiempo para enseñar.</h3>
                        <ul>
                            <li><i class="fa-solid fa-circle"></i> Planificaciones, tareas, asistencia y calificaciones.</li>
                            <li><i class="fa-solid fa-circle"></i> Organización por clases y períodos.</li>
                            <li><i class="fa-solid fa-circle"></i> Comunicación más clara con estudiantes.</li>
                        </ul>
                    </div>
                    <div class="az-role-card">
                        <div class="az-role-icon"><i class="fa-solid fa-people-roof"></i></div>
                        <span class="az-role-tag">Representantes</span>
                        <h3>Más cerca del proceso académico.</h3>
                        <ul>
                            <li><i class="fa-solid fa-circle"></i> Consulta tareas, comunicados y avances.</li>
                            <li><i class="fa-solid fa-circle"></i> Mayor claridad sobre fechas importantes.</li>
                            <li><i class="fa-solid fa-circle"></i> Menos dependencia de mensajes dispersos.</li>
                        </ul>
                    </div>
                    <div class="az-role-card">
                        <div class="az-role-icon"><i class="fa-solid fa-graduation-cap"></i></div>
                        <span class="az-role-tag">Estudiantes</span>
                        <h3>Todo lo importante, en el momento correcto.</h3>
                        <ul>
                            <li><i class="fa-solid fa-circle"></i> Tareas, evaluaciones y calendario.</li>
                            <li><i class="fa-solid fa-circle"></i> Mayor autonomía y organización.</li>
                            <li><i class="fa-solid fa-circle"></i> Información académica centralizada.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </section>

        <!-- ============================================================
             IMPLEMENTATION STEPS
             ============================================================ -->
        <section id="implementacion">
            <div class="az-container">
                <div class="az-section-head center" data-reveal>
                    <span class="az-eyebrow"><i class="fa-solid fa-map-signs"></i> Adopción</span>
                    <h2 class="az-section-title">Empieza sin complicar a tu equipo.</h2>
                </div>

                <div class="az-steps-grid" data-reveal>
                    <div class="az-step">
                        <div class="az-step-number">01</div>
                        <h3>Conocemos tu colegio</h3>
                        <p>Entendemos la estructura académica y las necesidades de tu equipo.</p>
                    </div>
                    <div class="az-step">
                        <div class="az-step-number">02</div>
                        <h3>Configuramos AulaSync</h3>
                        <p>Preparamos períodos, secciones, materias, usuarios y permisos.</p>
                    </div>
                    <div class="az-step">
                        <div class="az-step-number">03</div>
                        <h3>Tu comunidad empieza a sincronizarse</h3>
                        <p>Docentes, estudiantes y representantes acceden a una experiencia más ordenada.</p>
                    </div>
                </div>

                <p class="az-steps-footnote">Acompañamiento pensado para una adopción gradual y práctica.</p>
            </div>
        </section>

        <!-- ============================================================
             SECURITY & TRUST
             ============================================================ -->
        <section class="az-security" id="seguridad">
            <div class="az-container">
                <div class="az-section-head center" data-reveal>
                    <span class="az-eyebrow"><i class="fa-solid fa-shield-halved"></i> Confianza</span>
                    <h2 class="az-section-title">La información académica merece estar protegida.</h2>
                </div>

                <div class="az-security-grid" data-reveal>
                    <div class="az-security-card">
                        <i class="fa-solid fa-user-lock"></i>
                        <h3>Roles y permisos</h3>
                        <p>Cada usuario accede únicamente a la información que necesita.</p>
                    </div>
                    <div class="az-security-card">
                        <i class="fa-solid fa-database"></i>
                        <h3>Datos centralizados</h3>
                        <p>Evita que información importante se pierda en múltiples canales.</p>
                    </div>
                    <div class="az-security-card">
                        <i class="fa-solid fa-mobile-screen"></i>
                        <h3>Acceso desde cualquier dispositivo</h3>
                        <p>Consulta la información desde web y móvil según las capacidades del producto.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- ============================================================
             PILOT PROGRAM (replaces testimonials for now)
             ============================================================ -->
        <section id="piloto">
            <div class="az-container">
                <div class="az-pilot-card" data-reveal>
                    <div>
                        <h2>Forma parte de los primeros colegios en construir AulaSync.</h2>
                        <p>Estamos buscando instituciones educativas que quieran probar la plataforma, aportar feedback y organizar su gestión académica.</p>
                    </div>
                    <div class="az-pilot-actions">
                        <button type="button" class="az-btn az-btn-secondary" data-open-demo>
                            Quiero conocer el programa piloto
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <!-- ============================================================
             FAQ
             ============================================================ -->
        <section id="faq">
            <div class="az-container">
                <div class="az-section-head center" data-reveal>
                    <span class="az-eyebrow"><i class="fa-regular fa-circle-question"></i> Dudas frecuentes</span>
                    <h2 class="az-section-title">Preguntas frecuentes</h2>
                </div>

                <div class="az-faq-list" data-reveal>
                    <details class="az-faq-item">
                        <summary>¿Qué es AulaSync?<i class="fa-solid fa-chevron-down" aria-hidden="true"></i></summary>
                        <div class="az-faq-answer-wrap"><div class="az-faq-answer"><p>AulaSync es una plataforma de gestión académica que centraliza la planificación docente, las tareas, las calificaciones, la asistencia, el calendario y la comunicación escolar en un solo lugar.</p></div></div>
                    </details>
                    <details class="az-faq-item">
                        <summary>¿A quién está dirigida la plataforma?<i class="fa-solid fa-chevron-down" aria-hidden="true"></i></summary>
                        <div class="az-faq-answer-wrap"><div class="az-faq-answer"><p>Está pensada para colegios privados, especialmente para equipos directivos, coordinadores académicos y docentes que buscan reducir la carga administrativa.</p></div></div>
                    </details>
                    <details class="az-faq-item">
                        <summary>¿Pueden usarla docentes, estudiantes y representantes?<i class="fa-solid fa-chevron-down" aria-hidden="true"></i></summary>
                        <div class="az-faq-answer-wrap"><div class="az-faq-answer"><p>Sí. Cada rol accede a una vista adaptada a sus necesidades: dirección y coordinación, docentes, representantes y estudiantes.</p></div></div>
                    </details>
                    <details class="az-faq-item">
                        <summary>¿Qué procesos académicos puedo gestionar?<i class="fa-solid fa-chevron-down" aria-hidden="true"></i></summary>
                        <div class="az-faq-answer-wrap"><div class="az-faq-answer"><p>Planificación por materia y período, tareas y evaluaciones, calificaciones, asistencia, calendario académico y comunicación con la comunidad educativa.</p></div></div>
                    </details>
                    <details class="az-faq-item">
                        <summary>¿AulaSync funciona desde el teléfono?<i class="fa-solid fa-chevron-down" aria-hidden="true"></i></summary>
                        <div class="az-faq-answer-wrap"><div class="az-faq-answer"><p>Sí, puedes acceder desde el navegador de tu teléfono o computadora, según las capacidades actuales del producto.</p></div></div>
                    </details>
                    <details class="az-faq-item">
                        <summary>¿Cómo solicito una demostración?<i class="fa-solid fa-chevron-down" aria-hidden="true"></i></summary>
                        <div class="az-faq-answer-wrap"><div class="az-faq-answer"><p>Haz clic en "Solicitar una demo" en cualquier parte de esta página, completa el formulario y nuestro equipo se pondrá en contacto contigo.</p></div></div>
                    </details>
                    <details class="az-faq-item">
                        <summary>¿Puedo implementar AulaSync de forma gradual?<i class="fa-solid fa-chevron-down" aria-hidden="true"></i></summary>
                        <div class="az-faq-answer-wrap"><div class="az-faq-answer"><p>Sí. Acompañamos la configuración inicial y la adopción se puede hacer por secciones, materias o etapas, según el ritmo de tu equipo.</p></div></div>
                    </details>
                    <details class="az-faq-item">
                        <summary>¿Qué necesito para comenzar?<i class="fa-solid fa-chevron-down" aria-hidden="true"></i></summary>
                        <div class="az-faq-answer-wrap"><div class="az-faq-answer"><p>Basta con solicitar una demo. A partir de ahí te acompañamos para conocer la estructura de tu colegio y configurar la plataforma.</p></div></div>
                    </details>
                </div>
            </div>
        </section>

        <!-- ============================================================
             FINAL CTA
             ============================================================ -->
        <section class="az-final-cta">
            <div class="az-container" data-reveal>
                <h2>Haz que la gestión académica deje de ser un obstáculo.</h2>
                <p>Conecta a tu comunidad educativa y centraliza lo importante con AulaSync.</p>
                <div class="az-final-cta-actions">
                    <button type="button" class="az-btn az-btn-primary" data-open-demo>Solicitar una demo</button>
                    <button type="button" class="az-btn az-btn-secondary" data-open-demo>Conocer el programa piloto</button>
                </div>
                <p class="az-final-cta-support">Una plataforma creada para colegios que quieren operar con más claridad.</p>
                <img class="az-final-cta-mascot" src="/images/emoji viendo fijo sin fondo.png" alt="" aria-hidden="true">
            </div>
        </section>
    </main>

    <!-- ============================================================
         FOOTER
         ============================================================ -->
    <footer class="az-footer">
        <div class="az-container">
            <div class="az-footer-top">
                <div class="az-footer-brand">
                    <a href="{{ route('welcome') }}" class="az-logo" aria-label="AulaSync — Inicio">
                        <span class="az-logo-mark" aria-hidden="true">
                            <img src="/images/emoji leyendo sin fondo.png" alt="">
                        </span>
                        <span class="az-logo-text">AulaSync</span>
                    </a>
                    <p>Gestión académica más clara, comunidades más conectadas.</p>
                </div>
                <div class="az-footer-col">
                    <h4>Producto</h4>
                    <a href="#funciones">Funciones</a>
                    <a href="#roles">Para colegios</a>
                    <button type="button" data-open-demo>Solicitar demo</button>
                </div>
                <div class="az-footer-col">
                    <h4>Recursos</h4>
                    <a href="#faq">Preguntas frecuentes</a>
                    <a href="#seguridad">Seguridad y confianza</a>
                </div>
                <div class="az-footer-col">
                    <h4>Empresa</h4>
                    <a href="#piloto">Nosotros</a>
                    <button type="button" data-open-demo>Contacto</button>
                </div>
            </div>

            <div class="az-footer-bottom">
                <span>&copy; {{ date('Y') }} AulaSync. Todos los derechos reservados.</span>
                <div style="display:flex; gap: 20px;">
                    <a href="{{ route('legal.privacidad') }}">Privacidad</a>
                    <a href="{{ route('legal.terminos') }}">Términos</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- ============================================================
         DEMO REQUEST MODAL
         ============================================================ -->
    <div class="az-modal-overlay" id="az-demo-modal" role="dialog" aria-modal="true" aria-labelledby="az-demo-title" hidden>
        <div class="az-modal">
            <button type="button" class="az-modal-close" id="az-demo-close" aria-label="Cerrar formulario">
                <i class="fa-solid fa-xmark"></i>
            </button>
            <h3 id="az-demo-title">Solicita una demo</h3>
            <p class="az-modal-sub">Cuéntanos sobre tu colegio y te contactaremos para coordinar una demostración.</p>

            <div class="az-form-status" id="az-demo-status" role="status"></div>

            <form id="az-demo-form" novalidate>
                @csrf
                <div class="az-form-grid">
                    <div class="az-form-field full" data-field="name">
                        <label for="demo-name">Nombre completo</label>
                        <input type="text" id="demo-name" name="name" autocomplete="name" required>
                        <span class="az-form-error"></span>
                    </div>
                    <div class="az-form-field full" data-field="school_name">
                        <label for="demo-school">Nombre del colegio</label>
                        <input type="text" id="demo-school" name="school_name" autocomplete="organization" required>
                        <span class="az-form-error"></span>
                    </div>
                    <div class="az-form-field" data-field="role">
                        <label for="demo-role">Cargo</label>
                        <select id="demo-role" name="role" required>
                            <option value="">Selecciona…</option>
                            <option value="Director/a">Director/a</option>
                            <option value="Coordinador/a académico">Coordinador/a académico</option>
                            <option value="Docente">Docente</option>
                            <option value="Representante">Representante</option>
                            <option value="Otro">Otro</option>
                        </select>
                        <span class="az-form-error"></span>
                    </div>
                    <div class="az-form-field" data-field="school_size">
                        <label for="demo-size">Tamaño aproximado del colegio</label>
                        <select id="demo-size" name="school_size">
                            <option value="">Selecciona…</option>
                            <option value="Menos de 100 estudiantes">Menos de 100 estudiantes</option>
                            <option value="100 - 300 estudiantes">100 - 300 estudiantes</option>
                            <option value="300 - 600 estudiantes">300 - 600 estudiantes</option>
                            <option value="Más de 600 estudiantes">Más de 600 estudiantes</option>
                        </select>
                        <span class="az-form-error"></span>
                    </div>
                    <div class="az-form-field" data-field="email">
                        <label for="demo-email">Correo electrónico</label>
                        <input type="email" id="demo-email" name="email" autocomplete="email" required>
                        <span class="az-form-error"></span>
                    </div>
                    <div class="az-form-field" data-field="phone">
                        <label for="demo-phone">Teléfono / WhatsApp (opcional)</label>
                        <input type="tel" id="demo-phone" name="phone" autocomplete="tel">
                        <span class="az-form-error"></span>
                    </div>
                </div>

                <button type="submit" class="az-btn az-btn-primary az-btn-block" id="az-demo-submit">
                    Enviar solicitud
                </button>
            </form>
        </div>
    </div>

    <script>
        (function () {
            'use strict';

            /* ── Sticky nav blur on scroll ─────────────────────────── */
            var nav = document.getElementById('az-nav');
            var onScroll = function () {
                if (window.scrollY > 12) nav.classList.add('is-scrolled');
                else nav.classList.remove('is-scrolled');
            };
            window.addEventListener('scroll', onScroll, { passive: true });
            onScroll();

            /* ── Mobile menu ────────────────────────────────────────── */
            var burger = document.getElementById('az-burger');
            var mobileMenu = document.getElementById('az-mobile-menu');
            burger.addEventListener('click', function () {
                var isOpen = mobileMenu.classList.toggle('is-open');
                burger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            });
            mobileMenu.querySelectorAll('a').forEach(function (link) {
                link.addEventListener('click', function () {
                    mobileMenu.classList.remove('is-open');
                    burger.setAttribute('aria-expanded', 'false');
                });
            });

            /* ── Feature tabs (roving tabindex, ARIA) ─────────────────── */
            var tabList = document.getElementById('az-feature-tabs');
            var tabs = Array.prototype.slice.call(tabList.querySelectorAll('.az-tab-btn'));
            var panels = Array.prototype.slice.call(document.querySelectorAll('.az-tab-panel'));

            function activateTab(tab) {
                tabs.forEach(function (t) {
                    var selected = t === tab;
                    t.setAttribute('aria-selected', selected ? 'true' : 'false');
                    t.tabIndex = selected ? 0 : -1;
                });
                panels.forEach(function (panel) {
                    var match = panel.id === 'panel-' + tab.dataset.tab;
                    panel.classList.toggle('is-active', match);
                    if (match) panel.removeAttribute('hidden');
                    else panel.setAttribute('hidden', '');
                });
                tab.focus();
            }

            tabs.forEach(function (tab, idx) {
                tab.addEventListener('click', function () { activateTab(tab); });
                tab.addEventListener('keydown', function (e) {
                    var next = null;
                    if (e.key === 'ArrowRight') next = tabs[(idx + 1) % tabs.length];
                    if (e.key === 'ArrowLeft') next = tabs[(idx - 1 + tabs.length) % tabs.length];
                    if (e.key === 'Home') next = tabs[0];
                    if (e.key === 'End') next = tabs[tabs.length - 1];
                    if (next) { e.preventDefault(); activateTab(next); }
                });
            });

            /* ── Scroll reveal ─────────────────────────────────────── */
            var revealItems = document.querySelectorAll('[data-reveal]');
            if ('IntersectionObserver' in window && revealItems.length) {
                var observer = new IntersectionObserver(function (entries) {
                    entries.forEach(function (entry) {
                        if (entry.isIntersecting) {
                            entry.target.classList.add('is-visible');
                            observer.unobserve(entry.target);
                        }
                    });
                }, { threshold: 0.15 });
                revealItems.forEach(function (el) { observer.observe(el); });
            } else {
                revealItems.forEach(function (el) { el.classList.add('is-visible'); });
            }

            /* ── Demo modal ─────────────────────────────────────────── */
            var overlay = document.getElementById('az-demo-modal');
            var closeBtn = document.getElementById('az-demo-close');
            var form = document.getElementById('az-demo-form');
            var statusBox = document.getElementById('az-demo-status');
            var submitBtn = document.getElementById('az-demo-submit');
            var lastFocused = null;

            function openModal() {
                lastFocused = document.activeElement;
                overlay.hidden = false;
                overlay.classList.add('is-open');
                document.body.style.overflow = 'hidden';
                var firstField = form.querySelector('input, select');
                if (firstField) firstField.focus();
            }

            function closeModal() {
                overlay.classList.remove('is-open');
                overlay.hidden = true;
                document.body.style.overflow = '';
                if (lastFocused) lastFocused.focus();
            }

            document.querySelectorAll('[data-open-demo]').forEach(function (btn) {
                btn.addEventListener('click', openModal);
            });
            closeBtn.addEventListener('click', closeModal);
            overlay.addEventListener('click', function (e) {
                if (e.target === overlay) closeModal();
            });
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && overlay.classList.contains('is-open')) closeModal();
            });

            function setFieldError(name, message) {
                var field = form.querySelector('[data-field="' + name + '"]');
                if (!field) return;
                var errorEl = field.querySelector('.az-form-error');
                field.classList.toggle('has-error', !!message);
                if (errorEl) errorEl.textContent = message || '';
            }

            function clearErrors() {
                form.querySelectorAll('[data-field]').forEach(function (field) {
                    field.classList.remove('has-error');
                    var errorEl = field.querySelector('.az-form-error');
                    if (errorEl) errorEl.textContent = '';
                });
            }

            function validate(data) {
                var valid = true;
                if (!data.name.trim()) { setFieldError('name', 'Ingresa tu nombre completo.'); valid = false; }
                if (!data.school_name.trim()) { setFieldError('school_name', 'Ingresa el nombre del colegio.'); valid = false; }
                if (!data.role) { setFieldError('role', 'Selecciona tu cargo.'); valid = false; }
                var emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!data.email.trim() || !emailPattern.test(data.email)) {
                    setFieldError('email', 'Ingresa un correo electrónico válido.');
                    valid = false;
                }
                return valid;
            }

            function showStatus(type, message) {
                statusBox.textContent = message;
                statusBox.className = 'az-form-status is-visible ' + type;
            }

            form.addEventListener('submit', function (e) {
                e.preventDefault();
                clearErrors();
                statusBox.className = 'az-form-status';

                var formData = new FormData(form);
                var data = {
                    name: formData.get('name') || '',
                    school_name: formData.get('school_name') || '',
                    role: formData.get('role') || '',
                    email: formData.get('email') || '',
                    phone: formData.get('phone') || '',
                    school_size: formData.get('school_size') || '',
                };

                if (!validate(data)) return;

                submitBtn.disabled = true;
                submitBtn.textContent = 'Enviando…';

                fetch('{{ route('demo.request') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify(data),
                })
                    .then(function (res) {
                        return res.json().then(function (json) { return { ok: res.ok, json: json }; });
                    })
                    .then(function (result) {
                        if (!result.ok) {
                            if (result.json.errors) {
                                Object.keys(result.json.errors).forEach(function (key) {
                                    setFieldError(key, result.json.errors[key][0]);
                                });
                            }
                            showStatus('error', result.json.message || 'Revisa los datos e intenta de nuevo.');
                            return;
                        }
                        showStatus('success', result.json.message || 'Recibimos tu solicitud. Te contactaremos pronto.');
                        form.reset();
                    })
                    .catch(function () {
                        showStatus('error', 'No pudimos enviar tu solicitud. Intenta de nuevo en unos minutos.');
                    })
                    .finally(function () {
                        submitBtn.disabled = false;
                        submitBtn.textContent = 'Enviar solicitud';
                    });
            });
        })();
    </script>
</body>
</html>
