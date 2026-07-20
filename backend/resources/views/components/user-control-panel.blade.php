<style>
    .ucp-btn {
        border:1px solid #e2e8f0;
        background:#f8fafc;
        color:#475569;
    }
    .ucp-btn:hover { background:#eef2ff; color:#1f2937; }
    html.dark .ucp-btn {
        border-color: rgba(255,255,255,.2);
        background: rgba(255,255,255,.1);
        color: #f0abfc;
    }
    html.dark .ucp-btn:hover { background: rgba(255,255,255,.2); color:#fff; }
    .ucp-dropdown {
        position:absolute; right:0; top:2.5rem; width:min(22rem, 88vw); max-height:24rem; overflow:auto;
        border:1px solid #e2e8f0; border-radius:1rem; background:#ffffff; color:#0f172a;
        box-shadow:0 22px 50px rgba(15,23,42,.12); z-index:9999;
    }
    html.dark .ucp-dropdown {
        border-color: rgba(148,163,184,.25);
        background:#0f172a;
        color:#e2e8f0;
        box-shadow:0 22px 50px rgba(15,23,42,.35);
    }
    .ucp-dropdown-header {
        border-bottom:1px solid #e2e8f0;
    }
    html.dark .ucp-dropdown-header { border-bottom-color: rgba(148,163,184,.18); }
    .ucp-dropdown-title { font-size:.875rem; font-weight:700; color:#0f172a; }
    html.dark .ucp-dropdown-title { color:#f8fafc; }
    .ucp-dropdown-action { font-size:.75rem; font-weight:600; color:#0891b2; }
    html.dark .ucp-dropdown-action { color:#67e8f9; }
    .ucp-dropdown-empty { color:#64748b; }
    html.dark .ucp-dropdown-empty { color:#94a3b8; }
    .ucp-notification { display:block; padding:.75rem .9rem; border-top:1px solid #e2e8f0; text-decoration:none; color:inherit; }
    html.dark .ucp-notification { border-top-color: rgba(148,163,184,.18); }
    .ucp-notification:hover { background:#f8fafc; }
    html.dark .ucp-notification:hover { background:rgba(255,255,255,.06); }
    .ucp-notification.unread { background:#ecfeff; }
    html.dark .ucp-notification.unread { background:rgba(14,165,233,.08); }
    .ucp-notification-title { font-size:.875rem; font-weight:700; color:#0f172a; line-height:1.35; }
    html.dark .ucp-notification-title { color:#f8fafc; }
    .ucp-notification-message { margin-top:.25rem; font-size:.75rem; line-height:1.45; color:#475569; }
    html.dark .ucp-notification-message { color:#cbd5e1; }
    .ucp-notification-time { margin-top:.25rem; font-size:.625rem; color:#64748b; }
    html.dark .ucp-notification-time { color:#94a3b8; }
    .ucp-unread-dot { color:#0891b2; }
    html.dark .ucp-unread-dot { color:#67e8f9; }
</style>

<div x-data="userControlPanel()" x-init="init()" class="relative flex items-center gap-2">
    <button
        @click="toggleNotifications()"
        title="Notificaciones"
        class="ucp-btn relative w-8 h-8 rounded-lg flex items-center justify-center transition"
    >
        <i class="fa-solid fa-bell text-xs"></i>
        <span x-show="unreadCount > 0"
              x-text="unreadCount > 99 ? '99+' : unreadCount"
              class="absolute -right-1 -top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-cyan-400 px-1 text-[9px] font-black text-cyan-950"
              x-cloak></span>
    </button>

    <div x-show="showNotifications"
         @click.outside="showNotifications = false"
         x-cloak
         x-transition.opacity.scale.origin.top.right
         class="ucp-dropdown">
        <div class="ucp-dropdown-header flex items-center justify-between px-4 py-3">
            <span class="ucp-dropdown-title">Notificaciones</span>
            <button type="button" @click="markAllNotificationsRead()" class="ucp-dropdown-action hover:opacity-80">
                Marcar leídas
            </button>
        </div>
        <template x-if="notifications.length === 0">
            <div class="ucp-dropdown-empty px-4 py-6 text-center text-sm">Sin notificaciones</div>
        </template>
        <template x-for="n in notifications" :key="n.id">
            <a :href="notificationLink(n)"
               @click="markAsRead(n.id); showNotifications = false"
               class="ucp-notification"
               :class="{ unread: !n.read_at }">
                <div class="flex items-start gap-2">
                    <i x-show="!n.read_at" class="ucp-unread-dot fa-solid fa-circle mt-1.5 text-[8px]"></i>
                    <div class="min-w-0">
                        <p class="ucp-notification-title" x-text="n.title"></p>
                        <p class="ucp-notification-message" x-text="n.message || ''"></p>
                        <p class="ucp-notification-time" x-text="n.created_at ? new Date(n.created_at).toLocaleString('es') : ''"></p>
                    </div>
                </div>
            </a>
        </template>
    </div>

    <button
        @click="toggleTheme()"
        :title="isDark ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro'"
        class="ucp-btn w-8 h-8 rounded-lg flex items-center justify-center transition"
    >
        <i :class="isDark ? 'fa-solid fa-sun' : 'fa-solid fa-moon'" class="text-xs"></i>
    </button>

    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button
            type="submit"
            class="ucp-btn inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition"
            title="Cerrar sesión"
        >
            <i class="fa-solid fa-right-from-bracket text-[10px]"></i>
            Salir
        </button>
    </form>
</div>

<script>
if (!window.userControlPanel) {
    window.userControlPanel = function () {
        return {
            isDark: false,
            notifications: [],
            unreadCount: 0,
            showNotifications: false,
            _pollTimer: null,
            _knownUnreadCount: 0,
            init() {
                const saved = localStorage.getItem('nova-theme');
                this.isDark = saved ? saved === 'dark' : true;
                this.applyTheme();
                this.loadNotifications();
                this.startNotificationPolling();
            },
            startNotificationPolling() {
                if (this._pollTimer) return;

                const pollMs = 8000;
                const poll = () => {
                    if (document.hidden || this.showNotifications) return;
                    this.loadNotifications();
                };

                this._pollTimer = setInterval(poll, pollMs);
                document.addEventListener('visibilitychange', () => {
                    if (!document.hidden) {
                        this.loadNotifications();
                    }
                });
            },
            toggleTheme() {
                this.isDark = !this.isDark;
                localStorage.setItem('nova-theme', this.isDark ? 'dark' : 'light');
                this.applyTheme();
            },
            applyTheme() {
                document.documentElement.classList.toggle('dark', this.isDark);
            },
            async loadNotifications() {
                try {
                    const res = await fetch('/notifications', { headers: { Accept: 'application/json' } });
                    const data = await res.json();
                    if (data.success) {
                        const nextUnread = data.unread_count || 0;
                        const latest = (data.notifications || [])[0];
                        if (nextUnread > this._knownUnreadCount && latest && !latest.read_at) {
                            window.dispatchEvent(new CustomEvent('ai-toast', {
                                detail: {
                                    message: latest.title,
                                    type: 'info',
                                    icon: 'fa-bell',
                                },
                            }));
                        }
                        this._knownUnreadCount = nextUnread;
                        this.notifications = data.notifications || [];
                        this.unreadCount = nextUnread;
                    }
                } catch (e) {
                    this.notifications = [];
                }
            },
            toggleNotifications() {
                this.showNotifications = !this.showNotifications;
                if (this.showNotifications) {
                    this.loadNotifications();
                }
            },
            async markAsRead(id) {
                try {
                    const res = await fetch(`/notifications/${id}/read`, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                            Accept: 'application/json',
                        },
                    });
                    const data = await res.json();
                    if (data.success) {
                        this.unreadCount = data.unread_count || 0;
                        const notification = this.notifications.find((item) => item.id === id);
                        if (notification) notification.read_at = new Date().toISOString();
                    }
                } catch (e) {}
            },
            async markAllNotificationsRead() {
                try {
                    const res = await fetch('/notifications/read-all', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                            Accept: 'application/json',
                        },
                    });
                    const data = await res.json();
                    if (data.success) {
                        this.unreadCount = 0;
                        this.notifications.forEach((item) => item.read_at = new Date().toISOString());
                    }
                } catch (e) {}
            },
            notificationLink(notification) {
                return notification.link || '/dashboard';
            },
        };
    };

    const saved = localStorage.getItem('nova-theme');
    if (!saved || saved === 'dark') {
        document.documentElement.classList.add('dark');
    } else {
        document.documentElement.classList.remove('dark');
    }
}
</script>
