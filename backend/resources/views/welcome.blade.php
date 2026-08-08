<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NOVA ACADEMY · IA Educativa para Profesores</title>
    
    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600;9..40,700;9..40,800&family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        /* Design System Nova - Estilo Duolingo Premium con colores vibrantes */
        :root {
            --nova-deep: #0A0A1F;
            --nova-dark: #12122B;
            --nova-medium: #1E1A3A;
            --nova-light: #2D1F4A;
            --nova-violet: #8B5CF6;
            --nova-violet-light: #A78BFA;
            --nova-fuchsia: #C084FC;
            --nova-pink: #EC4899;
            --nova-cyan: #22D3EE;
            --nova-cyan-light: #67E8F9;
            --nova-green: #10B981;
            --nova-success: #34D399;
            --nova-warning: #FBBF24;
            --nova-orange: #F59E0B;
            --nova-gradient: linear-gradient(135deg, #8B5CF6 0%, #EC4899 30%, #22D3EE 60%, #F59E0B 100%);
            --nova-gradient-warm: linear-gradient(135deg, #EC4899 0%, #F59E0B 50%, #22D3EE 100%);
            --nova-glass: rgba(255, 255, 255, 0.04);
            --nova-glass-border: rgba(139, 92, 246, 0.25);
            
            --text-primary: rgba(255, 255, 255, 1);
            --text-secondary: rgba(255, 255, 255, 0.7);
            --text-tertiary: rgba(255, 255, 255, 0.4);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--nova-deep);
            color: var(--text-primary);
            overflow-x: hidden;
        }

        /* Fondo dinámico Nova */
        .nova-bg {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: -1;
            overflow: hidden;
        }

        .nova-bg-orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(100px);
            animation: glow-pulse 8s ease-in-out infinite;
        }

        .nova-bg-orb:nth-child(1) {
            top: -15%;
            left: -10%;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(139, 92, 246, 0.5) 0%, transparent 70%);
            animation-delay: 0s;
        }

        .nova-bg-orb:nth-child(2) {
            bottom: -20%;
            right: -15%;
            width: 700px;
            height: 700px;
            background: radial-gradient(circle, rgba(236, 72, 153, 0.4) 0%, transparent 70%);
            animation-delay: 2s;
        }

        .nova-bg-orb:nth-child(3) {
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(34, 211, 238, 0.3) 0%, transparent 70%);
            animation-delay: 4s;
        }

        .nova-grid {
            position: absolute;
            inset: 0;
            background-image: 
                linear-gradient(var(--nova-glass-border) 1px, transparent 1px),
                linear-gradient(90deg, var(--nova-glass-border) 1px, transparent 1px);
            background-size: 60px 60px;
            opacity: 0.2;
        }

        @keyframes glow-pulse {
            0%, 100% { opacity: 0.4; transform: scale(1); }
            50% { opacity: 0.8; transform: scale(1.05); }
        }

        .welcome-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 1.5rem 2rem;
            position: relative;
            z-index: 2;
        }

        /* Header */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 0;
            margin-bottom: 2rem;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .logo-text {
            font-family: 'Outfit', sans-serif;
            font-size: 1.85rem;
            font-weight: 800;
            letter-spacing: -0.5px;
            background: linear-gradient(135deg, #fff 0%, #22D3EE 40%, #EC4899 70%, #F59E0B 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 2rem;
        }

        .nav-links a {
            color: var(--text-secondary);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.95rem;
            transition: all 0.3s;
            position: relative;
        }

        .nav-links a:hover {
            color: white;
        }

        .nav-links a.highlight {
            background: var(--nova-gradient);
            padding: 0.6rem 1.6rem;
            border-radius: 30px;
            color: white;
            font-weight: 600;
            box-shadow: 0 4px 16px -4px rgba(139, 92, 246, 0.5);
        }

        .nav-links a.highlight:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px -8px rgba(139, 92, 246, 0.6);
        }

        /* Hero Section */
        .hero {
            display: grid;
            grid-template-columns: 1.1fr 0.9fr;
            gap: 2.5rem;
            align-items: center;
            padding: 2.5rem 0 4rem;
        }

        .hero-content {
            position: relative;
            max-width: 720px;
            text-align: left;
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(34, 211, 238, 0.1);
            border: 1px solid rgba(34, 211, 238, 0.3);
            border-radius: 30px;
            padding: 0.5rem 1rem;
            margin-bottom: 1.5rem;
            font-size: 0.85rem;
            color: var(--nova-cyan);
            font-weight: 500;
        }

        .hero-title {
            font-family: 'Outfit', sans-serif;
            font-size: 4.2rem;
            font-weight: 800;
            line-height: 1.05;
            margin-bottom: 1.5rem;
            letter-spacing: -1px;
            background: var(--nova-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .hero-subtitle {
            font-size: 1.15rem;
            color: var(--text-secondary);
            margin-bottom: 2rem;
            max-width: 620px;
            line-height: 1.7;
        }

        .hero-stats {
            display: flex;
            gap: 2.5rem;
            margin: 2rem 0;
            justify-content: flex-start;
        }

        .stat-item {
            display: flex;
            flex-direction: column;
        }

        .stat-number {
            font-family: 'Outfit', sans-serif;
            font-size: 2rem;
            font-weight: 800;
            background: linear-gradient(135deg, #fff, var(--nova-cyan));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            line-height: 1;
        }

        .stat-label {
            font-size: 0.85rem;
            color: var(--text-tertiary);
            margin-top: 0.4rem;
        }

        .hero-cta {
            display: flex;
            gap: 1rem;
            align-items: center;
            justify-content: flex-start;
        }

        .btn-primary {
            background: var(--nova-gradient);
            border: none;
            padding: 0.9rem 2rem;
            border-radius: 40px;
            color: white;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: inline-flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            box-shadow: 0 8px 24px -8px rgba(139, 92, 246, 0.5);
        }

        .btn-primary:hover {
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 16px 32px -12px rgba(139, 92, 246, 0.6);
        }

        .btn-secondary {
            background: var(--nova-glass);
            border: 1px solid var(--nova-glass-border);
            padding: 0.9rem 2rem;
            border-radius: 40px;
            color: white;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.08);
            border-color: var(--nova-cyan);
            transform: translateY(-2px);
        }

        .hero-image {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .hero-image-container {
            position: relative;
            width: 100%;
            max-width: 560px;
            padding: 20px;
        }

        .nova-mascot {
            width: 100%;
            aspect-ratio: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            animation: float 6s ease-in-out infinite;
            position: relative;
            background: radial-gradient(circle at center, rgba(139, 92, 246, 0.08) 0%, transparent 70%);
            border-radius: 50%;
        }

        .nova-mascot::before {
            content: '';
            position: absolute;
            inset: -20px;
            background: radial-gradient(circle at center, rgba(236, 72, 153, 0.1) 0%, transparent 60%);
            border-radius: 50%;
            animation: pulse-ring 3s ease-in-out infinite;
            z-index: -1;
        }

        @keyframes pulse-ring {
            0%, 100% { transform: scale(1); opacity: 0.5; }
            50% { transform: scale(1.05); opacity: 0.8; }
        }

        .nova-mascot img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            filter: drop-shadow(0 30px 60px rgba(139, 92, 246, 0.5)) 
                    drop-shadow(0 15px 30px rgba(236, 72, 153, 0.3));
            transform: scale(1.05);
            transition: transform 0.3s ease;
        }

        .nova-mascot:hover img {
            transform: scale(1.15);
        }

        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-15px); }
        }

        .hero-glow {
            position: absolute;
            width: 140%;
            height: 140%;
            top: -20%;
            left: -20%;
            background: radial-gradient(circle, rgba(236, 72, 153, 0.28) 0%, rgba(34, 211, 238, 0.22) 35%, transparent 70%);
            filter: blur(60px);
            z-index: -1;
            animation: pulse-glow-hero 4s ease-in-out infinite;
        }

        @keyframes pulse-glow-hero {
            0%, 100% { 
                opacity: 0.6; 
                transform: scale(1);
            }
            50% { 
                opacity: 0.9; 
                transform: scale(1.08);
            }
        }

        /* Features Section */
        .features {
            padding: 4rem 0;
        }

        .section-header {
            text-align: center;
            margin-bottom: 3.5rem;
        }

        .section-header h2 {
            font-family: 'Outfit', sans-serif;
            font-size: 2.6rem;
            font-weight: 800;
            background: linear-gradient(135deg, #fff, var(--nova-pink), var(--nova-cyan));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 1rem;
        }

        .section-header p {
            color: var(--text-secondary);
            font-size: 1.1rem;
            max-width: 600px;
            margin: 0 auto;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.5rem;
        }

        .feature-card {
            background: rgba(255, 255, 255, 0.02);
            backdrop-filter: blur(10px);
            border: 1px solid var(--nova-glass-border);
            border-radius: 24px;
            padding: 2rem 1.75rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .feature-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--nova-violet), var(--nova-pink), var(--nova-cyan));
            transform: translateX(-100%);
            transition: transform 0.5s ease;
        }

        .feature-card:hover {
            transform: translateY(-8px);
            border-color: rgba(236, 72, 153, 0.4);
            box-shadow: 0 20px 40px -20px rgba(236, 72, 153, 0.3);
        }

        .feature-card:hover::before {
            transform: translateX(0);
        }

        .feature-icon {
            width: 56px;
            height: 56px;
            background: linear-gradient(135deg, rgba(139, 92, 246, 0.2) 0%, rgba(236, 72, 153, 0.2) 100%);
            border: 1px solid rgba(139, 92, 246, 0.3);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: var(--nova-pink);
            margin-bottom: 1.25rem;
        }

        .feature-icon svg {
            width: 24px;
            height: 24px;
            stroke: currentColor;
            fill: none;
            stroke-width: 2;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .feature-card h3 {
            font-family: 'Outfit', sans-serif;
            font-size: 1.35rem;
            font-weight: 700;
            margin-bottom: 0.75rem;
            color: white;
        }

        .feature-card p {
            color: var(--text-secondary);
            line-height: 1.6;
            font-size: 0.95rem;
        }

        /* Pricing — 4 planes piloto 2026 (solo esta sección) */
        .pricing {
            padding: 4rem 0;
        }

        .pricing-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1.25rem;
            max-width: 1200px;
            margin: 0 auto;
            align-items: stretch;
        }

        .pricing-card {
            background: var(--nova-glass);
            backdrop-filter: blur(10px);
            border: 1px solid var(--nova-glass-border);
            border-radius: 24px;
            padding: 2rem 1.5rem;
            transition: all 0.3s;
            position: relative;
            display: flex;
            flex-direction: column;
        }

        .pricing-card.popular {
            background: linear-gradient(135deg, rgba(139, 92, 246, 0.15) 0%, rgba(236, 72, 153, 0.15) 100%);
            border-color: rgba(236, 72, 153, 0.5);
            transform: scale(1.03);
            box-shadow: 0 0 40px rgba(139, 92, 246, 0.25), 0 0 70px rgba(236, 72, 153, 0.12);
            z-index: 1;
        }

        .pricing-card.popular::before {
            content: 'Piloto Casa212';
            position: absolute;
            top: -12px;
            left: 50%;
            transform: translateX(-50%);
            background: var(--nova-gradient-warm);
            padding: 0.35rem 1rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            color: white;
            white-space: nowrap;
        }

        .pricing-card h3 {
            font-family: 'Outfit', sans-serif;
            font-size: 1.15rem;
            font-weight: 700;
            margin-bottom: 0.35rem;
            color: white;
        }

        .pricing-card .plan-subtitle {
            color: var(--text-secondary);
            font-size: 0.85rem;
            margin-bottom: 1rem;
            line-height: 1.4;
        }

        .pricing-card .price {
            font-family: 'Outfit', sans-serif;
            font-size: 2.25rem;
            font-weight: 800;
            color: white;
            margin-bottom: 0.25rem;
            line-height: 1.1;
            display: flex;
            align-items: baseline;
            flex-wrap: wrap;
            gap: 0.15rem 0.25rem;
        }

        .pricing-card .price .price-currency {
            font-size: 0.95rem;
            color: var(--text-secondary);
            font-weight: 500;
            align-self: flex-start;
            margin-top: 0.35rem;
        }

        .pricing-card .price .price-period,
        .pricing-card .price span {
            font-size: 0.95rem;
            color: var(--text-secondary);
            font-weight: 400;
        }

        .pricing-card ul {
            list-style: none;
            margin: 1.25rem 0 1.5rem;
            flex: 1;
        }

        .pricing-card li {
            color: var(--text-secondary);
            padding: 0.45rem 0;
            display: flex;
            align-items: flex-start;
            gap: 10px;
            font-size: 0.85rem;
            line-height: 1.4;
        }

        .pricing-card li i {
            color: var(--nova-violet);
            margin-top: 0.15rem;
            flex-shrink: 0;
        }

        .pricing-card .btn-plan {
            width: 100%;
            padding: 0.8rem;
            border-radius: 30px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: block;
            text-align: center;
            margin-top: auto;
            font-size: 0.9rem;
        }

        .pricing-card .btn-plan.primary {
            background: var(--nova-gradient-warm);
            color: white;
            border: none;
        }

        .pricing-card .btn-plan.secondary {
            background: transparent;
            border: 1px solid var(--nova-glass-border);
            color: white;
        }

        .pricing-banner {
            max-width: 900px;
            margin: 2.5rem auto 0;
            padding: 1.1rem 1.5rem;
            text-align: center;
            background: var(--nova-glass);
            backdrop-filter: blur(10px);
            border: 1px solid var(--nova-glass-border);
            border-radius: 16px;
            color: var(--text-secondary);
            font-size: 0.95rem;
            line-height: 1.55;
        }

        .pricing-footnote {
            max-width: 800px;
            margin: 1.25rem auto 0;
            text-align: center;
            color: var(--text-tertiary);
            font-size: 0.8rem;
            line-height: 1.5;
        }

        /* CTA Section */
        .cta-section {
            text-align: center;
            padding: 5rem 0;
        }

        .cta-card {
            background: linear-gradient(135deg, rgba(139, 92, 246, 0.2) 0%, rgba(236, 72, 153, 0.2) 100%);
            border: 1px solid rgba(236, 72, 153, 0.3);
            border-radius: 32px;
            padding: 4rem 2rem;
            max-width: 800px;
            margin: 0 auto;
            position: relative;
            overflow: hidden;
        }

        .cta-card::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(236, 72, 153, 0.1) 0%, transparent 50%);
            animation: rotate 20s linear infinite;
        }

        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .cta-card h2 {
            font-family: 'Outfit', sans-serif;
            font-size: 2.4rem;
            font-weight: 800;
            color: white;
            margin-bottom: 1rem;
            position: relative;
            z-index: 1;
        }

        .cta-card p {
            color: var(--text-secondary);
            font-size: 1.1rem;
            margin-bottom: 2rem;
            position: relative;
            z-index: 1;
        }

        .cta-card .btn-primary {
            position: relative;
            z-index: 1;
            padding: 1rem 2.5rem;
            font-size: 1.1rem;
        }

        /* Footer */
        .footer {
            padding: 2rem 0;
            border-top: 1px solid var(--nova-glass-border);
        }

        .footer-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .footer-logo {
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        .footer-links {
            display: flex;
            gap: 2rem;
        }

        .footer-links a {
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 0.9rem;
            transition: color 0.3s;
        }

        .footer-links a:hover {
            color: var(--nova-cyan);
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .hero {
                grid-template-columns: 1fr;
                text-align: center;
            }

            .hero-content {
                text-align: center;
            }

            .hero-subtitle {
                margin: 0 auto 2rem;
            }

            .hero-stats,
            .hero-cta {
                justify-content: center;
            }
            
            .features-grid,
            .pricing-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .welcome-container {
                padding: 1rem;
            }
            
            .header {
                flex-direction: column;
                gap: 1rem;
            }
            
            .nav-links {
                flex-wrap: wrap;
                justify-content: center;
                gap: 1rem;
            }
            
            .hero-title {
                font-size: 2.5rem;
            }
            
            .hero-stats {
                gap: 1.5rem;
            }
            
            .features-grid,
            .pricing-grid {
                grid-template-columns: 1fr;
            }
            
            .pricing-card.popular {
                transform: scale(1);
            }
            
            .footer-content {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }
            
            .footer-links {
                flex-wrap: wrap;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <!-- Fondo dinámico Nova -->
    <div class="nova-bg">
        <div class="nova-bg-orb"></div>
        <div class="nova-bg-orb"></div>
        <div class="nova-bg-orb"></div>
        <div class="nova-grid"></div>
    </div>

    <div class="welcome-container">
        <!-- Header -->
        <header class="header">
            <div class="logo">
                <span class="logo-text">NOVA ACADEMY</span>
            </div>
            <nav class="nav-links">
                <a href="#features">Características</a>
                <a href="#pricing">Precios</a>
                <a href="{{ route('login') }}" class="highlight">
                    <i class="fa-regular fa-circle-user"></i> Iniciar Sesión
                </a>
            </nav>
        </header>

        <!-- Hero Section -->
        <section class="hero">
            <div class="hero-content">
                <div class="hero-badge">
                    <i class="fa-regular fa-star"></i>
                    <span>El futuro de la planificación educativa</span>
                </div>
                <h1 class="hero-title">
                    Planifica un Mes de Clases <br>en 5 Minutos
                </h1>
                <p class="hero-subtitle">
                    Deja atrás las horas de planificación. Nova transforma tus ideas en planificaciones completas, 
                    personalizadas y listas para usar con inteligencia artificial especializada en educación.
                </p>
                
                <div class="hero-stats">
                    <div class="stat-item">
                        <span class="stat-number">Beta Activa</span>
                        <span class="stat-label">Validando con colegios</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number">IA Generativa</span>
                        <span class="stat-label">Planificaciones automáticas</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number">Ahorra 10h/semana</span>
                        <span class="stat-label">por profesor</span>
                    </div>
                </div>

                <div class="hero-cta">
                    <a href="{{ route('register') }}" class="btn-primary">
                        <i class="fa-regular fa-rocket"></i>
                        Comenzar Gratis
                    </a>
                    <a href="#demo" class="btn-secondary">
                        <i class="fa-regular fa-circle-play"></i>
                        Ver Demo
                    </a>
                </div>
            </div>
            <div class="hero-image">
                <div class="hero-image-container animate-pulse">
                    <div class="hero-glow"></div>
                    <div class="nova-mascot">
                        <img src="/images/emoji leyendo sin fondo.png" alt="NOVA - Tu Asistente de IA">
                    </div>
                </div>
            </div>

        </section>

        <!-- Features Section -->
        <section class="features" id="features">
            <div class="section-header">
                <h2>Todo lo que necesitas en un solo lugar</h2>
                <p>Una plataforma completa potenciada por inteligencia artificial para educadores modernos</p>
            </div>

            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
                    </div>
                    <h3>5x Más Rápido</h3>
                    <p>Reduce el tiempo de planificación de horas a minutos con nuestra IA especializada en educación.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9.937 15.5A2 2 0 0 0 8.5 14.063l-6.135-1.582a.5.5 0 0 1 0-.962L8.5 9.936A2 2 0 0 0 9.937 8.5l1.582-6.135a.5.5 0 0 1 .963 0L14.063 8.5A2 2 0 0 0 15.5 9.937l6.135 1.581a.5.5 0 0 1 0 .964L15.5 14.063a2 2 0 0 0-1.437 1.437l-1.582 6.135a.5.5 0 0 1-.963 0z"/><path d="M20 3v4"/><path d="M22 5h-4"/><path d="M4 17v2"/><path d="M5 18H3"/></svg>
                    </div>
                    <h3>IA Especializada</h3>
                    <p>Algoritmos entrenados específicamente con miles de planificaciones y estándares educativos.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21.42 10.922a1 1 0 0 0-.019-1.838L12.83 5.18a2 2 0 0 0-1.66 0L2.6 9.08a1 1 0 0 0 0 1.832l8.57 3.908a2 2 0 0 0 1.66 0z"/><path d="M22 10v6"/><path d="M6 12.5V16a6 3 0 0 0 12 0v-3.5"/></svg>
                    </div>
                    <h3>Adaptado a Ti</h3>
                    <p>Se ajusta automáticamente a tu estilo de enseñanza, materia y nivel educativo.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/></svg>
                    </div>
                    <h3>Calendario Inteligente</h3>
                    <p>Visualiza todas tus actividades y planificaciones en un calendario interactivo.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    </div>
                    <h3>Gestión de Alumnos</h3>
                    <p>Administra cursos, estudiantes y calificaciones desde un solo panel.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/></svg>
                    </div>
                    <h3>Exportación Profesional</h3>
                    <p>Exporta tus planificaciones a PDF, Word o Google Docs con un clic.</p>
                </div>
            </div>
        </section>

        <!-- Pricing Section — actualizada: 4 planes piloto 2026 -->
        <section class="pricing" id="pricing">
            <div class="section-header">
                <h2>Precios Piloto — Cohorte Inicial 2026</h2>
                <p>Valores especiales para colegios aliados mientras validamos el producto.</p>
            </div>

            <div class="pricing-grid">
                <!-- Plan 1: Docente -->
                <div class="pricing-card">
                    <h3>Docente</h3>
                    <p class="plan-subtitle">Para profesores independientes</p>
                    <div class="price">Gratis</div>
                    <ul class="pricing-features">
                        <li><i class="fa-solid fa-check"></i> 1 curso activo</li>
                        <li><i class="fa-solid fa-check"></i> Planificación básica con IA</li>
                        <li><i class="fa-solid fa-check"></i> Calendario académico</li>
                        <li><i class="fa-solid fa-check"></i> Hasta 20 alumnos</li>
                    </ul>
                    <a href="{{ route('register') }}" class="btn-plan secondary">Empezar Gratis</a>
                </div>

                <!-- Plan 2: Colegio Piloto (destacado) -->
                <div class="pricing-card popular">
                    <h3>Colegio Piloto</h3>
                    <p class="plan-subtitle">Precio especial de validación 2026</p>
                    <div class="price">
                        <span class="price-currency">$</span>49<span class="price-period">/mes</span>
                    </div>
                    <ul class="pricing-features">
                        <li><i class="fa-solid fa-check"></i> Hasta 15 docentes</li>
                        <li><i class="fa-solid fa-check"></i> IA para planificaciones completas</li>
                        <li><i class="fa-solid fa-check"></i> Rúbricas y evaluaciones automáticas</li>
                        <li><i class="fa-solid fa-check"></i> Dashboard directivo básico</li>
                        <li><i class="fa-solid fa-check"></i> Soporte prioritario</li>
                        <li><i class="fa-solid fa-check"></i> Implementación gratuita</li>
                    </ul>
                    <a href="#demo" class="btn-plan primary">Solicitar Demo Piloto</a>
                </div>

                <!-- Plan 3: Colegio -->
                <div class="pricing-card">
                    <h3>Colegio</h3>
                    <p class="plan-subtitle">Precio comercial estándar</p>
                    <div class="price">
                        <span class="price-currency">$</span>99<span class="price-period">/mes</span>
                    </div>
                    <ul class="pricing-features">
                        <li><i class="fa-solid fa-check"></i> Hasta 25 docentes</li>
                        <li><i class="fa-solid fa-check"></i> Todo lo del plan Piloto</li>
                        <li><i class="fa-solid fa-check"></i> Reportes académicos avanzados</li>
                        <li><i class="fa-solid fa-check"></i> Exportación profesional (PDF, Word, Google Calendar)</li>
                        <li><i class="fa-solid fa-check"></i> Soporte con respuesta en 4 horas</li>
                    </ul>
                    <a href="#demo" class="btn-plan secondary">Agendar Demo</a>
                </div>

                <!-- Plan 4: Red Educativa -->
                <div class="pricing-card">
                    <h3>Red Educativa</h3>
                    <p class="plan-subtitle">Para cadenas de colegios</p>
                    <div class="price">
                        <span class="price-currency">Desde $</span>499<span class="price-period">/mes</span>
                    </div>
                    <ul class="pricing-features">
                        <li><i class="fa-solid fa-check"></i> Múltiples sedes</li>
                        <li><i class="fa-solid fa-check"></i> Docentes ilimitados</li>
                        <li><i class="fa-solid fa-check"></i> Analítica avanzada por sede</li>
                        <li><i class="fa-solid fa-check"></i> Capacitación del equipo incluida</li>
                        <li><i class="fa-solid fa-check"></i> Integraciones futuras (API)</li>
                        <li><i class="fa-solid fa-check"></i> SLA garantizado</li>
                    </ul>
                    <a href="#contacto" class="btn-plan secondary">Hablar con Ventas</a>
                </div>
            </div>

            <div class="pricing-banner">
                💡 Un colegio que ahorre solo 10 horas administrativas al mes ya recupera varias veces el costo de Nova Academy.
            </div>
            <p class="pricing-footnote">
                🚀 Los primeros 10 colegios piloto recibirán implementación y acompañamiento gratuito durante 3 meses a cambio de feedback y métricas de uso.
            </p>
        </section>

        <!-- Footer -->
        <footer class="footer">
            <div class="footer-content">
                <div class="footer-logo">
                    <span>© 2026 NOVA ACADEMY. Todos los derechos reservados.</span>
                </div>
                <div class="footer-links">
                    <a href="#features">Características</a>
                    <a href="#pricing">Precios</a>
                    <a href="#">Términos</a>
                    <a href="#">Privacidad</a>
                </div>
            </div>
        </footer>
    </div>
</body>
</html>
