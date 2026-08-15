<div class="theme-fab" id="aula-theme-fab">
    <div class="theme-panel" id="aula-theme-panel" hidden>
        <h4>Temas</h4>
        <div id="aula-theme-list"></div>
    </div>
    <button type="button" class="theme-fab-btn" id="aula-theme-btn" title="Cambiar tema" aria-label="Cambiar tema">
        <i class="fa-solid fa-palette"></i>
    </button>
</div>
<script>
    (function () {
        const list = document.getElementById('aula-theme-list');
        const panel = document.getElementById('aula-theme-panel');
        const btn = document.getElementById('aula-theme-btn');
        if (!list || !panel || !btn || !window.AULA_THEMES) return;

        function render() {
            const current = document.documentElement.getAttribute('data-theme');
            list.innerHTML = window.AULA_THEMES.map(theme => `
                <button type="button" class="theme-option ${theme.id === current ? 'active' : ''}" data-theme-id="${theme.id}">
                    <span class="theme-dot" style="background:${theme.dot}"></span>
                    <span>${theme.label}</span>
                </button>
            `).join('');
        }

        render();
        btn.addEventListener('click', () => {
            panel.hidden = !panel.hidden;
            render();
        });
        list.addEventListener('click', (event) => {
            const option = event.target.closest('[data-theme-id]');
            if (!option) return;
            window.applyAulaTheme(option.dataset.themeId);
            render();
        });
        document.addEventListener('click', (event) => {
            if (!event.target.closest('#aula-theme-fab')) panel.hidden = true;
        });
    })();
</script>
