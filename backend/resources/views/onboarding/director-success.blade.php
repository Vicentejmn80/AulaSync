<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>¡Colegio creado! · AulaSync</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    @include('partials.nova-theme')
    <style>
        body { font-family: Inter, ui-sans-serif, system-ui, -apple-system, sans-serif; }

        .success-card {
            background: #ffffff;
            border: 1px solid rgba(226, 232, 240, 0.9);
            box-shadow: 0 20px 60px rgba(79, 70, 229, 0.12), 0 4px 16px rgba(15, 23, 42, 0.06);
        }

        .invite-code {
            font-family: 'SF Mono', 'Fira Code', 'JetBrains Mono', ui-monospace, monospace;
            letter-spacing: .12em;
            background: linear-gradient(135deg, #6366f1, #8b5cf6, #06b6d4);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-6px); }
        }
        .float-anim { animation: float 3s ease-in-out infinite; }

        @keyframes pulse-ring {
            0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.45); }
            70% { box-shadow: 0 0 0 18px rgba(16, 185, 129, 0); }
            100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
        }
        .pulse-ring { animation: pulse-ring 2s infinite; }

        .step-num {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 1.5rem;
            height: 1.5rem;
            border-radius: 9999px;
            font-size: 0.7rem;
            font-weight: 800;
            color: #fff;
            flex-shrink: 0;
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center bg-gradient-to-br from-indigo-50 via-white to-violet-50 p-4">
    <div class="fixed inset-0 -z-10 overflow-hidden pointer-events-none">
        <div class="absolute -left-32 -top-32 h-96 w-96 rounded-full bg-violet-300/30 blur-[120px]"></div>
        <div class="absolute -bottom-32 right-0 h-[28rem] w-[28rem] rounded-full bg-cyan-300/25 blur-[130px]"></div>
        <div class="absolute left-1/3 top-1/2 h-64 w-64 rounded-full bg-fuchsia-300/20 blur-[100px]"></div>
    </div>

    <div class="success-card w-full max-w-lg rounded-3xl p-8 md:p-10 text-center">
        {{-- Icono de éxito --}}
        <div class="mx-auto mb-5 flex h-20 w-20 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-400 to-teal-500 shadow-lg shadow-emerald-500/30 pulse-ring float-anim">
            <i class="fa-solid fa-check text-3xl text-white"></i>
        </div>

        <p class="mb-1 text-xs font-bold uppercase tracking-[.3em] text-emerald-600">Éxito</p>
        <h1 class="mb-2 text-2xl font-black text-slate-900 md:text-3xl">¡Colegio creado!</h1>
        <p class="mb-3 text-sm text-slate-600">
            Tu institución <strong class="text-indigo-700">{{ $schoolName }}</strong> ya está lista.
        </p>
        <p class="mb-8 text-sm leading-relaxed text-slate-600">
            Este es el <strong class="text-slate-900">código institucional</strong> del colegio. Compártelo con representantes o docentes que no tengan un código <strong class="text-indigo-600">DOC-</strong> personal.
            <br>
            <span class="text-slate-500">Para invitar profesores con materia asignada, usa la sección <strong class="text-slate-700">Plantel docente</strong> en tu dashboard.</span>
        </p>

        @php($defaultPin = \App\Models\Colegio::defaultPinFromInvite($inviteCode))

        {{-- Código de invitación --}}
        <div class="mx-auto mb-4 max-w-xs rounded-2xl border-2 border-indigo-100 bg-gradient-to-b from-indigo-50 to-violet-50 px-5 py-5">
            <p class="mb-2 text-xs font-bold uppercase tracking-[.2em] text-indigo-600">Código de invitación</p>
            <p id="invite-code" class="invite-code text-4xl font-black select-all md:text-5xl">{{ $inviteCode }}</p>
        </div>

        {{-- PIN --}}
        <div class="mx-auto mb-6 max-w-xs rounded-2xl border border-amber-200 bg-gradient-to-br from-amber-50 to-orange-50 px-5 py-4 text-left">
            <p class="mb-1 text-[11px] font-bold uppercase tracking-[.2em] text-amber-700">
                <i class="fa-solid fa-key mr-1"></i> PIN del colegio
            </p>
            <p class="text-sm text-amber-900/80">Para revelar códigos familiares o el código institucional:</p>
            <p class="mt-2 font-mono text-2xl font-black tracking-[.35em] text-amber-900">{{ $defaultPin }}</p>
            <p class="mt-1 text-xs text-amber-700/80">Últimos 4 dígitos de tu código. Puedes cambiarlo en el dashboard.</p>
        </div>

        {{-- Acciones --}}
        <div class="flex flex-col gap-3">
            <button type="button" id="btn-copy"
                    class="flex items-center justify-center gap-3 rounded-xl border-2 border-indigo-200 bg-indigo-50 px-5 py-3.5 text-sm font-bold text-indigo-700 shadow-sm transition hover:border-indigo-300 hover:bg-indigo-100 active:scale-[.98]">
                <i class="fa-solid fa-copy text-base" id="copy-icon"></i>
                <span id="copy-label">Copiar código</span>
            </button>

            <a href="https://wa.me/?text={{ urlencode('¡Hola! Te invito a unirte a nuestro colegio ' . $schoolName . ' en AulaSync. Usa este código para registrarte: ' . $inviteCode) }}"
               target="_blank"
               rel="noopener noreferrer"
               class="flex items-center justify-center gap-3 rounded-xl border border-emerald-300 bg-emerald-50 px-5 py-3.5 text-sm font-bold text-emerald-800 transition hover:bg-emerald-100 hover:shadow-md">
                <i class="fa-brands fa-whatsapp text-lg text-emerald-600"></i>
                Compartir por WhatsApp
            </a>
        </div>

        {{-- Pasos siguientes --}}
        <div class="mt-8 space-y-4 border-t border-slate-200 pt-6">
            <p class="text-xs font-bold uppercase tracking-[.2em] text-slate-500">Siguiente</p>
            <div class="grid gap-3 text-left text-sm">
                <div class="flex items-start gap-3 rounded-xl border border-cyan-100 bg-cyan-50/80 px-4 py-3">
                    <span class="step-num bg-cyan-500">1</span>
                    <p class="text-slate-700"><strong class="text-cyan-800">Invita docentes</strong> con códigos DOC-</p>
                </div>
                <div class="flex items-start gap-3 rounded-xl border border-violet-100 bg-violet-50/80 px-4 py-3">
                    <span class="step-num bg-violet-500">2</span>
                    <p class="text-slate-700"><strong class="text-violet-800">Crea cursos</strong> y asigna materias</p>
                </div>
                <div class="flex items-start gap-3 rounded-xl border border-emerald-100 bg-emerald-50/80 px-4 py-3">
                    <span class="step-num bg-emerald-500">3</span>
                    <p class="text-slate-700"><strong class="text-emerald-800">Matricula alumnos</strong> en la nómina</p>
                </div>
            </div>

            <a href="{{ route('director.dashboard', ['setup' => 1]) }}"
               class="inline-flex w-full items-center justify-center gap-3 rounded-xl bg-gradient-to-r from-indigo-600 to-violet-600 px-8 py-4 text-base font-bold text-white shadow-lg shadow-indigo-500/25 transition hover:from-indigo-700 hover:to-violet-700 hover:shadow-xl active:scale-[.99]">
                <i class="fa-solid fa-arrow-right"></i>
                Ir a configurar mi colegio
            </a>
        </div>
    </div>

    <script>
        (function () {
            const btn = document.getElementById('btn-copy');
            const label = document.getElementById('copy-label');
            const icon = document.getElementById('copy-icon');
            const code = document.getElementById('invite-code')?.textContent?.trim() || '';

            btn?.addEventListener('click', async function () {
                try {
                    await navigator.clipboard.writeText(code);
                } catch {
                    const range = document.createRange();
                    range.selectNode(document.getElementById('invite-code'));
                    window.getSelection().removeAllRanges();
                    window.getSelection().addRange(range);
                    document.execCommand('copy');
                    window.getSelection().removeAllRanges();
                }

                label.textContent = '¡Copiado!';
                icon.className = 'fa-solid fa-check text-base text-emerald-600';
                btn.classList.remove('border-indigo-200', 'bg-indigo-50', 'text-indigo-700');
                btn.classList.add('border-emerald-300', 'bg-emerald-50', 'text-emerald-800');

                setTimeout(function () {
                    label.textContent = 'Copiar código';
                    icon.className = 'fa-solid fa-copy text-base';
                    btn.classList.add('border-indigo-200', 'bg-indigo-50', 'text-indigo-700');
                    btn.classList.remove('border-emerald-300', 'bg-emerald-50', 'text-emerald-800');
                }, 2500);
            });
        })();
    </script>
</body>
</html>
