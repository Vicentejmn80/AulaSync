{{-- Switch unificado claro/oscuro. Persiste con applyAulaTheme + localStorage. --}}
<style>
    .aula-theme-switch {
        width: 44px;
        min-width: 44px;
        height: 44px;
        border-radius: 0.75rem;
        border: 1px solid #e2e8f0;
        background: #f8fafc;
        color: #475569;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: background-color .15s, color .15s, border-color .15s, transform .15s;
    }
    .aula-theme-switch:hover {
        background: #eef2ff;
        color: #312e81;
        border-color: #c7d2fe;
    }
    html.dark .aula-theme-switch {
        border-color: rgba(255,255,255,.2);
        background: rgba(255,255,255,.1);
        color: #c7d2fe;
    }
    html.dark .aula-theme-switch:hover {
        background: rgba(99, 102, 241, .28);
        color: #fff;
        border-color: rgba(165, 180, 252, .45);
    }
</style>
<button
    type="button"
    x-data="novaThemeToggle()"
    x-init="init()"
    @click.stop="toggle()"
    class="aula-theme-switch"
    :title="isDark ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro'"
    aria-label="Cambiar tema claro u oscuro"
>
    <i class="fa-solid text-sm" :class="isDark ? 'fa-sun' : 'fa-moon'"></i>
</button>
<script>
if (!window.novaThemeToggle) {
    window.novaThemeToggle = function () {
        return {
            isDark: document.documentElement.classList.contains('dark'),
            init() {
                this.sync();
                window.addEventListener('aula-theme-changed', () => this.sync());
            },
            sync() {
                this.isDark = document.documentElement.classList.contains('dark');
            },
            toggle() {
                const nextDark = !this.isDark;
                if (typeof window.applyAulaTheme === 'function') {
                    window.applyAulaTheme(nextDark ? 'dark' : 'light');
                } else {
                    document.documentElement.classList.toggle('dark', nextDark);
                    localStorage.setItem('nova-theme', nextDark ? 'dark' : 'light');
                    localStorage.setItem('aula-theme', nextDark ? 'dark' : 'light');
                }
                this.sync();
            },
        };
    };
}
</script>
