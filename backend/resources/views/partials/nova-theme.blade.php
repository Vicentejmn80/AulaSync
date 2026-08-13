<style>
    :root {
        --nova-deep: #F1F5F9;
        --nova-dark: #FFFFFF;
        --nova-medium: #F8FAFC;
        --nova-light: #F1F5F9;
        --nova-violet: #8B5CF6;
        --nova-fuchsia: #D946EF;
        --nova-cyan: #F472B6;
        --nova-success: #22C55E;
        --nova-warning: #F59E0B;
        --nova-gradient: linear-gradient(135deg, #8B5CF6 0%, #D946EF 52%, #F472B6 100%);
        --nova-glass: rgba(0, 0, 0, 0.02);
        --nova-glass-border: rgba(216, 70, 239, 0.13);
        --nova-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.04), 0 1px 2px -1px rgba(0, 0, 0, 0.06);
        --text-primary: #0F172A;
        --text-secondary: #475569;
        --text-tertiary: #94A3B8;
        --text-inverse: #FFFFFF;
        --bg-primary: #F1F5F9;
        --bg-secondary: #FFFFFF;
        --bg-tertiary: #F8FAFC;
        --bg-card: rgba(255, 255, 255, 0.92);
    }

    html.dark {
        --nova-deep: #060B18;
        --nova-dark: #0C1225;
        --nova-medium: #11182F;
        --nova-light: #192140;
        --nova-violet: #A855F7;
        --nova-fuchsia: #EC4899;
        --nova-cyan: #F472B6;
        --nova-success: #22C55E;
        --nova-warning: #F59E0B;
        --nova-gradient: linear-gradient(135deg, #A855F7 0%, #EC4899 65%, #F472B6 100%);
        --nova-glass: rgba(255, 255, 255, 0.03);
        --nova-glass-border: rgba(236, 72, 153, 0.18);
        --nova-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        --text-primary: rgba(255, 255, 255, 0.92);
        --text-secondary: rgba(255, 255, 255, 0.58);
        --text-tertiary: rgba(255, 255, 255, 0.3);
        --text-inverse: #0C1225;
        --bg-primary: #060B18;
        --bg-secondary: #0C1225;
        --bg-tertiary: #11182F;
        --bg-card: rgba(12, 18, 37, 0.85);
    }

    .nova-bg {
        position: fixed;
        inset: 0;
        z-index: -10;
        overflow: hidden;
        background: var(--bg-primary);
    }
</style>
<script>
    (function() {
        var t = localStorage.getItem('nova-theme');
        if (!t) {
            var legacy = localStorage.getItem('sp-dark-mode');
            if (legacy === 'true') {
                localStorage.setItem('nova-theme', 'dark');
                t = 'dark';
            } else if (legacy === 'false') {
                localStorage.setItem('nova-theme', 'light');
                t = 'light';
            }
        }
        if (!t || t === 'dark') {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    })();
</script>