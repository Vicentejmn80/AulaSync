{{--
  AI Assistant Bubble v3 — AulaSync Assistant
  Diseño innovador con gradientes, microinteracciones y experiencia premium
--}}
<style>
    /* ─────────────── NOVA AI BUBBLE v3 ─────────────── */
    .nova-ai-container {
        position: fixed;
        bottom: 24px;
        right: 24px;
        z-index: 9999;
        pointer-events: none;
    }

    .nova-ai-container .nova-ai-trigger,
    .nova-ai-container .nova-ai-panel {
        pointer-events: auto;
    }

    /* Botón flotante principal */
    .nova-ai-trigger {
        width: 68px;
        height: 68px;
        background: transparent;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        box-shadow: 0 10px 30px -8px rgba(124, 58, 237, 0.45),
                    0 5px 15px -5px rgba(236, 72, 153, 0.32);
        transition: all 0.3s cubic-bezier(0.2, 0.9, 0.4, 1.1);
        position: relative;
        overflow: visible;
    }

    .nova-ai-trigger::before {
        content: '';
        position: absolute;
        inset: -3px;
        background: linear-gradient(135deg, rgba(124, 58, 237, 0.18), rgba(196, 85, 237, 0.15), rgba(236, 72, 153, 0.18));
        border-radius: 50%;
        z-index: -1;
        opacity: 0;
        transition: opacity 0.3s ease;
    }

    .nova-ai-trigger:hover::before {
        opacity: 1;
    }

    .nova-ai-trigger:hover {
        transform: scale(1.08);
        box-shadow: 0 15px 40px -10px rgba(124, 58, 237, 0.55),
                    0 8px 20px -6px rgba(236, 72, 153, 0.42);
    }

    .nova-ai-trigger:active {
        transform: scale(0.98);
    }

    .nova-ai-trigger.listening {
        animation: pulse-glow 1.2s ease-in-out infinite;
    }

    .nova-ai-trigger.listening .nova-monkey-avatar {
        filter: drop-shadow(0 0 0 2px rgba(239, 68, 68, 0.9)) drop-shadow(0 6px 16px rgba(239, 68, 68, 0.45));
    }

    @keyframes pulse-glow {
        0%, 100% { box-shadow: 0 8px 24px rgba(239, 68, 68, 0.4); }
        50% { box-shadow: 0 8px 32px rgba(239, 68, 68, 0.8), 0 0 0 8px rgba(239, 68, 68, 0.15); }
    }

    /*
     * Avatar Nova con micro-interacción:
     * - Cerrado: emoji leyendo (lector).
     * - Abierto: emoji mirando fijamente (foco).
     * Recorte circular para evitar burbuja redundante.
     */
    .nova-monkey-avatar {
        --nova-avatar-size: 48px;
        --nova-avatar-scale: 130%;
        position: relative;
        width: var(--nova-avatar-size);
        height: var(--nova-avatar-size);
        flex-shrink: 0;
        border-radius: 50%;
        overflow: visible;
        background: transparent;
        filter: drop-shadow(0 6px 16px rgba(139, 92, 246, 0.4)) drop-shadow(0 3px 8px rgba(236, 72, 153, 0.3));
        transition: transform 0.3s ease-in-out, filter 0.3s ease-in-out;
    }

    .nova-monkey-avatar--trigger {
        --nova-avatar-size: 60px;
        --nova-avatar-scale: 135%;
        filter: drop-shadow(0 8px 20px rgba(139, 92, 246, 0.5)) drop-shadow(0 4px 10px rgba(236, 72, 153, 0.4));
    }

    .nova-monkey-avatar__sprite {
        position: absolute;
        inset: -20%;
        background-repeat: no-repeat;
        background-size: var(--nova-avatar-scale);
        background-position: center center;
        transition: opacity 0.3s ease-in-out, transform 0.3s ease-in-out;
        transform: translate(0, 0) scale(1.05);
        will-change: opacity, transform;
        pointer-events: none;
    }

    .nova-monkey-avatar__sprite--closed {
        background-image: url('/images/emoji leyendo sin fondo.png');
        opacity: 1;
    }

    .nova-monkey-avatar__sprite--open {
        background-image: url('/images/emoji viendo fijo sin fondo.png');
        opacity: 0;
    }

    .nova-monkey-avatar--open .nova-monkey-avatar__sprite--closed {
        opacity: 0;
        transform: scale(1.04);
    }

    .nova-monkey-avatar--open .nova-monkey-avatar__sprite--open {
        opacity: 1;
        transform: scale(1.06);
    }

    /* Panel principal */
    .nova-ai-panel {
        position: absolute;
        bottom: 80px;
        right: 0;
        width: 400px;
        height: 620px;
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(20px);
        border-radius: 32px;
        border: 1px solid rgba(124, 58, 237, 0.25);
        box-shadow: 0 25px 50px -12px rgba(124, 58, 237, 0.2);
        display: flex;
        flex-direction: column;
        overflow: hidden;
        transition: all 0.3s cubic-bezier(0.2, 0.9, 0.4, 1.1);
        transform-origin: bottom right;
        z-index: 10000;
    }

    .nova-ai-panel.entering {
        opacity: 0;
        transform: scale(0.9) translateY(20px);
    }

    .nova-ai-panel.visible {
        opacity: 1;
        transform: scale(1) translateY(0);
    }

    /* Header con gradiente animado */
    .nova-ai-header {
        background: linear-gradient(135deg, #7C3AED 0%, #C455ED 55%, #EC4899 100%);
        background-size: 200% 100%;
        animation: gradient-shift 6s ease infinite;
        padding: 18px 20px;
        position: relative;
        overflow: hidden;
    }

    @keyframes gradient-shift {
        0%, 100% { background-position: 0% 50%; }
        50% { background-position: 100% 50%; }
    }

    .nova-ai-header::after {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: radial-gradient(circle at 30% 20%, rgba(255,255,255,0.15) 0%, transparent 60%);
        pointer-events: none;
    }

    .nova-ai-header-content {
        display: flex;
        align-items: center;
        gap: 12px;
        position: relative;
        z-index: 2;
    }

    .nova-ai-avatar {
        position: relative;
        width: 44px;
        height: 44px;
        flex-shrink: 0;
        border-radius: 50%;
        overflow: visible;
        background: transparent;
        border: none;
        backdrop-filter: none;
    }

    .nova-ai-title h3 {
        font-size: 16px;
        font-weight: 700;
        color: white;
        margin: 0;
        letter-spacing: -0.3px;
    }

    .nova-ai-title p {
        font-size: 11px;
        color: rgba(255,255,255,0.7);
        margin: 2px 0 0;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .nova-ai-badge {
        background: rgba(255,255,255,0.2);
        border-radius: 20px;
        padding: 2px 8px;
        font-size: 9px;
        font-weight: 600;
    }

    .nova-ai-close {
        width: 32px;
        height: 32px;
        background: rgba(255,255,255,0.15);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.2s ease;
        margin-left: auto;
    }

    .nova-ai-close:hover {
        background: rgba(255,255,255,0.3);
        transform: scale(1.05);
    }

    /* Context chip */
    .nova-ai-context {
        background: rgba(108, 74, 224, 0.15);
        border-bottom: 1px solid rgba(108, 74, 224, 0.2);
        padding: 10px 16px;
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }

    .context-chip {
        background: linear-gradient(135deg, rgba(108,74,224,0.3), rgba(196,85,237,0.2));
        border-radius: 20px;
        padding: 4px 12px;
        font-size: 11px;
        font-weight: 500;
        color: #C455ED;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        border: 1px solid rgba(108,74,224,0.3);
    }

    /* Área de mensajes */
    .nova-ai-messages {
        flex: 1;
        overflow-y: auto;
        padding: 16px;
        display: flex;
        flex-direction: column;
        gap: 12px;
        scroll-behavior: smooth;
    }

    .nova-ai-messages::-webkit-scrollbar {
        width: 4px;
    }

    .nova-ai-messages::-webkit-scrollbar-track {
        background: rgba(124,58,237,0.08);
        border-radius: 4px;
    }

    .nova-ai-messages::-webkit-scrollbar-thumb {
        background: rgba(124,58,237,0.3);
        border-radius: 4px;
    }

    /* Burbujas de mensaje */
    .message-user {
        display: flex;
        justify-content: flex-end;
    }

    .message-user .bubble {
        background: linear-gradient(135deg, #6C4AE0 0%, #C455ED 100%);
        color: white;
        border-radius: 20px 20px 4px 20px;
        padding: 10px 14px;
        max-width: 85%;
        font-size: 13px;
        line-height: 1.5;
        box-shadow: 0 2px 8px rgba(108,74,224,0.3);
    }

    .message-assistant {
        display: flex;
        justify-content: flex-start;
    }

    .message-assistant .bubble {
        background: rgba(240, 240, 255, 0.95);
        color: #1E1B4B;
        border-radius: 20px 20px 20px 4px;
        padding: 10px 14px;
        max-width: 85%;
        font-size: 13px;
        line-height: 1.5;
        border: 1px solid rgba(124, 58, 237, 0.2);
    }

    .message-info {
        display: flex;
        justify-content: center;
    }

    .message-info .bubble {
        background: rgba(245, 158, 11, 0.15);
        color: #F59E0B;
        border-radius: 16px;
        padding: 6px 12px;
        font-size: 11px;
        border: 1px solid rgba(245,158,11,0.3);
    }

    .message-action {
        display: flex;
        justify-content: flex-start;
    }

    .action-card {
        background: rgba(16, 185, 129, 0.08);
        border-left: 3px solid #059669;
        border-radius: 12px;
        padding: 10px 12px;
        max-width: 90%;
        font-size: 12px;
        color: #065F46;
    }

    .message-activity_created {
        display: flex;
        justify-content: flex-start;
    }

    .created-activity-card {
        width: 100%;
        max-width: 92%;
        border-radius: 14px;
        border: 1px solid rgba(124, 58, 237, 0.25);
        background: rgba(255, 255, 255, 0.85);
        box-shadow: 0 10px 26px rgba(124, 58, 237, 0.12);
        padding: 12px 14px;
    }

    .created-activity-card .title {
        font-size: 13px;
        font-weight: 700;
        color: #4C1D95;
        margin: 0 0 6px 0;
    }

    .created-activity-card .meta {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        font-size: 11px;
        color: #6D28D9;
        margin-bottom: 10px;
    }

    .created-activity-card .meta span {
        background: rgba(124, 58, 237, 0.18);
        border: 1px solid rgba(124, 58, 237, 0.28);
        border-radius: 999px;
        padding: 2px 8px;
    }

    .created-activity-card .open-btn {
        border: none;
        border-radius: 10px;
        padding: 8px 10px;
        font-size: 12px;
        font-weight: 700;
        color: white;
        background: linear-gradient(135deg, #6C4AE0 0%, #C455ED 100%);
        cursor: pointer;
        transition: transform 0.15s ease;
    }

    .created-activity-card .open-btn:hover {
        transform: translateY(-1px);
    }

    .created-activity-card .open-btn.loading {
        opacity: 0.75;
        cursor: wait;
        transform: none;
        background: linear-gradient(135deg, #4C1D95 0%, #7E22CE 100%);
    }

    /* Typing indicator */
    .typing-indicator {
        display: flex;
        gap: 6px;
        padding: 10px 14px;
        background: rgba(240, 240, 255, 0.9);
        border-radius: 20px;
        width: fit-content;
    }

    .typing-dot {
        width: 8px;
        height: 8px;
        background: #C455ED;
        border-radius: 50%;
        animation: typing-bounce 1.2s infinite;
    }

    .typing-dot:nth-child(2) { animation-delay: 0.2s; background: #6C4AE0; }
    .typing-dot:nth-child(3) { animation-delay: 0.4s; background: #3BC9DB; }

    @keyframes typing-bounce {
        0%, 60%, 100% { transform: translateY(0); opacity: 0.5; }
        30% { transform: translateY(-8px); opacity: 1; }
    }

    /* Input area */
    .nova-ai-input {
        padding: 16px;
        border-top: 1px solid rgba(124,58,237,0.2);
        background: rgba(255,255,255,0.9);
    }

    .input-wrapper {
        display: flex;
        align-items: flex-end;
        gap: 10px;
        background: #ffffff;
        border-radius: 16px;
        padding: 6px 6px 6px 12px;
        border: 1px solid rgba(124,58,237,0.25);
        transition: all 0.2s ease;
    }

    .input-wrapper:focus-within {
        border-color: #7C3AED;
        box-shadow: 0 0 0 2px rgba(124,58,237,0.2);
    }

    .nova-ai-input textarea {
        flex: 1;
        background: transparent;
        border: none;
        color: #0f172a;
        font-size: 13px;
        resize: none;
        outline: none;
        font-family: inherit;
        line-height: 1.5;
        min-height: 48px;
        max-height: 120px;
        padding: 12px 8px 8px 4px;
        overflow-y: auto;
    }

    .nova-ai-textarea-shell {
        display: flex;
        flex-direction: column;
        gap: 6px;
        background: #ffffff;
        border: 1px solid #cbd5e1;
        border-radius: 14px;
        padding: 6px 6px 6px 12px;
        transition: all 0.2s ease;
    }

    .nova-ai-textarea-shell:focus-within {
        border-color: #7C3AED;
        box-shadow: 0 0 0 2px rgba(124,58,237,0.2);
    }

    .nova-ai-textarea-shell textarea {
        flex: 1;
        background: transparent;
        border: none;
        color: #0f172a;
        font-size: 13px;
        resize: none;
        outline: none;
        font-family: inherit;
        line-height: 1.5;
        min-height: 56px;
        max-height: 120px;
        padding: 12px 8px 4px 4px;
        overflow-y: auto;
    }

    .nova-ai-textarea-shell textarea::placeholder {
        color: rgba(100,116,139,0.75);
    }

    .nova-ai-input textarea::placeholder {
        color: rgba(30,27,75,0.4);
    }

    .input-actions {
        display: flex;
        gap: 8px;
        align-items: center;
    }

    .voice-btn, .send-btn {
        width: 36px;
        height: 36px;
        border-radius: 18px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.2s ease;
        border: none;
        background: rgba(108,74,224,0.2);
        color: #C455ED;
    }

    .voice-btn:hover, .send-btn:hover {
        background: rgba(108,74,224,0.4);
        transform: scale(1.05);
    }

    .voice-btn.listening {
        background: #EF4444;
        color: white;
        animation: mic-pulse 1.2s infinite;
    }

    @keyframes mic-pulse {
        0%, 100% { box-shadow: 0 0 0 0 rgba(239,68,68,0.4); }
        50% { box-shadow: 0 0 0 6px rgba(239,68,68,0); }
    }

    /* Skeleton loader */
    .skeleton-message {
        display: flex;
        gap: 12px;
        padding: 12px;
        background: rgba(240,240,255,0.6);
        border-radius: 16px;
        margin-bottom: 8px;
    }

    .skeleton-avatar {
        width: 32px;
        height: 32px;
        background: rgba(108,74,224,0.3);
        border-radius: 10px;
    }

    .skeleton-lines {
        flex: 1;
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .skeleton-line {
        height: 10px;
        background: linear-gradient(90deg, rgba(108,74,224,0.2), rgba(108,74,224,0.4), rgba(108,74,224,0.2));
        background-size: 200% 100%;
        animation: skeleton-wave 1.5s infinite;
        border-radius: 5px;
    }

    @keyframes skeleton-wave {
        0% { background-position: 200% 0; }
        100% { background-position: -200% 0; }
    }

    /* Toast */
    .nova-toast {
        position: fixed;
        bottom: 100px;
        right: 420px;
        background: #10B981;
        color: white;
        padding: 10px 16px;
        border-radius: 40px;
        font-size: 12px;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 8px;
        box-shadow: 0 10px 25px -5px rgba(0,0,0,0.2);
        z-index: 10005;
        pointer-events: none;
        animation: toast-in 0.3s ease;
    }

    @keyframes toast-in {
        from { opacity: 0; transform: translateX(20px); }
        to { opacity: 1; transform: translateX(0); }
    }

    /* Menú inicial: "¿Qué quieres hacer?" (panel sin conversación) */
    .nova-ai-quickstart {
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
        gap: 6px;
        padding: 20px 8px 10px;
    }

    .nova-ai-quickstart-icon {
        width: 46px;
        height: 46px;
        border-radius: 16px;
        background: linear-gradient(135deg, #7C3AED 0%, #C455ED 55%, #EC4899 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 18px;
        box-shadow: 0 10px 24px -10px rgba(124, 58, 237, 0.55);
        margin-bottom: 4px;
    }

    .nova-ai-quickstart h4 {
        font-size: 14.5px;
        font-weight: 700;
        color: #1E1133;
        margin: 0 0 12px;
    }

    html.dark .nova-ai-quickstart h4 {
        color: rgba(255, 255, 255, 0.92);
    }

    .nova-ai-quickstart-actions {
        display: flex;
        flex-direction: column;
        gap: 8px;
        width: 100%;
    }

    .quickstart-btn {
        display: flex;
        align-items: center;
        gap: 10px;
        width: 100%;
        background: rgba(124, 58, 237, 0.06);
        border: 1px solid rgba(124, 58, 237, 0.16);
        border-radius: 14px;
        padding: 11px 14px;
        font-size: 13px;
        font-weight: 600;
        color: #1E1133;
        cursor: pointer;
        transition: all 0.2s ease;
        text-align: left;
    }

    .quickstart-btn i {
        color: #C455ED;
        width: 16px;
        text-align: center;
    }

    .quickstart-btn:hover {
        background: rgba(124, 58, 237, 0.12);
        border-color: rgba(124, 58, 237, 0.32);
        transform: translateY(-1px);
    }

    html.dark .quickstart-btn {
        background: rgba(196, 85, 237, 0.08);
        border-color: rgba(196, 85, 237, 0.2);
        color: rgba(255, 255, 255, 0.9);
    }

    .nova-ai-quickstart-hint {
        font-size: 11px;
        color: #9D89B6;
        margin: 12px 4px 4px;
        line-height: 1.5;
    }

    /* Sugerencias rápidas */
    .quick-suggestions {
        display: flex;
        gap: 8px;
        padding: 8px 16px 12px;
        flex-wrap: wrap;
        border-top: 1px solid rgba(108,74,224,0.1);
    }

    .suggestion-chip {
        background: rgba(108,74,224,0.15);
        border-radius: 20px;
        padding: 6px 12px;
        font-size: 11px;
        color: #A78BFA;
        cursor: pointer;
        transition: all 0.2s ease;
        border: 1px solid rgba(108,74,224,0.2);
    }

    .suggestion-chip:hover {
        background: rgba(108,74,224,0.3);
        transform: translateY(-1px);
    }

    /* Dark mode overrides */
    html.dark .nova-ai-panel {
        background: rgba(10, 10, 31, 0.95);
        backdrop-filter: blur(20px);
        border-color: rgba(108, 74, 224, 0.3);
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
    }

    html.dark .nova-ai-messages::-webkit-scrollbar-track {
        background: rgba(255,255,255,0.05);
    }

    html.dark .nova-ai-messages::-webkit-scrollbar-thumb {
        background: rgba(108,74,224,0.4);
    }

    html.dark .message-assistant .bubble {
        background: rgba(30, 26, 58, 0.9);
        color: #E2E8F0;
        border-color: rgba(108,74,224,0.2);
    }

    html.dark .nova-ai-input {
        background: rgba(10,10,31,0.8);
        border-top-color: rgba(108,74,224,0.2);
    }

    html.dark .input-wrapper {
        background: rgba(30,26,58,0.6);
        border-color: rgba(108,74,224,0.3);
    }

    html.dark .input-wrapper:focus-within {
        border-color: #6C4AE0;
        box-shadow: 0 0 0 2px rgba(108,74,224,0.2);
    }

    html.dark .nova-ai-input textarea {
        color: white;
    }

    html.dark .nova-ai-input textarea::placeholder {
        color: rgba(255,255,255,0.4);
    }

    html.dark .suggestion-chip {
        background: rgba(108,74,224,0.15);
        color: #A78BFA;
        border-color: rgba(108,74,224,0.2);
    }

    html.dark .typing-indicator {
        background: rgba(30, 26, 58, 0.8);
    }

    html.dark .skeleton-message {
        background: rgba(30,26,58,0.4);
    }

    html.dark .action-card {
        background: rgba(16, 185, 129, 0.1);
        border-left-color: #10B981;
        color: #A7F3D0;
    }

    html.dark .created-activity-card {
        border-color: rgba(196, 85, 237, 0.35);
        background: linear-gradient(135deg, rgba(30, 26, 58, 0.95), rgba(18, 16, 42, 0.95));
        box-shadow: 0 10px 26px rgba(0, 0, 0, 0.22);
    }

    html.dark .created-activity-card .title {
        color: #F3E8FF;
    }

    html.dark .created-activity-card .meta {
        color: #C4B5FD;
    }

    /* Mobile/tablet: panel usable a pantalla completa sin scroll infinito */
    @media (max-width: 767px) {
        .nova-ai-container {
            right: max(12px, env(safe-area-inset-right));
            bottom: calc(12px + env(safe-area-inset-bottom));
        }

        .nova-ai-trigger {
            width: 60px;
            height: 60px;
        }

        .nova-ai-panel {
            width: min(calc(100vw - 24px), 400px);
            height: min(78dvh, 640px);
            max-height: calc(100dvh - 108px - env(safe-area-inset-bottom));
            bottom: 72px;
            right: 0;
            border-radius: 24px;
        }

        .nova-toast {
            right: 12px;
            bottom: 88px;
            max-width: calc(100vw - 24px);
        }
    }

    @media (max-width: 420px) {
        .nova-ai-panel {
            width: calc(100vw - 16px);
            right: -4px;
        }
    }
</style>

<div id="ai-assistant-root" class="nova-ai-container" x-data="novaAIAssistant()" x-init="init()">
    <!-- Toast -->
    <div x-show="toast.visible" x-cloak class="nova-toast" :class="toast.type">
        <i class="fa-solid" :class="toast.icon"></i>
        <span x-text="toast.message"></span>
    </div>

    <!-- Panel principal -->
    <div class="nova-ai-panel" x-show="open" x-cloak :class="panelAnimation">
        <!-- Header -->
        <div class="nova-ai-header">
            <div class="nova-ai-header-content">
                <div class="nova-ai-avatar">
                    <div class="nova-monkey-avatar" :class="{ 'nova-monkey-avatar--open': open }" aria-hidden="true">
                        <div class="nova-monkey-avatar__sprite nova-monkey-avatar__sprite--closed"></div>
                        <div class="nova-monkey-avatar__sprite nova-monkey-avatar__sprite--open"></div>
                    </div>
                </div>
                <div class="nova-ai-title">
                    <h3>AulaSync</h3>
                    <p>
                        <span class="nova-ai-badge" x-text="isDirector ? 'Asistente del director' : 'Asistente docente'"></span>
                        <span>⚡ Siempre activa</span>
                    </p>
                </div>
                <div class="nova-ai-close" @click="open = false">
                    <i class="fa-solid fa-xmark"></i>
                </div>
            </div>
        </div>

        <!-- Contexto activo -->
        <div class="nova-ai-context" x-show="pageContext" x-cloak>
            <div class="context-chip">
                <i class="fa-solid fa-location-dot"></i>
                <span x-text="pageContext?.name || pageContext?.subject_name || pageContext?.title"></span>
            </div>
            <div class="context-chip" x-show="pageContext?.type">
                <i class="fa-solid" :class="pageContext?.type === 'clase' ? 'fa-book' : 'fa-clipboard-list'"></i>
                <span x-text="pageContext?.type === 'clase' ? 'Clase teórica' : 'Actividad'"></span>
            </div>
        </div>

        <!-- Mensajes -->
        <div class="nova-ai-messages" x-ref="messagesContainer">
            <template x-if="messages.length === 0 && !isDirector">
                <div class="nova-ai-quickstart">
                    <div class="nova-ai-quickstart-icon">
                        <i class="fa-solid fa-sparkles"></i>
                    </div>
                    <h4>¿Qué quieres hacer?</h4>
                    <div class="nova-ai-quickstart-actions">
                        <button type="button" class="quickstart-btn"
                                @click="input = 'Ayúdame a crear una planificación para mi curso'; sendCommand()">
                            <i class="fa-solid fa-calendar-days"></i>
                            Crear planificación
                        </button>
                        <button type="button" class="quickstart-btn"
                                @click="input = 'Créame una evaluación sobre el tema actual y agrégala al plan de evaluación'; sendCommand()">
                            <i class="fa-solid fa-file-pen"></i>
                            Crear evaluación + plan
                        </button>
                        <button type="button" class="quickstart-btn"
                                @click="input = 'Ayúdame a crear una actividad para mi curso'; sendCommand()">
                            <i class="fa-solid fa-clipboard-list"></i>
                            Crear actividad
                        </button>
                        <button type="button" class="quickstart-btn" @click="$refs.novaMainTextarea?.focus()">
                            <i class="fa-solid fa-comment-dots"></i>
                            Preguntar algo
                        </button>
                    </div>
                    <p class="nova-ai-quickstart-hint">
                        También puedo crear exámenes, agregarlos al plan, generar rúbricas, cargar notas o inscribir alumnos.
                    </p>
                </div>
            </template>

            <template x-if="messages.length === 0 && isDirector">
                <div class="nova-ai-quickstart">
                    <div class="nova-ai-quickstart-icon">
                        <i class="fa-solid fa-building-user"></i>
                    </div>
                    <h4>¿Qué operación quieres ejecutar?</h4>
                    <div class="nova-ai-quickstart-actions">
                        <button type="button" class="quickstart-btn"
                                @click="input = 'Crea al profesor Vicente Maduro y asígnale Inglés de 1ro a 6to'; sendCommand()">
                            <i class="fa-solid fa-user-plus"></i>
                            Crear profesor + asignaciones
                        </button>
                        <button type="button" class="quickstart-btn"
                                @click="input = 'Crea a los siguientes alumnos en 2do grado: Carlos José, Juan Carlos y María'; sendCommand()">
                            <i class="fa-solid fa-users"></i>
                            Crear estudiantes por lote
                        </button>
                        <button type="button" class="quickstart-btn"
                                @click="input = '¿Cuántos alumnos, profesores y cursos hay en el colegio?'; sendCommand()">
                            <i class="fa-solid fa-chart-simple"></i>
                            Consultar el colegio
                        </button>
                        <button type="button" class="quickstart-btn" @click="$refs.novaMainTextarea?.focus()">
                            <i class="fa-solid fa-comment-dots"></i>
                            Escribir operación
                        </button>
                    </div>
                    <p class="nova-ai-quickstart-hint">
                        Las operaciones sensibles siempre pedirán confirmación antes de ejecutarse.
                    </p>
                </div>
            </template>

            <template x-for="(msg, idx) in messages" :key="idx">
                <div>
                    <template x-if="msg.role !== 'activity_created'">
                        <div :class="`message-${msg.role}`">
                            <div class="bubble" x-html="renderMarkdown(msg.text)"></div>
                        </div>
                    </template>
                    <template x-if="msg.role === 'activity_created'">
                        <div class="message-activity_created">
                            <div class="created-activity-card">
                                <p class="title" x-text="msg.activity?.title || 'Actividad creada'"></p>
                                <div class="meta">
                                    <span x-show="msg.activity?.course_name" x-text="msg.activity?.course_name"></span>
                                    <span x-show="msg.activity?.due_date" x-text="msg.activity?.due_date"></span>
                                    <span x-show="msg.activity?.type" x-text="msg.activity?.type"></span>
                                </div>
                                <button class="open-btn"
                                        :class="{ 'loading': openingActivityId === msg.activity?.id }"
                                        :disabled="openingActivityId === msg.activity?.id"
                                        @click="openCreatedActivity(msg.activity)">
                                    <span x-show="openingActivityId !== msg.activity?.id">Ver actividad creada →</span>
                                    <span x-show="openingActivityId === msg.activity?.id">Abriendo...</span>
                                </button>
                            </div>
                        </div>
                    </template>
                </div>
            </template>

            <!-- Loading skeleton -->
            <template x-if="loading">
                <div class="message-assistant">
                    <div class="bubble">
                        <div class="typing-indicator">
                            <div class="typing-dot"></div>
                            <div class="typing-dot"></div>
                            <div class="typing-dot"></div>
                        </div>
                        <span style="font-size: 11px; margin-left: 8px;">AulaSync está pensando...</span>
                    </div>
                </div>
            </template>
        </div>

        <!-- Sugerencias rápidas -->
        <div class="quick-suggestions" x-show="messages.length > 0 && !loading && !isDirector">
            <span class="suggestion-chip" @click="input = 'Modifica esta clase para que los alumnos copien en su cuaderno'; sendCommand()">
                📝 Copiar en cuaderno
            </span>
            <span class="suggestion-chip" @click="input = 'Agrega una actividad práctica al final de la clase'; sendCommand()">
                🎮 Actividad práctica
            </span>
            <span class="suggestion-chip" @click="input = 'Incluye una rúbrica de evaluación'; sendCommand()">
                📊 Rúbrica
            </span>
            <span class="suggestion-chip" @click="input = 'Adapta para estudiantes con TDAH'; sendCommand()">
                🧠 Adaptación NEE
            </span>
        </div>

        <div class="quick-suggestions" x-show="messages.length > 0 && !loading && isDirector">
            <span class="suggestion-chip" @click="input = 'Crea al profesor Ana Rojas y asígnale Matemática de 1ro a 3ro'; sendCommand()">
                👩‍🏫 Crear profesor
            </span>
            <span class="suggestion-chip" @click="input = 'Crea a los siguientes alumnos en 2do grado: Luis Perez, Marta Gomez, Pedro Ruiz'; sendCommand()">
                🧒 Crear estudiantes
            </span>
            <span class="suggestion-chip" @click="input = '¿Cuántos alumnos, profesores y cursos hay en el colegio?'; sendCommand()">
                📊 Consultar colegio
            </span>
        </div>

        <!-- Botones híbridos del menú director (Fase Híbrida) -->
        <div class="quick-suggestions" x-show="currentButtons.length > 0 && !loading" style="border-top: 1px solid rgba(124,58,237,0.2); background: rgba(124,58,237,0.06);">
            <template x-for="btn in currentButtons" :key="btn.id">
                <span class="suggestion-chip" 
                      :style="btn.color === 'blue' ? 'background: rgba(59,130,246,0.15); border-color: rgba(59,130,246,0.3);' : btn.color === 'green' ? 'background: rgba(16,185,129,0.15); border-color: rgba(16,185,129,0.3);' : btn.color === 'red' ? 'background: rgba(239,68,68,0.15); border-color: rgba(239,68,68,0.3);' : btn.color === 'orange' ? 'background: rgba(249,115,22,0.15); border-color: rgba(249,115,22,0.3);' : btn.color === 'purple' ? 'background: rgba(147,51,234,0.15); border-color: rgba(147,51,234,0.3);' : ''"
                      @click="handleButtonClick(btn.id, btn.label)">
                    <span x-text="btn.label"></span>
                </span>
            </template>
        </div>

        <!-- Acciones de notas (siempre visibles) -->
        <div class="quick-suggestions" x-show="isTeacher" style="border-top: 1px solid rgba(16,185,129,0.2);">
            <span class="suggestion-chip" style="background: rgba(16,185,129,0.15); border-color: rgba(16,185,129,0.3);" 
                @click="quickAddStudent()">
                👨‍🎓 Agregar Alumno
            </span>
            <span class="suggestion-chip" style="background: rgba(16,185,129,0.15); border-color: rgba(16,185,129,0.3);"
                @click="quickAddStudents()">
                👥 Agregar Varios
            </span>
            <span class="suggestion-chip" style="background: rgba(59,201,219,0.15); border-color: rgba(59,201,219,0.3);"
                @click="quickLoadGrades()">
                📝 Cargar Notas
            </span>
            <span class="suggestion-chip" style="background: rgba(139,92,246,0.15); border-color: rgba(139,92,246,0.3);"
                @click="quickPublishGrades()">
                🚀 Publicar Notas
            </span>
            <span class="suggestion-chip" style="background: rgba(196,85,237,0.15); border-color: rgba(196,85,237,0.3);"
                @click="window.dispatchEvent(new CustomEvent('nova-lesson-template-picker'))">
                🎨 Estilo de Clase
            </span>
        </div>

        <!-- Input -->
        <div class="nova-ai-input">
            <div class="input-wrapper">
                <textarea x-ref="novaMainTextarea" x-model="input" @keydown.enter.prevent="if(!$event.shiftKey) sendCommand()" @input="autoResizeTextarea()" :disabled="loading" rows="1" placeholder="Escribe tu mensaje..."></textarea>
                <div class="input-actions">
                    <button class="voice-btn" :class="{ 'listening': listening }" @click="toggleVoice()" :title="isDirector ? 'Nota de voz: graba y envía' : 'Dictado de voz'">
                        <i class="fa-solid" :class="listening ? 'fa-stop' : 'fa-microphone'"></i>
                    </button>
                    <button class="send-btn" @click="sendCommand()" :disabled="loading || !input.trim()">
                        <i class="fa-solid" :class="loading ? 'fa-spinner fa-spin' : 'fa-paper-plane'"></i>
                    </button>
                </div>
            </div>
            <div x-show="listening || transcribing" class="voice-status" style="font-size: 10px; color: #EF4444; text-align: center; margin-top: 6px;">
                <span x-show="isDirector && listening && !transcribing">🔴 Grabando nota de voz... toca el micrófono para transcribir y enviar</span>
                <span x-show="isDirector && transcribing">⏳ Transcribiendo y entendiendo la orden...</span>
                <span x-show="!isDirector">🔴 Escuchando...</span>
            </div>
        </div>
    </div>

    <!-- Botón flotante -->
    <button type="button" class="nova-ai-trigger" :class="{ 'listening': listening }" @click="togglePanel()" aria-label="Abrir o cerrar AulaSync">
        <div class="nova-monkey-avatar nova-monkey-avatar--trigger" :class="{ 'nova-monkey-avatar--open': open }" aria-hidden="true">
            <div class="nova-monkey-avatar__sprite nova-monkey-avatar__sprite--closed"></div>
            <div class="nova-monkey-avatar__sprite nova-monkey-avatar__sprite--open"></div>
        </div>
    </button>
</div>

<script>
function novaAIAssistant() {
    return {
        open: false,
        loading: false,
        listening: false,
        transcribing: false,
        input: '',
        messages: @json(auth()->check() ? app(\App\Services\AiChatHistoryService::class)->load(auth()->id()) : []),
        confirmation: null,
        recognition: null,
        panelAnimation: 'entering',
        pageContext: null,
        openingActivityId: null,
        currentButtons: [],
        currentMode: 'main_menu',
        historyEndpoint: @json(auth()->check() ? route('ai.chat.history.store') : null),
        isTeacher: {{ auth()->check() && auth()->user()->role === 'profesor' ? 'true' : 'false' }},
        isDirector: {{ auth()->check() && auth()->user()->role === 'director' ? 'true' : 'false' }},
        commandEndpoint: @json(auth()->check() && auth()->user()->role === 'director' ? route('director.ai.command') : route('ai.command')),
        transcribeEndpoint: @json(auth()->check() && auth()->user()->role === 'director' ? route('director.ai.transcribe') : null),
        mediaRecorder: null,
        audioChunks: [],
        toast: {
            visible: false,
            message: '',
            type: 'success',
            icon: 'fa-check-circle'
        },

        init() {
            this.refreshContext();
            this._persistTimer = null;

            // Escuchar eventos globales
            window.addEventListener('ai-context-changed', (e) => {
                this.pageContext = e.detail;
            });

            window.addEventListener('open-activity-ai', (e) => {
                this.openWithFullContext(e.detail.activity, e.detail.courseName, e.detail.fullContext);
            });

            window.addEventListener('ai-toast', (e) => {
                const message = e.detail?.message || 'Acción ejecutada';
                const type = e.detail?.type === 'error' ? 'error' : 'success';
                const icon = type === 'error' ? 'fa-circle-xmark' : 'fa-check-circle';
                this.showToast(message, type, icon);
            });

            window.addEventListener('open-ai-bubble', () => {
                this.open = true;
                this.focusInput();
            });

            window.addEventListener('nova-assistant-prefill', (e) => {
                const detail = e.detail || {};
                this.applyExternalPrefill(detail);
            });

            // Animación de apertura
            this.$watch('open', (val) => {
                if (val) {
                    this.panelAnimation = 'entering';
                    setTimeout(() => { this.panelAnimation = 'visible'; }, 10);
                    this.$nextTick(() => this.scrollToBottom());
                }
            });
        },

        applyExternalPrefill(detail) {
            const context = detail.context || null;
            const prefill = (detail.prefill || '').trim();
            const seedMessage = (detail.seedMessage || '').trim();

            this.open = true;

            if (context) {
                this.pageContext = context;
                window.novaContext = context;
                window.AI_PAGE_CONTEXT = context;
                window.dispatchEvent(new CustomEvent('ai-context-changed', { detail: context }));
            }

            if (seedMessage) {
                this.addMessage('assistant', seedMessage);
            }

            if (prefill) {
                this.input = prefill;
            }

            this.focusInput();
        },

        refreshContext() {
            this.pageContext = window.novaContext || window.AI_PAGE_CONTEXT || null;
        },

        togglePanel() {
            this.refreshContext();
            this.open = !this.open;
        },

        openWithFullContext(activity, courseName, fullContext) {
            this.open = true;
            this.pageContext = fullContext || activity;
            window.novaContext = this.pageContext;
            window.AI_PAGE_CONTEXT = this.pageContext;
            window.dispatchEvent(new CustomEvent('ai-context-changed', { detail: this.pageContext }));

            // Mensaje de contexto
            this.messages = [];
            this.addMessage('assistant', `📝 **Editando:** ${activity.title}\n🏫 **Curso:** ${courseName}\n📅 **Fecha:** ${activity.due_date || 'No definida'}\n\n¿Qué te gustaría modificar?`);

            // Precargar prompt
            if (activity.type === 'clase') {
                this.input = `Modifica la clase "${activity.title}" para que los alumnos copien en su cuaderno la teoría`;
            } else {
                this.input = `Modifica la actividad "${activity.title}"`;
            }

            this.$nextTick(() => {
                document.querySelector('.nova-ai-input textarea')?.focus();
                this.scrollToBottom();
            });
        },

        isConfirmationAffirmative(text) {
            const t = text.trim().replace(/[.!¡?¿]+$/g, '').toLowerCase();
            if (t === 'sí, créalos' || t === 'si, crealos' || t === 'sí, hazlo' || t === 'si, hazlo' || t === 'sí, créalo' || t === 'si, crealo') return true;
            return /^(s[ií]|ok|okay|dale|adelante|confirmo|procede|proceder|hazlo|listo|yes|yep|crealo|créalo|crealos|cr[ée]alos|adelante|puedes|puede|perfecto|de acuerdo)$/i.test(t);
        },

        autoResizeTextarea() {
            this.$nextTick(() => {
                const el = this.$refs.novaMainTextarea;
                if (!el) return;
                el.style.height = 'auto';
                el.style.height = Math.min(el.scrollHeight, 120) + 'px';
            });
        },

        getCsrfToken() {
            const el = document.querySelector('meta[name="csrf-token"]');
            if (el && el.getAttribute('content')) {
                return el.getAttribute('content');
            }
            const input = document.querySelector('input[name="_token"]');
            return input ? input.value : '';
        },
        async executeConfirmed() {
            const liveContext = window.novaContext || this.pageContext || null;
            const conversation = this.messages
                .filter(m => m.role === 'user' || m.role === 'assistant')
                .map(m => ({ role: m.role, content: m.text }));
            const token = this.getCsrfToken();
            if (!token) {
                this.addMessage('assistant', 'No pude verificar tu sesión. Recarga la página e intenta de nuevo.');
                return { success: false };
            }
            const res = await fetch(this.commandEndpoint, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': token,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                    body: JSON.stringify({
                    prompt: '',
                    message: '',
                    confirmed: true,
                    pending_actions: this.confirmation?.pending_actions || undefined,
                    screen_context: liveContext,
                    conversation: conversation.length ? conversation : undefined,
                    lesson_template: window.novaLessonTemplate || undefined,
                    payload: { mensaje_usuario: '[confirmación]', contexto: liveContext },
                }),
            });
            if (res.status === 419) {
                this.addMessage('assistant', 'Tu sesión expiró. Recarga la página e intenta de nuevo.');
                return { success: false, status: 419 };
            }
            return res.json();
        },

        async sendCommand() {
            const text = this.input.trim();
            if (!text || this.loading) return;

            if (this.confirmation && this.isConfirmationAffirmative(text)) {
                this.addMessage('user', text);
                this.input = '';
                this.autoResizeTextarea();
                this.loading = true;
                this.scrollToBottom();
                try {
                    const json = await this.executeConfirmed();
                    if (json?.pending_actions?.length) {
                        this.confirmation = { ...this.confirmation, pending_actions: json.pending_actions };
                    } else if (json?.any_success || json?.success) {
                        this.confirmation = null;
                    }
                    try {
                        this.handleResponse(json);
                    } catch (handlerErr) {
                        console.error('AI handleResponse', handlerErr);
                    }
                } catch (err) {
                    this.showToast('Error de conexión', 'error', 'fa-exclamation-triangle');
                    this.addMessage('assistant', '❌ Lo siento, hubo un error de conexión. Intenta de nuevo.');
                } finally {
                    this.loading = false;
                    this.scrollToBottom();
                }
                return;
            }

            this.addMessage('user', text);
            this.input = '';
            this.autoResizeTextarea();
            this.loading = true;
            this.scrollToBottom();
            if (this.isDirector) {
                const countMatch = text.match(/profesor/i) ? (text.split(/,|\sy\s/i).filter(Boolean).length > 1 ? text.split(/,/).length : null) : null;
                window.dispatchEvent(new CustomEvent('aula-sync-ai-busy', {
                    detail: { prompt: text, count: countMatch && countMatch > 1 ? countMatch : null },
                }));
            }

            const token2 = this.getCsrfToken();
            if (!token2) {
                this.addMessage('assistant', 'No pude verificar tu sesión. Recarga la página e intenta de nuevo.');
                this.loading = false;
                return;
            }
            try {
                const liveContext = window.novaContext || this.pageContext || null;
                const conversation = this.messages
                    .filter(m => m.role === 'user' || m.role === 'assistant')
                    .map(m => ({ role: m.role, content: m.text }));
                const res = await fetch(this.commandEndpoint, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': token2,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                    prompt: text,
                    message: text,
                    screen_context: liveContext,
                    conversation: conversation.length ? conversation : undefined,
                    lesson_template: window.novaLessonTemplate || undefined,
                    payload: { mensaje_usuario: text, contexto: liveContext },
                }),
                });

                if (res.status === 419) {
                    this.addMessage('assistant', 'Tu sesión expiró. Recarga la página e intenta de nuevo.');
                    this.showToast('Sesión expirada', 'error', 'fa-exclamation-triangle');
                    return;
                }
                const raw = await res.text();
                let json;
                try {
                    json = JSON.parse(raw);
                } catch (parseErr) {
                    this.addMessage('assistant', '❌ Lo siento, hubo un error de conexión. Intenta de nuevo.');
                    return;
                }
                try {
                    this.handleResponse(json);
                } catch (handlerErr) {
                    console.error('AI handleResponse', handlerErr);
                }
            } catch (err) {
                this.showToast('Error de conexión', 'error', 'fa-exclamation-triangle');
                this.addMessage('assistant', '❌ Lo siento, hubo un error de conexión. Intenta de nuevo.');
            } finally {
                this.loading = false;
                this.scrollToBottom();
            }
        },

        handleButtonClick(buttonId, label) {
            this.addMessage('user', label);
            this.loading = true;
            const liveContext = window.novaContext || this.pageContext || null;
            const conversation = this.messages.filter(m => m.role === 'user' || m.role === 'assistant').map(m => ({ role: m.role, content: m.text }));
            fetch(this.commandEndpoint, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': this.getCsrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    prompt: label,
                    message: label,
                    button_action: buttonId,
                    buttonAction: buttonId,
                    screen_context: liveContext,
                    conversation: conversation.length ? conversation : undefined,
                }),
            }).then(r => r.text().then(t => { try { return JSON.parse(t); } catch(e) { return { message: 'Error' }; } })).then(json => {
                this.handleResponse(json);
            }).catch(() => {
                this.addMessage('assistant', '❌ Error de conexión.');
            }).finally(() => {
                this.loading = false;
                this.scrollToBottom();
            });
        },
        formatTimeline(timeline) {
            if (!Array.isArray(timeline) || !timeline.length) return '';
            const iconByStatus = {
                analyzing: '🔍',
                planning: '📋',
                confirming: '⏳',
                executing: '⚙️',
                completed: '✅',
                error: '❌',
            };
            const lines = timeline
                .filter(step => step && typeof step.message === 'string' && step.message.trim() !== '')
                .map(step => `${iconByStatus[step.status] || '•'} ${step.message.trim()}`);
            if (!lines.length) return '';
            return lines.join('\n');
        },
        handleResponse(json) {
            if (!json || typeof json !== 'object') {
                return;
            }
            const timelineMessage = this.formatTimeline(json.timeline);
            if (timelineMessage) {
                this.addMessage('assistant', timelineMessage);
            }
            // Guardar botones y modo para UI híbrida
            if (Array.isArray(json.buttons)) {
                this.currentButtons = json.buttons;
                this.currentMode = json.mode || this.currentMode;
            } else if (json.mode) {
                this.currentMode = json.mode;
                this.currentButtons = [];
            } else {
                this.currentButtons = [];
            }

            if (json.cancelled) {
                this.confirmation = null;
                if (this.isDirector) {
                    window.dispatchEvent(new CustomEvent('aula-sync-roster-changed', {
                        detail: { message: json.message || 'Operación cancelada.', cancelled: true },
                    }));
                    window.dispatchEvent(new CustomEvent('aula-sync-ai-idle'));
                }
            }

            if (json.message && !json.actions && !json.requires_confirmation) {
                this.addMessage('assistant', json.message);
                if (this.isDirector) {
                    window.dispatchEvent(new CustomEvent('aula-sync-ai-idle'));
                }
                return;
            }

            if (json.requires_confirmation) {
                if (json.message) this.addMessage('assistant', json.message);
                this.confirmation = json;
                if (this.isDirector) {
                    const n = Array.isArray(json.pending_actions) ? json.pending_actions.length : 0;
                    window.dispatchEvent(new CustomEvent('aula-sync-ai-busy', {
                        detail: { prompt: n > 1 ? `Confirmación: ${n} acciones` : 'Esperando confirmación', count: n > 1 ? n : null },
                    }));
                }
                return;
            }

            let hasDeleteSuccess = false;
            const directorNarrative = this.isDirector && json.message;

            if (directorNarrative) {
                this.addMessage('assistant', json.message);
            }

            if (Array.isArray(json.actions)) {
                if (json.any_success || (json.status === 'success' && json.bulk_plan)) {
                    this.confirmation = null;
                }
                json.actions.forEach(a => {
                    if (a.success) {
                        if (!directorNarrative) {
                            this.addMessage('action', `✅ ${a.message}`);
                        }
                        if (a.action_type === 'activity' && a.data?.activity_id) {
                            this.addMessage('activity_created', '', {
                                activity: {
                                    id: a.data.activity_id,
                                    title: a.data.title || 'Actividad creada',
                                    course_name: a.data.course_name || '',
                                    due_date: a.data.due_date || '',
                                    type: a.data.type || '',
                                    course_id: a.data.course_id || null,
                                }
                            });
                        }
                        if (a.action_type === 'delete') {
                            hasDeleteSuccess = true;
                            this.showToast('Actividades eliminadas correctamente', 'success', 'fa-trash-alt');
                        }
                    } else {
                        this.addMessage('assistant', `⚠️ ${a.message}`);
                    }
                });

                if (json.any_success || hasDeleteSuccess) {
                    const toastMsg = json.bulk_plan?.activities_created
                        ? `¡Listo! ${json.bulk_plan.activities_created} actividades creadas`
                        : (hasDeleteSuccess ? null : 'Cambios aplicados correctamente');
                    if (toastMsg) {
                        this.showToast(toastMsg, 'success', 'fa-check-circle');
                    }
                    window.dispatchEvent(new CustomEvent('ai-canvas-refresh'));
                    if (this.isDirector) {
                        window.dispatchEvent(new CustomEvent('aula-sync-roster-changed', {
                            detail: {
                                actions: json.actions,
                                message: json.message || toastMsg || 'Cambios aplicados.',
                            },
                        }));
                    }
                }

                // Resumen único para operaciones multi-entidad (varios cursos/alumnos).
                const successfulActions = json.actions.filter(a => a.success).length;
                if (json.message && !json.bulk_plan && successfulActions > 1 && !directorNarrative) {
                    this.addMessage('assistant', json.message);
                }
            }

            if (json.error && !json.any_success && !Array.isArray(json.actions)) {
                this.addMessage('assistant', `❌ Error: ${json.error}`);
                this.showToast(json.error, 'error', 'fa-exclamation-triangle');
            }

            if (this.isDirector && !json.requires_confirmation) {
                window.dispatchEvent(new CustomEvent('aula-sync-ai-idle'));
            }
        },

        addMessage(role, text, extra = {}) {
            this.messages.push({ role, text, ...extra });
            this.persistHistory();
            this.$nextTick(() => this.scrollToBottom());
        },

        persistHistory() {
            if (!this.historyEndpoint) return;
            clearTimeout(this._persistTimer);
            this._persistTimer = setTimeout(() => {
                const payload = this.messages.slice(-40).map((m) => ({
                    role: m.role,
                    text: m.text || '',
                    activity: m.activity || null,
                }));
                fetch(this.historyEndpoint, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.getCsrfToken(),
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ messages: payload }),
                }).catch(() => {});
            }, 300);
        },

        openCreatedActivity(activity) {
            if (!activity?.id) {
                this.showToast('No se pudo abrir la actividad', 'error', 'fa-circle-xmark');
                return;
            }

            this.openingActivityId = activity.id;
            this.showToast('Buscando actividad...', 'success', 'fa-location-arrow');

            window.dispatchEvent(new CustomEvent('open-activity-modal', {
                detail: {
                    id: activity.id,
                    course_id: activity.course_id ?? null,
                    due_date: activity.due_date ?? null,
                    source: 'chat-card',
                }
            }));

            setTimeout(() => {
                this.openingActivityId = null;
            }, 900);
        },

        toggleVoice() {
            if (this.listening) {
                this.stopVoiceCapture();
                return;
            }

            this.open = true;
            if (this.isDirector && this.transcribeEndpoint && navigator.mediaDevices?.getUserMedia && window.MediaRecorder) {
                this.startVoiceNote();
                return;
            }

            this.startBrowserDictation();
        },

        stopVoiceCapture() {
            if (this.mediaRecorder && this.mediaRecorder.state !== 'inactive') {
                this.mediaRecorder.stop();
                return;
            }
            this.recognition?.stop();
            this.listening = false;
        },

        async startVoiceNote() {
            try {
                const stream = await navigator.mediaDevices.getUserMedia({
                    audio: {
                        echoCancellation: true,
                        noiseSuppression: true,
                        autoGainControl: true,
                        channelCount: 1,
                    },
                });
                const mimeType = ['audio/webm;codecs=opus', 'audio/webm', 'audio/mp4']
                    .find((type) => window.MediaRecorder.isTypeSupported(type)) || '';
                this.audioChunks = [];
                this.mediaRecorder = mimeType
                    ? new MediaRecorder(stream, { mimeType, audioBitsPerSecond: 128000 })
                    : new MediaRecorder(stream);
                this.mediaRecorder.ondataavailable = (event) => {
                    if (event.data && event.data.size > 0) {
                        this.audioChunks.push(event.data);
                    }
                };
                this.mediaRecorder.onstop = async () => {
                    stream.getTracks().forEach((track) => track.stop());
                    this.listening = false;
                    const blob = new Blob(this.audioChunks, { type: this.mediaRecorder?.mimeType || 'audio/webm' });
                    this.mediaRecorder = null;
                    await this.sendVoiceNote(blob);
                };
                this.mediaRecorder.start(250);
                this.listening = true;
            } catch (err) {
                this.startBrowserDictation();
            }
        },

        async sendVoiceNote(blob) {
            if (!blob || blob.size < 800) {
                this.showToast('La nota quedó muy corta. Graba de nuevo.', 'error', 'fa-microphone-slash');
                return;
            }

            const token = this.getCsrfToken();
            if (!token || !this.transcribeEndpoint) {
                this.showToast('No pude verificar tu sesión', 'error', 'fa-circle-xmark');
                return;
            }

            this.transcribing = true;
            this.loading = true;
            const form = new FormData();
            const extension = (blob.type || '').includes('mp4') ? 'm4a' : 'webm';
            form.append('audio', blob, `nota-voz.${extension}`);

            try {
                const res = await fetch(this.transcribeEndpoint, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': token,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: form,
                });
                const json = await res.json().catch(() => ({}));
                const errorText = json?.message || json?.errors?.audio?.[0] || 'No pude transcribir la nota de voz. Intenta de nuevo.';
                if (!res.ok || !json?.success || !json?.transcript) {
                    this.showToast(errorText, 'error', 'fa-microphone-slash');
                    this.addMessage('assistant', errorText);
                    return;
                }
                this.input = json.transcript;
                this.autoResizeTextarea();
            } catch (err) {
                this.showToast('Error al enviar la nota de voz', 'error', 'fa-exclamation-triangle');
                return;
            } finally {
                this.transcribing = false;
                this.loading = false;
            }

            await this.sendCommand();
        },

        startBrowserDictation() {
            if (!('webkitSpeechRecognition' in window) && !('SpeechRecognition' in window)) {
                this.showToast('Tu navegador no soporta el micrófono', 'error', 'fa-microphone-slash');
                return;
            }

            const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
            this.recognition = new SR();
            this.recognition.lang = 'es-VE';
            this.recognition.continuous = false;
            this.recognition.interimResults = false;

            this.recognition.onresult = (e) => {
                this.input = e.results[0][0].transcript;
                this.listening = false;
                this.sendCommand();
            };

            this.recognition.onerror = () => {
                this.showToast('Error de micrófono', 'error', 'fa-microphone-slash');
                this.listening = false;
            };

            this.recognition.start();
            this.listening = true;
            this.open = true;
        },

        scrollToBottom() {
            const container = document.querySelector('.nova-ai-messages');
            if (container) container.scrollTop = container.scrollHeight;
        },

        focusInput() {
            this.$nextTick(() => {
                document.querySelector('.nova-ai-input textarea')?.focus();
                this.scrollToBottom();
            });
        },

        showToast(message, type, icon) {
            this.toast = { visible: true, message, type, icon };
            setTimeout(() => { this.toast.visible = false; }, 3000);
        },

        renderMarkdown(text) {
            if (!text) return '';
            return text.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
                       .replace(/\n/g, '<br>');
        },

        async quickAddStudent() {
            const name = prompt('Nombre del alumno (debe existir en la nómina del colegio):');
            if (!name) return;
            this.input = `Inscribe a ${name} en este curso solo si ya está matriculado en el colegio. No crees un alumno nuevo.`;
            await this.sendCommand();
        },

        async quickAddStudents() {
            const input = prompt('Nombres separados por coma. Solo se vincularán si ya están en la nómina:\nEj: María, Pedro, Juan');
            if (!input) return;
            this.input = `Inscribe a ${input} en este curso solo si ya están matriculados en el colegio. No crees alumnos nuevos.`;
            await this.sendCommand();
        },

        quickLoadGrades() {
            this.open = false;
            setTimeout(() => {
                const hub = document.querySelector('[x-data*="courseData"]')?.__x?.$data;
                if (hub?.courseData?.activities?.length > 0) {
                    const act = hub.courseData.activities.find(a => a.type !== 'clase');
                    if (act) hub.openGradesSlideover(act);
                }
            }, 200);
            this.showToast('Abriendo panel de notas...', 'success', 'fa-table-cells');
        },

        quickPublishGrades() {
            this.open = false;
            setTimeout(() => {
                const hub = document.querySelector('[x-data*="courseData"]')?.__x?.$data;
                if (hub?.gradesSlideover?.open) {
                    hub.gradesSlideover.confirmPublish = true;
                } else if (hub?.courseData?.activities?.length > 0) {
                    const act = hub.courseData.activities.find(a => a.type !== 'clase');
                    if (act) {
                        hub.openGradesSlideover(act);
                        setTimeout(() => { hub.gradesSlideover.confirmPublish = true; }, 500);
                    }
                }
            }, 200);
            this.showToast('Abriendo publicar notas...', 'success', 'fa-rocket');
        }
    };
}
</script>
@include('components.lesson-template-picker')