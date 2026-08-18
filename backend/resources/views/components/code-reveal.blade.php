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
            class="select-all min-w-[7.5rem] rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-center font-mono text-xs font-bold tracking-widest text-indigo-700"
            x-text="displayed"
        ></code>
        <button
            type="button"
            @click="openPin()"
            class="flex h-8 items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-2.5 text-[11px] font-semibold text-slate-700 transition hover:border-indigo-400 hover:text-indigo-700"
            :disabled="busy"
        >
            <i class="fa-solid text-xs" :class="unlocked ? 'fa-lock-open text-emerald-600' : 'fa-lock'"></i>
            <span x-text="unlocked ? (secondsLeft + 's') : 'Ver'"></span>
        </button>
        <button
            type="button"
            x-show="unlocked"
            x-cloak
            @click="copyCode()"
            class="flex h-8 w-8 items-center justify-center rounded-lg border border-slate-300 bg-white text-slate-500 transition hover:border-indigo-400 hover:text-indigo-700"
            title="Copiar"
        >
            <i class="fa-solid text-xs" :class="copied ? 'fa-check text-emerald-400' : 'fa-copy'"></i>
        </button>
    </div>
    <p x-show="error && !showModal" x-cloak class="text-[11px] text-rose-600" x-text="error"></p>

    <div
        x-show="showModal"
        x-cloak
        class="fixed inset-0 z-[80] flex items-center justify-center p-4"
        @keydown.escape.window="showModal = false"
    >
        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="showModal = false"></div>
        <div class="relative w-full max-w-sm rounded-xl border border-slate-200 bg-white p-6 shadow-xl" @click.stop>
            <p class="mb-2 text-[11px] font-bold uppercase tracking-[.2em] text-indigo-600">Autenticación</p>
            <h4 class="mb-1 text-lg font-bold text-slate-900">Ingresa el PIN del colegio</h4>
            <p class="mb-3 text-xs text-slate-600">El código se mostrará solo 20 segundos y luego se bloqueará de nuevo.</p>
            @if($isDirector && $pinHint)
                <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900">
                    <p class="font-semibold text-amber-800">Si nunca cambiaste el PIN, es:</p>
                    <p class="mt-1 font-mono text-lg tracking-[.3em] text-slate-900">{{ $pinHint }}</p>
                    <p class="mt-1 text-amber-800/80">Últimos 4 dígitos del código institucional. Para crear uno nuevo: Alumnos → PIN del colegio.</p>
                </div>
            @elseif($isDirector)
                <p class="mb-4 text-xs text-slate-600">Si no lo recuerdas, configúralo o restablécelo en <strong class="text-slate-900">PIN del colegio</strong>.</p>
            @endif
            <input
                type="password"
                inputmode="numeric"
                maxlength="6"
                x-model="pin"
                @keydown.enter="verify()"
                placeholder="PIN de 4 a 6 dígitos"
                class="w-full rounded-lg border border-slate-300 bg-white px-4 py-3 text-center font-mono text-lg tracking-[.35em] text-slate-900 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200"
                x-ref="pinInput"
            >
            <p x-show="error" x-cloak class="mt-2 text-xs text-rose-600" x-text="error"></p>
            <div class="mt-4 flex gap-2">
                <button type="button" @click="showModal = false" class="flex-1 rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancelar</button>
                <button type="button" @click="verify()" :disabled="busy || !pin" class="flex-1 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-indigo-700 disabled:opacity-50">
                    <span x-text="busy ? 'Verificando…' : 'Desbloquear'"></span>
                </button>
            </div>
        </div>
    </div>
</div>
