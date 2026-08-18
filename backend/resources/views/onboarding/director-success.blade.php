<!DOCTYPE html>
<html lang="es" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>¡Colegio creado! · AulaSync</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { darkMode: 'class' };</script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    @include('partials.nova-theme')
    <style>
        [x-cloak] { display: none !important; }
        body { font-family: Inter, ui-sans-serif, system-ui, -apple-system, sans-serif; }
        .glow-card {
            background: linear-gradient(145deg, rgba(255,255,255,.12), rgba(255,255,255,.04));
            border: 1px solid rgba(255,255,255,.15);
            box-shadow: 0 24px 80px rgba(0,0,0,.35), inset 0 1px 0 rgba(255,255,255,.1);
            backdrop-filter: blur(24px);
        }
        .invite-code {
            font-family: 'SF Mono', 'Fira Code', 'JetBrains Mono', monospace;
            letter-spacing: .15em;
            background: linear-gradient(135deg, #c084fc, #818cf8, #38bdf8);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-shadow: 0 0 60px rgba(129, 140, 248, .3);
        }
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-8px); }
        }
        .float-anim { animation: float 3s ease-in-out infinite; }
        .pulse-ring {
            box-shadow: 0 0 0 0 rgba(129, 140, 248, .5);
            animation: pulse-ring 2s infinite;
        }
        @keyframes pulse-ring {
            0% { box-shadow: 0 0 0 0 rgba(129, 140, 248, .4); }
            70% { box-shadow: 0 0 0 20px rgba(129, 140, 248, 0); }
            100% { box-shadow: 0 0 0 0 rgba(129, 140, 248, 0); }
        }
        .btn-copy {
            transition: all .2s ease;
        }
        .btn-copy:active { transform: scale(.96); }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4" style="background: var(--bg-primary);">
    <div class="fixed inset-0 -z-10 overflow-hidden">
        <div class="absolute -left-32 -top-32 h-96 w-96 rounded-full bg-violet-600/25 blur-[120px]"></div>
        <div class="absolute -bottom-32 right-0 h-[28rem] w-[28rem] rounded-full bg-cyan-500/15 blur-[130px]"></div>
        <div class="absolute left-1/3 top-1/2 h-64 w-64 rounded-full bg-fuchsia-600/15 blur-[100px]"></div>
    </div>

    <div class="glow-card w-full max-w-lg rounded-[2.5rem] p-8 md:p-12 text-center" x-data="copyCode()">
        <div class="mx-auto mb-6 flex h-20 w-20 items-center justify-center rounded-[1.5rem] bg-gradient-to-br from-emerald-400 to-cyan-500 shadow-lg shadow-emerald-500/20 pulse-ring float-anim">
            <i class="fa-solid fa-check text-3xl text-white"></i>
        </div>

        <p class="text-xs font-bold uppercase tracking-[.3em] text-cyan-200 mb-2">Éxito</p>
        <h1 class="text-2xl md:text-3xl font-black text-white mb-2">¡Colegio creado!</h1>
        <p class="text-slate-400 text-sm mb-4">
            Tu institución <strong class="text-white">{{ $schoolName }}</strong> ya está lista.
        </p>
        <p class="text-slate-400 text-sm mb-8">
            Este es el <strong class="text-white">código institucional</strong> del colegio. Compártelo con representantes o docentes que no tengan un código <strong class="text-cyan-200">DOC-</strong> personal.
            <br><span class="text-slate-500">Para invitar profesores con materia asignada, usa la sección Plantel docente en tu dashboard.</span>
        </p>

        <div class="mx-auto mb-8 max-w-xs rounded-2xl border border-white/10 bg-white/[.045] px-4 py-5">
            <p class="text-xs uppercase tracking-[.2em] text-slate-500 mb-2">Código de invitación</p>
            <p class="invite-code text-5xl md:text-6xl font-black tracking-[.15em] select-all" x-ref="code">{{ $inviteCode }}</p>
        </div>

        <div class="flex flex-col gap-3">
            <button @click="copy" class="btn-copy flex items-center justify-center gap-3 rounded-2xl border-2 px-5 py-4 text-sm font-bold transition-all duration-200 shadow-lg"
                    :class="copied
                        ? 'border-emerald-400/40 bg-emerald-400/15 text-emerald-200 shadow-emerald-500/10'
                        : 'border-white/20 bg-white/10 text-white hover:bg-white/15 hover:shadow-xl hover:shadow-violet-500/10 hover:-translate-y-0.5'">
                <span class="text-lg" x-text="copied ? '✅' : '📋'"></span>
                <span x-text="copied ? '¡Copiado!' : 'Copiar Código'"></span>
            </button>

            <a href="https://wa.me/?text={{ urlencode('¡Hola! Te invito a unirte a nuestro colegio ' . $schoolName . ' en AulaSync. Usa este código para registrarte: ' . $inviteCode) }}"
               target="_blank"
               rel="noopener noreferrer"
               class="flex items-center justify-center gap-3 rounded-2xl border border-emerald-400/30 bg-emerald-400/10 px-5 py-3.5 text-sm font-bold text-emerald-200 transition hover:bg-emerald-400/20 hover:shadow-lg hover:shadow-emerald-500/10">
                <i class="fa-brands fa-whatsapp text-lg"></i>
                Compartir por WhatsApp
            </a>
        </div>

        <div class="mt-8 pt-6 border-t border-white/10 space-y-3">
            <p class="text-xs font-bold uppercase tracking-[.2em] text-slate-500">Siguiente</p>
            <div class="grid gap-2 text-left text-sm text-slate-400">
                <p><span class="font-bold text-cyan-200">1.</span> Invita docentes con códigos DOC-</p>
                <p><span class="font-bold text-violet-200">2.</span> Crea cursos y asigna materias</p>
                <p><span class="font-bold text-emerald-200">3.</span> Matricula alumnos en la nómina</p>
            </div>
            <a href="{{ route('director.dashboard', ['setup' => 1]) }}"
               class="inline-flex items-center gap-3 rounded-2xl bg-gradient-to-r from-violet-600 to-cyan-500 px-8 py-4 text-base font-bold text-white shadow-lg shadow-violet-500/20 transition hover:shadow-xl hover:shadow-violet-500/30 hover:-translate-y-0.5">
                <i class="fa-solid fa-arrow-right"></i>
                Ir a configurar mi colegio
            </a>
        </div>
    </div>

    <script>
        function copyCode() {
            return {
                copied: false,
                async copy() {
                    try {
                        await navigator.clipboard.writeText(this.$refs.code.textContent);
                        this.copied = true;
                        setTimeout(() => { this.copied = false; }, 3000);
                    } catch {
                        const range = document.createRange();
                        range.selectNode(this.$refs.code);
                        window.getSelection().removeAllRanges();
                        window.getSelection().addRange(range);
                        document.execCommand('copy');
                        window.getSelection().removeAllRanges();
                        this.copied = true;
                        setTimeout(() => { this.copied = false; }, 3000);
                    }
                }
            };
        }
    </script>
</body>
</html>
