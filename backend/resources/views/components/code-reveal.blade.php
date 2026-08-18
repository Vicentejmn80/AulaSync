@props([
    'type' => 'school', // school|family
    'studentId' => null,
    'label' => 'Código',
    'masked' => '••••••••',
    'pinHint' => null, // solo director: pista del PIN por defecto
])

@php
    $verifyUrl = route('codes.reveal');
    $isDirector = auth()->user()?->role === 'director';
@endphp

<div
    x-data="{
        type: @js($type),
        studentId: @js($studentId),
        masked: @js($masked),
        verifyUrl: @js($verifyUrl),
        pinHint: @js($pinHint),
        displayed: @js($masked),
        unlocked: false,
        showModal: false,
        pin: '',
        error: '',
        busy: false,
        copied: false,
        secondsLeft: 0,
        timer: null,
        codeValue: '',
        openPin() {
            if (this.unlocked) return;
            this.error = '';
            this.pin = '';
            this.showModal = true;
            this.$nextTick(() => this.$refs.pinInput?.focus());
        },
        async verify() {
            if (!this.pin || this.busy) return;
            this.busy = true;
            this.error = '';
            try {
                const res = await fetch(this.verifyUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                    },
                    body: JSON.stringify({
                        pin: this.pin,
                        type: this.type,
                        student_id: this.studentId,
                    }),
                });
                const json = await res.json();
                if (!res.ok || !json.ok) {
                    this.error = json.error || 'No se pudo verificar el PIN.';
                    return;
                }
                this.codeValue = json.code;
                this.displayed = json.code;
                this.unlocked = true;
                this.showModal = false;
                this.secondsLeft = json.ttl_seconds || 20;
                if (this.timer) clearInterval(this.timer);
                this.timer = setInterval(() => {
                    this.secondsLeft -= 1;
                    if (this.secondsLeft <= 0) this.lock();
                }, 1000);
            } catch (e) {
                this.error = 'Error de conexión. Intenta de nuevo.';
            } finally {
                this.busy = false;
                this.pin = '';
            }
        },
        lock() {
            if (this.timer) clearInterval(this.timer);
            this.timer = null;
            this.unlocked = false;
            this.secondsLeft = 0;
            this.displayed = this.masked;
            this.codeValue = '';
        },
        async copyCode() {
            if (!this.codeValue) return;
            try {
                await navigator.clipboard.writeText(this.codeValue);
                this.copied = true;
                setTimeout(() => this.copied = false, 1500);
            } catch (_) {}
        },
    }"
    class="inline-flex flex-col gap-1"
>
    <div class="inline-flex items-center gap-2">
        <code
            class="select-all rounded-lg border border-cyan-500/20 bg-cyan-500/10 px-3 py-1.5 font-mono text-xs font-bold tracking-widest text-cyan-300 min-w-[7.5rem] text-center"
            x-text="displayed"
        ></code>
        <button
            type="button"
            @click="openPin()"
            class="flex h-8 items-center gap-1.5 rounded-lg border border-white/10 px-2.5 text-[11px] font-semibold text-slate-300 transition hover:border-cyan-500/40 hover:text-cyan-300"
            :disabled="busy"
        >
            <i class="fa-solid text-xs" :class="unlocked ? 'fa-lock-open text-emerald-400' : 'fa-lock'"></i>
            <span x-text="unlocked ? (secondsLeft + 's') : 'Ver'"></span>
        </button>
        <button
            type="button"
            x-show="unlocked"
            x-cloak
            @click="copyCode()"
            class="flex h-8 w-8 items-center justify-center rounded-lg border border-white/10 text-slate-400 transition hover:border-cyan-500/40 hover:text-cyan-300"
            title="Copiar"
        >
            <i class="fa-solid text-xs" :class="copied ? 'fa-check text-emerald-400' : 'fa-copy'"></i>
        </button>
    </div>
    <p x-show="error && !showModal" x-cloak class="text-[11px] text-rose-400" x-text="error"></p>

    <div
        x-show="showModal"
        x-cloak
        class="fixed inset-0 z-[80] flex items-center justify-center p-4"
        @keydown.escape.window="showModal = false"
    >
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="showModal = false"></div>
        <div class="relative w-full max-w-sm rounded-3xl border border-white/10 bg-slate-900 p-6 shadow-2xl" @click.stop>
            <p class="text-[11px] font-bold uppercase tracking-[.2em] text-cyan-300 mb-2">Autenticación</p>
            <h4 class="text-lg font-bold text-white mb-1">Ingresa el PIN del colegio</h4>
            <p class="text-xs text-slate-400 mb-3">El código se mostrará solo 20 segundos y luego se bloqueará de nuevo.</p>
            @if($isDirector && $pinHint)
                <div class="mb-4 rounded-xl border border-amber-400/25 bg-amber-400/10 px-3 py-2 text-xs text-amber-100">
                    <p class="font-semibold text-amber-200">Si nunca cambiaste el PIN, es:</p>
                    <p class="mt-1 font-mono text-lg tracking-[.3em] text-white">{{ $pinHint }}</p>
                    <p class="mt-1 text-amber-100/80">Últimos 4 dígitos del código institucional. Para crear uno nuevo: Dashboard → PIN del colegio.</p>
                </div>
            @elseif($isDirector)
                <p class="mb-4 text-xs text-slate-400">Si no lo recuerdas, configúralo o restablécelo en el dashboard → <strong class="text-slate-200">PIN del colegio</strong>.</p>
            @endif
            <input
                type="password"
                inputmode="numeric"
                maxlength="6"
                x-model="pin"
                @keydown.enter="verify()"
                placeholder="PIN de 4 a 6 dígitos"
                class="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-3 text-center font-mono text-lg tracking-[.35em] text-white outline-none focus:border-cyan-400/50"
                x-ref="pinInput"
            >
            <p x-show="error" x-cloak class="mt-2 text-xs text-rose-400" x-text="error"></p>
            <div class="mt-4 flex gap-2">
                <button type="button" @click="showModal = false" class="flex-1 rounded-xl border border-white/10 px-4 py-2.5 text-sm font-semibold text-slate-300 hover:bg-white/5">Cancelar</button>
                <button type="button" @click="verify()" :disabled="busy || !pin" class="flex-1 rounded-xl bg-gradient-to-r from-violet-600 to-cyan-500 px-4 py-2.5 text-sm font-bold text-white disabled:opacity-50">
                    <span x-text="busy ? 'Verificando…' : 'Desbloquear'"></span>
                </button>
            </div>
        </div>
    </div>
</div>
