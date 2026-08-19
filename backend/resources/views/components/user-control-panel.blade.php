<style>
    [x-cloak] { display: none !important; }
    .ucp-btn {
        border:1px solid #e2e8f0;
        background:#f8fafc;
        color:#475569;
        min-width: 44px;
        min-height: 44px;
    }
    .ucp-btn:hover { background:#eef2ff; color:#1f2937; }
    html.dark .ucp-btn {
        border-color: rgba(255,255,255,.2);
        background: rgba(255,255,255,.1);
        color: #f0abfc;
    }
    html.dark .ucp-btn:hover { background: rgba(255,255,255,.2); color:#fff; }
    .ucp-dropdown {
        position: fixed;
        right: 1rem;
        top: 4.5rem;
        width: min(22rem, calc(100vw - 2rem));
        max-height: min(24rem, 70dvh);
        overflow: auto;
        border: 1px solid #e2e8f0;
        border-radius: 1rem;
        background: #ffffff;
        color: #0f172a;
        box-shadow: 0 22px 50px rgba(15,23,42,.22);
        z-index: 10060;
        -webkit-overflow-scrolling: touch;
        overscroll-behavior: contain;
    }
    [x-cloak] .ucp-dropdown,
    .ucp-dropdown[x-cloak] { display: none !important; }
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

<div x-data="userControlPanel()" x-init="init()" class="relative z-[200] flex items-center gap-2 overflow-visible">
    @include('components.theme-toggle')
    <button
        type="button"
        @click.stop="open = !open; if (open) loadNotifications()"
        title="Notificaciones"
        class="ucp-btn relative flex items-center justify-center rounded-lg transition"
    >
        <i class="fa-solid fa-bell text-xs"></i>
        <span x-show="unreadCount > 0"
              x-text="unreadCount > 99 ? '99+' : unreadCount"
              class="pointer-events-none absolute -right-1 -top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-cyan-400 px-1 text-[9px] font-black text-cyan-950"
              x-cloak></span>
    </button>

    <div x-show="open"
         x-cloak
         x-transition.opacity.scale.origin.top.right
         @click.outside="open = false"
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
            <a href="#"
               @click.prevent="handleNotificationClick(n)"
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
            open: false,
            _pollTimer: null,
            _knownUnreadCount: 0,
            init() {
                this.isDark = document.documentElement.classList.contains('dark');
                this.loadNotifications();
                this.startNotificationPolling();
            },
            startNotificationPolling() {
                if (this._pollTimer) return;

                const pollMs = 8000;
                const poll = () => {
                    if (document.hidden || this.open) return;
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
                if (typeof window.applyAulaTheme === 'function') {
                    window.applyAulaTheme(this.isDark ? 'light' : 'dark');
                } else {
                    document.documentElement.classList.toggle('dark');
                    localStorage.setItem('nova-theme', document.documentElement.classList.contains('dark') ? 'dark' : 'light');
                    localStorage.setItem('aula-theme', localStorage.getItem('nova-theme'));
                }
                this.isDark = document.documentElement.classList.contains('dark');
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
                this.open = !this.open;
                if (this.open) {
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
            async handleNotificationClick(notification) {
                await this.markAsRead(notification.id);
                this.open = false;

                const href = this.notificationLink(notification);
                try {
                    const url = new URL(href, window.location.origin);
                    const activityId = Number(
                        url.searchParams.get('open_activity')
                        || url.searchParams.get('activity')
                        || 0
                    );
                    if (activityId > 0) {
                        window.dispatchEvent(new CustomEvent('open-activity-modal', {
                            detail: { id: activityId },
                        }));
                        if (!window.location.pathname.includes('/teacher/hub')) {
                            window.location.href = url.pathname + url.search;
                        }
                        return;
                    }
                } catch (e) {}

                window.location.href = href;
            },
        };
    };
}
</script>
