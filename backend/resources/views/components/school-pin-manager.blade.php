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
    <div class="director-card flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between !p-4">
        <div>
            <p class="text-[11px] font-bold uppercase tracking-[.2em] text-amber-700">PIN del colegio</p>
            <p class="mt-1 text-sm text-slate-600">
                Protege códigos familiares y el código institucional.
                @if($defaultPin)
                    PIN por defecto: <strong class="font-mono tracking-widest text-slate-900">{{ $defaultPin }}</strong>
                    <span class="text-slate-500">(últimos 4 dígitos del código {{ $colegio->invite_code }})</span>
                @endif
            </p>
        </div>
        <button type="button"
                @click="open = true"
                class="shrink-0 rounded-lg border border-amber-300 bg-amber-50 px-4 py-2 text-sm font-bold text-amber-900 transition hover:bg-amber-100">
            <i class="fa-solid fa-key mr-2"></i>Crear / cambiar PIN
        </button>
    </div>

    <div x-show="open" x-cloak class="fixed inset-0 z-[90] flex items-center justify-center p-4" @keydown.escape.window="open = false">
        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="open = false"></div>
        <div class="relative w-full max-w-md rounded-xl border border-slate-200 bg-white p-6 shadow-xl" @click.stop>
            <p class="mb-2 text-[11px] font-bold uppercase tracking-[.2em] text-indigo-600">Seguridad</p>
            <h3 class="mb-1 text-xl font-black text-slate-900">PIN del colegio</h3>
            <p class="mb-4 text-xs text-slate-600">
                Este PIN lo usas tú (y docentes) para revelar códigos sensibles 20 segundos.
                Si aún no lo cambiaste, el predeterminado es <strong class="font-mono text-slate-900" x-text="defaultPin"></strong>.
            </p>

            <label class="director-label">Nuevo PIN (4–6 dígitos)</label>
            <input type="password" inputmode="numeric" maxlength="6" x-model="newPin"
                   class="director-input mb-3 text-center font-mono text-lg tracking-[.35em]"
                   placeholder="••••">

            <label class="director-label">Confirmar PIN</label>
            <input type="password" inputmode="numeric" maxlength="6" x-model="confirmPin"
                   class="director-input mb-3 text-center font-mono text-lg tracking-[.35em]"
                   placeholder="••••">

            <p x-show="error" x-cloak class="mb-2 text-xs text-rose-600" x-text="error"></p>
            <p x-show="success" x-cloak class="mb-2 text-xs text-emerald-700" x-text="success"></p>

            <div class="mt-2 flex gap-2">
                <button type="button" @click="open = false" class="director-btn-secondary flex-1 !py-2.5 !text-sm">Cancelar</button>
                <button type="button" @click="save()" :disabled="busy" class="director-btn-primary flex-1">
                    <span x-text="busy ? 'Guardando…' : 'Guardar PIN'"></span>
                </button>
            </div>
        </div>
    </div>
</div>
@endif
