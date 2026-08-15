{{-- Theme system: overrides --nova-* / --bg-* / --text-* without breaking existing CSS --}}
<style>
    html, body, #hub-root, .ios-stat, .ios-panel, .course-card, .ai-command-card,
    .nav-item, .btn-primary, .btn-create, input, textarea, select, table {
        transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease, box-shadow 0.3s ease;
    }

    html[data-theme="light"] {
        --bg-primary: #F8F6F0;
        --bg-secondary: #FFFFFF;
        --bg-tertiary: #F3F1EA;
        --bg-card: #FFFFFF;
        --bg-sidebar: #FFFFFF;
        --text-primary: #2D2D3A;
        --text-secondary: #6B6B7A;
        --text-tertiary: #8A8A99;
        --nova-violet: #6C63FF;
        --nova-fuchsia: #FF6B9D;
        --nova-cyan: #FF6B9D;
        --nova-gradient: linear-gradient(135deg, #6C63FF 0%, #FF6B9D 100%);
        --nova-glass-border: #E8E6F0;
        --nova-shadow: 0 10px 28px rgba(0,0,0,0.06);
        --az-violet: #6C63FF;
        --az-fuchsia: #FF6B9D;
        --az-rose: #FF6B9D;
    }

    html[data-theme="dark"] {
        --bg-primary: #1A1A2E;
        --bg-secondary: #232340;
        --bg-tertiary: #2A2A48;
        --bg-card: #232340;
        --bg-sidebar: #232340;
        --text-primary: #E8E8F0;
        --text-secondary: #A0A0B8;
        --text-tertiary: #7A7A90;
        --nova-violet: #7B73FF;
        --nova-fuchsia: #FF7EAA;
        --nova-cyan: #FF7EAA;
        --nova-gradient: linear-gradient(135deg, #7B73FF 0%, #FF7EAA 100%);
        --nova-glass-border: #333355;
        --nova-shadow: 0 16px 40px rgba(0,0,0,0.4);
        --az-violet: #7B73FF;
        --az-fuchsia: #FF7EAA;
        --az-rose: #FF7EAA;
    }

    html[data-theme="eco"] {
        --bg-primary: #0D0D1A;
        --bg-secondary: #141428;
        --bg-tertiary: #1A1A30;
        --bg-card: #141428;
        --bg-sidebar: #121224;
        --text-primary: #D4D4E0;
        --text-secondary: #8E8EA3;
        --text-tertiary: #6A6A80;
        --nova-violet: #5B54C7;
        --nova-fuchsia: #C45A80;
        --nova-cyan: #C45A80;
        --nova-gradient: linear-gradient(135deg, #5B54C7 0%, #C45A80 100%);
        --nova-glass-border: #262640;
        --nova-shadow: 0 12px 28px rgba(0,0,0,0.5);
        --az-violet: #5B54C7;
        --az-fuchsia: #C45A80;
        --az-rose: #C45A80;
    }

    html[data-theme="ocean"] {
        --bg-primary: #F0FBFA;
        --bg-secondary: #FFFFFF;
        --bg-tertiary: #E5F7F5;
        --bg-card: #FFFFFF;
        --bg-sidebar: #FFFFFF;
        --text-primary: #1B3A4B;
        --text-secondary: #4A6B75;
        --text-tertiary: #7A9AA3;
        --nova-violet: #4ECDC4;
        --nova-fuchsia: #6C63FF;
        --nova-cyan: #4ECDC4;
        --nova-gradient: linear-gradient(135deg, #4ECDC4 0%, #6C63FF 100%);
        --nova-glass-border: #CDEDEC;
        --nova-shadow: 0 10px 28px rgba(78,205,196,0.16);
        --az-violet: #4ECDC4;
        --az-fuchsia: #6C63FF;
        --az-rose: #6C63FF;
    }

    html[data-theme="cotton"] {
        --bg-primary: #FFF5F7;
        --bg-secondary: #FFFFFF;
        --bg-tertiary: #FFE8EE;
        --bg-card: #FFFFFF;
        --bg-sidebar: #FFFFFF;
        --text-primary: #4A2C35;
        --text-secondary: #8A5A66;
        --text-tertiary: #B08A94;
        --nova-violet: #FF9A9E;
        --nova-fuchsia: #FECFEF;
        --nova-cyan: #FF9A9E;
        --nova-gradient: linear-gradient(135deg, #FF9A9E 0%, #FECFEF 100%);
        --nova-glass-border: #F8D5DC;
        --nova-shadow: 0 10px 28px rgba(255,154,158,0.18);
        --az-violet: #FF9A9E;
        --az-fuchsia: #F48FB1;
        --az-rose: #F48FB1;
    }

    html[data-theme="mint"] {
        --bg-primary: #F3FBF6;
        --bg-secondary: #FFFFFF;
        --bg-tertiary: #E4F6EC;
        --bg-card: #FFFFFF;
        --bg-sidebar: #FFFFFF;
        --text-primary: #1F3D32;
        --text-secondary: #4E7464;
        --text-tertiary: #7A9A8C;
        --nova-violet: #88D8B0;
        --nova-fuchsia: #A8E6CF;
        --nova-cyan: #88D8B0;
        --nova-gradient: linear-gradient(135deg, #A8E6CF 0%, #88D8B0 100%);
        --nova-glass-border: #CDE9D8;
        --nova-shadow: 0 10px 28px rgba(136,216,176,0.18);
        --az-violet: #3D9B74;
        --az-fuchsia: #88D8B0;
        --az-rose: #88D8B0;
    }

    html[data-theme="sunset"] {
        --bg-primary: #FFF8EE;
        --bg-secondary: #FFFFFF;
        --bg-tertiary: #FFE9D2;
        --bg-card: #FFFFFF;
        --bg-sidebar: #FFFFFF;
        --text-primary: #3D2A1B;
        --text-secondary: #7A5A3C;
        --text-tertiary: #A88460;
        --nova-violet: #FF6B6B;
        --nova-fuchsia: #FFD93D;
        --nova-cyan: #FF6B6B;
        --nova-gradient: linear-gradient(135deg, #FFD93D 0%, #FF6B6B 100%);
        --nova-glass-border: #F5D7B8;
        --nova-shadow: 0 10px 28px rgba(255,107,107,0.16);
        --az-violet: #FF6B6B;
        --az-fuchsia: #FFB020;
        --az-rose: #FF6B6B;
    }

    html[data-theme="neon"] {
        --bg-primary: #07131F;
        --bg-secondary: #0E2133;
        --bg-tertiary: #133047;
        --bg-card: #0E2133;
        --bg-sidebar: #0B1C2C;
        --text-primary: #E8F7FF;
        --text-secondary: #8EC5D8;
        --text-tertiary: #5E8FA3;
        --nova-violet: #00D2FF;
        --nova-fuchsia: #3A7BD5;
        --nova-cyan: #00D2FF;
        --nova-gradient: linear-gradient(135deg, #00D2FF 0%, #3A7BD5 100%);
        --nova-glass-border: #1C4A66;
        --nova-shadow: 0 16px 40px rgba(0,210,255,0.16);
        --az-violet: #00D2FF;
        --az-fuchsia: #3A7BD5;
        --az-rose: #3A7BD5;
    }

    .theme-fab {
        position: fixed;
        top: 18px;
        right: 18px;
        z-index: 140;
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 8px;
    }

    .theme-fab-btn {
        width: 46px;
        height: 46px;
        border: 0;
        border-radius: 50%;
        background: var(--nova-gradient);
        color: #fff;
        box-shadow: 0 10px 24px rgba(0,0,0,0.18);
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .theme-panel {
        width: 260px;
        background: var(--bg-card);
        color: var(--text-primary);
        border: 1px solid var(--nova-glass-border);
        border-radius: 18px;
        box-shadow: var(--nova-shadow);
        padding: 12px;
    }

    .theme-panel h4 {
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: .08em;
        color: var(--text-tertiary);
        margin: 0 0 10px;
    }

    .theme-option {
        width: 100%;
        display: flex;
        align-items: center;
        gap: 10px;
        border: 0;
        background: transparent;
        color: inherit;
        padding: 8px;
        border-radius: 12px;
        cursor: pointer;
        font-size: 13px;
        font-weight: 600;
        text-align: left;
    }

    .theme-option:hover,
    .theme-option.active { background: color-mix(in srgb, var(--nova-violet) 12%, transparent); }

    .theme-dot {
        width: 16px;
        height: 16px;
        border-radius: 50%;
        flex-shrink: 0;
        box-shadow: inset 0 0 0 1px rgba(0,0,0,0.08);
    }
</style>
<script>
    window.AULA_THEMES = [
        { id: 'light', label: 'Claro', dot: 'linear-gradient(135deg,#F8F6F0,#6C63FF)', dark: false },
        { id: 'dark', label: 'Oscuro', dot: 'linear-gradient(135deg,#1A1A2E,#7B73FF)', dark: true },
        { id: 'eco', label: 'Ahorro de energía', dot: 'linear-gradient(135deg,#0D0D1A,#5B54C7)', dark: true },
        { id: 'ocean', label: 'Ocean Breeze', dot: 'linear-gradient(135deg,#4ECDC4,#6C63FF)', dark: false },
        { id: 'cotton', label: 'Cotton Candy', dot: 'linear-gradient(135deg,#FF9A9E,#FECFEF)', dark: false },
        { id: 'mint', label: 'Mint Wave', dot: 'linear-gradient(135deg,#A8E6CF,#88D8B0)', dark: false },
        { id: 'sunset', label: 'Sunset Glow', dot: 'linear-gradient(135deg,#FFD93D,#FF6B6B)', dark: false },
        { id: 'neon', label: 'Neon Dream', dot: 'linear-gradient(135deg,#00D2FF,#3A7BD5)', dark: true },
    ];

    window.applyAulaTheme = function (themeId) {
        const theme = (window.AULA_THEMES || []).find(t => t.id === themeId) || window.AULA_THEMES[0];
        document.documentElement.setAttribute('data-theme', theme.id);
        document.documentElement.classList.toggle('dark', !!theme.dark);
        localStorage.setItem('aula-theme', theme.id);
        localStorage.setItem('nova-theme', theme.dark ? 'dark' : 'light');
        window.dispatchEvent(new CustomEvent('aula-theme-changed', { detail: theme }));
    };

    (function bootTheme() {
        let themeId = localStorage.getItem('aula-theme');
        if (!themeId) {
            const legacy = localStorage.getItem('nova-theme');
            themeId = legacy === 'dark' ? 'dark' : 'light';
        }
        window.applyAulaTheme(themeId);
    })();
</script>
