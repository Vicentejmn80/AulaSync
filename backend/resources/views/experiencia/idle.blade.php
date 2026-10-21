<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#6C5CE7">
    <title>Experiencia interactiva · AulaSync</title>
    <link rel="manifest" href="/experiencia.webmanifest">
    <link rel="icon" href="/favicon.ico?v=3" sizes="any">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/experiencia.css') }}?v={{ filemtime(public_path('css/experiencia.css')) }}">
</head>
<body>
    <div class="sky" aria-hidden="true">
        <span class="blob blob-a"></span>
        <span class="blob blob-b"></span>
        <span class="float f1">✏️</span>
        <span class="float far f2">📚</span>
        <span class="float f3">🎓</span>
        <span class="float far f4">⭐</span>
        <span class="float f5">⚙️</span>
    </div>
    <div class="loader" id="experiencia-loader">Preparando a Aulo…</div>
    <h1 class="sr-only">Experiencia interactiva de AulaSync</h1>
    <p class="sync" id="sync-dot">🟢</p>
    <button type="button" class="admin-hot" id="esquina-admin" aria-label="Herramientas del stand"></button>
    <div class="admin-panel" id="panel-admin" hidden>
        <p id="admin-conteo"></p>
        <button type="button" class="ghost-btn" id="admin-csv">Descargar CSV</button>
        <button type="button" class="ghost-btn" id="admin-cerrar">Cerrar</button>
    </div>

    <main class="app" id="experiencia-app" data-state="idle">
        <div class="shell">
            <div class="camera-wrap">
                <div class="camera" id="experiencia-camera">
                    @if ($celebrateVideoUrl)
                        <video id="experiencia-celebrate-fill" class="scene-fill" src="{{ $celebrateVideoUrl }}" muted playsinline webkit-playsinline preload="auto" aria-hidden="true"></video>
                    @endif
                    @if ($typingVideoUrl)
                        <video id="experiencia-idle-video" class="scene-video is-on" src="{{ $typingVideoUrl }}" autoplay loop muted playsinline webkit-playsinline preload="auto" disablepictureinpicture aria-hidden="true"></video>
                    @endif
                    @if ($waveVideoUrl)
                        <video id="experiencia-wave-video" class="scene-video" src="{{ $waveVideoUrl }}" playsinline webkit-playsinline preload="auto" disablepictureinpicture aria-hidden="true"></video>
                    @endif
                    @if ($celebrateVideoUrl)
                        <video id="experiencia-celebrate-video" class="scene-video" src="{{ $celebrateVideoUrl }}" playsinline webkit-playsinline preload="auto" disablepictureinpicture aria-hidden="true"></video>
                    @endif
                    @unless ($typingVideoUrl)
                        <p class="fallback">El video del robot se incorpora en config/experiencia.php.</p>
                    @endunless
                </div>
            </div>

            <div class="ui">
                <button type="button" class="start-btn" id="experiencia-comenzar">Toca para jugar y gana un premio</button>

                <section class="panel" id="panel-roles" aria-label="Selección de rol">
                    <p class="badge">¡Hola! Soy Aulo 🤖</p>
                    <p class="subtitle">Selecciona tu rol para continuar:</p>
                    <div class="roles">
                        <button type="button" class="role-btn" data-role="profesor">👨‍🏫 Eres Profesor?</button>
                        <button type="button" class="role-btn" data-role="director">🏫 Eres Director?</button>
                    </div>
                </section>

                <section class="panel" id="panel-quiz" aria-label="Trivia">
                    <div class="quiz-head">
                        <p class="quiz-progress" id="quiz-progress">1/3</p>
                        <div class="timer" id="quiz-timer" aria-hidden="true"></div>
                        <p class="streak" id="quiz-streak"></p>
                    </div>
                    <h2 class="question" id="quiz-question"></h2>
                    <p class="reaction" id="quiz-reaction" aria-hidden="true"></p>
                    <div class="options" id="quiz-options"></div>
                    <p class="fact" id="quiz-fact"></p>
                </section>

                <section class="panel" id="panel-roulette" aria-label="Ruleta de premios">
                    <p class="badge">Ruleta de AulaSync</p>
                    <p class="roulette-title">Gira y descubre tu premio</p>
                    <div class="roulette-stage">
                        <div class="wheel-spin" id="ruleta"></div>
                        <div class="leds" id="leds" aria-hidden="true"></div>
                        <div class="pointer" id="puntero" aria-hidden="true"></div>
                        <button type="button" class="spin-btn" id="girar-ruleta">¡Girar Ruleta!</button>
                    </div>
                </section>
            </div>

            <div class="celebrate-card">
            <div class="prize-card">
                <p class="prize-icon" id="premio-icono"></p>
                <h2 id="premio-nombre"></h2>
                <p id="premio-texto"></p>
                <p class="win-ask">¡Ganaste! Para enviártelo, dinos a dónde mandarlo</p>
                <form id="premio-form">
                    <div class="fields">
                        <label>Nombre <input name="nombre" autocomplete="name" required></label>
                        <label>Colegio <input name="colegio" autocomplete="organization" required></label>
                        <label>WhatsApp
                            <span class="phone">
                                <input name="codigo" value="+58" inputmode="tel" aria-label="Código de país" required>
                                <input name="whatsapp" inputmode="numeric" placeholder="4141234567" required>
                            </span>
                        </label>
                        <label class="consent">
                            <input type="checkbox" name="consentimiento" required>
                            <span>Acepto que AulaSync me contacte por WhatsApp para entregarme mi premio e información</span>
                        </label>
                    </div>
                    <p class="form-error" id="form-error"></p>
                    <button type="submit" class="claim-btn">Reclamar mi premio</button>
                </form>
                <p class="confirm-copy" id="confirm-text"></p>
                <p class="code" id="confirm-code"></p>
            </div>
        </div>
        </div>
    </main>

    <script id="experiencia-config" type="application/json">@json($kiosk)</script>
    <script src="{{ asset('js/experiencia-audio.js') }}?v={{ filemtime(public_path('js/experiencia-audio.js')) }}"></script>
    <script src="{{ asset('js/experiencia-store.js') }}?v={{ filemtime(public_path('js/experiencia-store.js')) }}"></script>
    <script src="{{ asset('js/experiencia-wheel.js') }}?v={{ filemtime(public_path('js/experiencia-wheel.js')) }}"></script>
    <script src="{{ asset('js/experiencia.js') }}?v={{ filemtime(public_path('js/experiencia.js')) }}"></script>
</body>
</html>
