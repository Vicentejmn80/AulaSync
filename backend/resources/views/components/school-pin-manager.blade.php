{{-- Panel para que el director cree / cambie el PIN del colegio --}}
@php
    $colegio = $colegio ?? auth()->user()?->colegio;
    $defaultPin = $colegio ? \App\Models\Colegio::defaultPinFromInvite($colegio->invite_code) : null;
    $hasCustomPin = filled($colegio?->codes_pin);
@endphp

@if(auth()->user()?->role === 'director' && $colegio)
<div
    x-data="{
        open: false,
        newPin: '',
        confirmPin: '',
        currentPin: '',
        busy: false,
        error: '',
        success: '',
        defaultPin: @js($defaultPin),
        async save() {
            this.error = '';
            this.success = '';
            if (!/^\d{4,6}$/.test(this.newPin)) {
                this.error = 'El nuevo PIN debe tener entre 4 y 6 dígitos.';
                return;
            }
            if (this.newPin !== this.confirmPin) {
                this.error = 'Los PIN no coinciden.';
                return;
            }
            this.busy = true;
            try {
                const res = await fetch(@js(route('codes.pin.update')), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                    },
                    body: JSON.stringify({
                        new_pin: this.newPin,
                        current_pin: this.currentPin || null,
                        reset: true,
                    }),
                });
                const json = await res.json();
                if (!res.ok || !json.ok) {
                    this.error = json.error || 'No se pudo guardar el PIN.';
                    return;
                }
                this.success = json.message || 'PIN actualizado.';
                this.newPin = '';
                this.confirmPin = '';
                this.currentPin = '';
                setTimeout(() => { this.open = false; this.success = ''; }, 1800);
            } catch (e) {
                this.error = 'Error de conexión.';
            } finally {
                this.busy = false;
            }
        }
    }"
    class="w-full"
>
    <div class="flex flex-col gap-3 rounded-2xl border border-white/10 bg-white/[.04] p-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="text-[11px] font-bold uppercase tracking-[.2em] text-amber-200">PIN del colegio</p>
            <p class="mt-1 text-sm text-slate-300">
                Protege códigos familiares y el código institucional.
                @if($defaultPin)
                    PIN por defecto: <strong class="font-mono tracking-widest text-white">{{ $defaultPin }}</strong>
                    <span class="text-slate-500">(últimos 4 dígitos del código {{ $colegio->invite_code }})</span>
                @endif
            </p>
        </div>
        <button type="button"
                @click="open = true"
                class="shrink-0 rounded-xl border border-amber-400/30 bg-amber-400/10 px-4 py-2 text-sm font-bold text-amber-100 transition hover:bg-amber-400/20">
            <i class="fa-solid fa-key mr-2"></i>Crear / cambiar PIN
        </button>
    </div>

    <div x-show="open" x-cloak class="fixed inset-0 z-[90] flex items-center justify-center p-4" @keydown.escape.window="open = false">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="open = false"></div>
        <div class="relative w-full max-w-md rounded-3xl border border-white/10 bg-slate-900 p-6 shadow-2xl" @click.stop>
            <p class="text-[11px] font-bold uppercase tracking-[.2em] text-cyan-300 mb-2">Seguridad</p>
            <h3 class="text-xl font-black text-white mb-1">PIN del colegio</h3>
            <p class="text-xs text-slate-400 mb-4">
                Este PIN lo usas tú (y docentes) para revelar códigos sensibles 20 segundos.
                Si aún no lo cambiaste, el predeterminado es <strong class="font-mono text-white" x-text="defaultPin"></strong>.
            </p>

            <label class="mb-1 block text-xs font-semibold text-slate-400">Nuevo PIN (4–6 dígitos)</label>
            <input type="password" inputmode="numeric" maxlength="6" x-model="newPin"
                   class="mb-3 w-full rounded-xl border border-white/10 bg-white/5 px-4 py-3 text-center font-mono text-lg tracking-[.35em] text-white outline-none focus:border-cyan-400/50"
                   placeholder="••••">

            <label class="mb-1 block text-xs font-semibold text-slate-400">Confirmar PIN</label>
            <input type="password" inputmode="numeric" maxlength="6" x-model="confirmPin"
                   class="mb-3 w-full rounded-xl border border-white/10 bg-white/5 px-4 py-3 text-center font-mono text-lg tracking-[.35em] text-white outline-none focus:border-cyan-400/50"
                   placeholder="••••">

            <p x-show="error" x-cloak class="mb-2 text-xs text-rose-400" x-text="error"></p>
            <p x-show="success" x-cloak class="mb-2 text-xs text-emerald-300" x-text="success"></p>

            <div class="mt-2 flex gap-2">
                <button type="button" @click="open = false" class="flex-1 rounded-xl border border-white/10 px-4 py-2.5 text-sm font-semibold text-slate-300 hover:bg-white/5">Cancelar</button>
                <button type="button" @click="save()" :disabled="busy" class="flex-1 rounded-xl bg-gradient-to-r from-violet-600 to-cyan-500 px-4 py-2.5 text-sm font-bold text-white disabled:opacity-50">
                    <span x-text="busy ? 'Guardando…' : 'Guardar PIN'"></span>
                </button>
            </div>
        </div>
    </div>
</div>
@endif
