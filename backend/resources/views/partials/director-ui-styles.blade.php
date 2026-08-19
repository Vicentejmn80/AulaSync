<style>
    [x-cloak] { display: none !important; }

    .director-input,
    .director-select,
    .director-textarea {
        width: 100%;
        border-radius: 0.5rem;
        border: 1px solid #cbd5e1;
        background-color: #ffffff;
        padding: 0.625rem 0.75rem;
        font-size: 0.875rem;
        font-weight: 500;
        color: #0f172a;
        box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        transition: border-color 0.15s, box-shadow 0.15s, background-color 0.15s;
    }

    .director-input::placeholder,
    .director-textarea::placeholder {
        color: #94a3b8;
    }

    .director-input:focus,
    .director-select:focus,
    .director-textarea:focus {
        outline: none;
        border-color: #6366f1;
        background-color: #ffffff;
        box-shadow: 0 0 0 2px rgba(199, 210, 254, 1);
    }

    .director-select option,
    .director-select optgroup {
        background-color: #ffffff;
        color: #0f172a;
    }

    .director-select option:disabled {
        color: #94a3b8;
    }

    .director-label {
        display: block;
        margin-bottom: 0.25rem;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #334155;
    }

    .director-card {
        border-radius: 0.75rem;
        border: 1px solid rgba(226, 232, 240, 0.8);
        background-color: #ffffff;
        padding: 1.5rem;
        box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    }

    .director-page-title {
        font-size: 1.5rem;
        font-weight: 900;
        letter-spacing: -0.025em;
        color: #0f172a;
    }

    .director-page-subtitle {
        margin-top: 0.25rem;
        font-size: 0.875rem;
        color: #475569;
    }

    .director-section-title {
        font-size: 1.125rem;
        font-weight: 700;
        color: #1e1b4b;
    }

    .director-btn-primary {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        border-radius: 0.5rem;
        background-color: #4f46e5;
        padding: 0.625rem 1.25rem;
        font-size: 0.875rem;
        font-weight: 600;
        color: #ffffff;
        box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        transition: background-color 0.15s, opacity 0.15s;
    }

    .director-btn-primary:hover:not(:disabled) {
        background-color: #4338ca;
    }

    .director-btn-primary:disabled {
        cursor: not-allowed;
        opacity: 0.6;
    }

    .director-code {
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        font-size: 1rem;
        font-weight: 700;
        color: #4f46e5;
    }

    .director-badge-pending {
        display: inline-flex;
        align-items: center;
        border-radius: 9999px;
        background-color: #fef3c7;
        padding: 0.25rem 0.625rem;
        font-size: 0.75rem;
        font-weight: 500;
        color: #92400e;
    }

    .director-badge-active {
        display: inline-flex;
        align-items: center;
        border-radius: 9999px;
        background-color: #d1fae5;
        padding: 0.25rem 0.625rem;
        font-size: 0.75rem;
        font-weight: 500;
        color: #065f46;
    }

    .director-chip {
        display: inline-flex;
        align-items: center;
        border-radius: 9999px;
        border: 1px solid #e2e8f0;
        background-color: #f8fafc;
        padding: 0.25rem 0.75rem;
        font-size: 0.75rem;
        font-weight: 500;
        color: #334155;
    }

    .director-alert-success {
        border-radius: 0.75rem;
        border: 1px solid #a7f3d0;
        background-color: #ecfdf5;
        padding: 0.75rem 1rem;
        font-size: 0.875rem;
        font-weight: 500;
        color: #065f46;
    }

    .director-alert-error {
        border-radius: 0.75rem;
        border: 1px solid #fecaca;
        background-color: #fef2f2;
        padding: 0.75rem 1rem;
        font-size: 0.875rem;
        color: #991b1b;
    }

    .director-alert-warning {
        border-radius: 0.75rem;
        border: 1px solid #fde68a;
        background-color: #fffbeb;
        padding: 0.75rem 1rem;
        font-size: 0.875rem;
        font-weight: 500;
        color: #78350f;
    }

    .director-info-box {
        border-radius: 0.75rem;
        border: 1px solid #e0e7ff;
        background-color: rgba(238, 242, 255, 0.6);
        padding: 1rem;
        font-size: 0.875rem;
        color: #334155;
    }

    .director-info-box strong {
        color: #0f172a;
    }

    .director-header {
        margin-bottom: 1.5rem;
        display: flex;
        flex-direction: column;
        gap: 1rem;
        border-radius: 1rem;
        border: 1px solid rgba(226, 232, 240, 0.8);
        background-color: #ffffff;
        padding: 1.25rem;
        box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    }

    @media (min-width: 1024px) {
        .director-header {
            flex-direction: row;
            align-items: center;
            justify-content: space-between;
        }
    }

    .director-link {
        font-size: 0.875rem;
        font-weight: 600;
        color: #4f46e5;
        text-decoration: none;
    }

    .director-link:hover {
        color: #3730a3;
    }

    .director-btn-secondary {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.375rem;
        border-radius: 0.5rem;
        border: 1px solid #cbd5e1;
        background-color: #ffffff;
        padding: 0.5rem 0.75rem;
        font-size: 0.75rem;
        font-weight: 600;
        color: #334155;
        transition: background-color 0.15s, border-color 0.15s;
    }

    .director-btn-secondary:hover {
        background-color: #f8fafc;
        border-color: #94a3b8;
    }

    .director-btn-danger {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 0.5rem;
        border: 1px solid #fecaca;
        background-color: #fef2f2;
        padding: 0.5rem 0.75rem;
        font-size: 0.75rem;
        font-weight: 600;
        color: #b91c1c;
    }

    .director-btn-danger:hover {
        background-color: #fee2e2;
    }

    .director-checkbox-label {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        border-radius: 0.5rem;
        border: 1px solid #cbd5e1;
        background-color: #f8fafc;
        padding: 0.625rem 0.75rem;
        font-size: 0.75rem;
        font-weight: 500;
        color: #334155;
    }

    .director-checkbox-label input[type="checkbox"] {
        border-radius: 0.25rem;
        border-color: #cbd5e1;
        color: #4f46e5;
    }

    @keyframes director-spin {
        to { transform: rotate(360deg); }
    }

    .director-spinner {
        width: 1rem;
        height: 1rem;
        border: 2px solid rgba(255, 255, 255, 0.35);
        border-top-color: #fff;
        border-radius: 50%;
        animation: director-spin 0.65s linear infinite;
    }

    html {
        overflow-x: hidden;
        -webkit-text-size-adjust: 100%;
    }

    body {
        overflow-x: hidden;
        max-width: 100%;
        padding-bottom: calc(24px + env(safe-area-inset-bottom));
    }

    .director-input,
    .director-select,
    .director-textarea {
        font-size: 16px;
        min-height: 44px;
    }

    @media (max-width: 767px) {
        .director-header {
            padding: 1rem;
            border-radius: 1rem;
        }
        .director-header > .flex.items-center.gap-4 {
            align-items: flex-start;
        }
        .director-page-title {
            font-size: 1.25rem;
            line-height: 1.25;
            word-break: break-word;
        }
        .director-page-subtitle {
            font-size: 0.8125rem;
            line-height: 1.45;
        }
        .director-card {
            padding: 1rem;
        }
        .director-header .relative.flex.items-center.gap-2 {
            align-self: flex-end;
            overflow: visible;
            z-index: 120;
        }
        .director-dash-header .shrink-0 {
            overflow: visible;
            z-index: 120;
        }
        .tracking-\[\.3em\],
        .tracking-\[\.25em\] {
            letter-spacing: 0.12em;
        }
    }

    html.dark .director-btn-primary {
        background-color: #6366f1;
        color: #ffffff;
        box-shadow: 0 8px 20px rgba(79, 70, 229, 0.28);
    }
    html.dark .director-btn-primary:hover:not(:disabled) {
        background-color: #818cf8;
    }
    html.dark .director-badge-pending {
        background-color: rgba(251, 191, 36, 0.16);
        color: #fde68a;
    }
    html.dark .director-badge-active {
        background-color: rgba(16, 185, 129, 0.16);
        color: #6ee7b7;
    }
    html.dark .director-alert-success {
        border-color: rgba(16, 185, 129, 0.35);
        background-color: rgba(6, 78, 59, 0.35);
        color: #a7f3d0;
    }
    html.dark .director-alert-error {
        border-color: rgba(248, 113, 113, 0.35);
        background-color: rgba(127, 29, 29, 0.35);
        color: #fecaca;
    }
    html.dark .director-input,
    html.dark .director-select,
    html.dark .director-textarea {
        border-color: #334155;
        background-color: #0f172a;
        color: #e2e8f0;
    }
    html.dark .director-input:focus,
    html.dark .director-select:focus,
    html.dark .director-textarea:focus {
        border-color: #818cf8;
        background-color: #0f172a;
        box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.35);
    }
    html.dark .director-select option {
        background-color: #0f172a;
        color: #e2e8f0;
    }
    html.dark .director-label { color: #cbd5e1; }
    html.dark .director-card {
        border-color: #1e293b;
        background-color: #0f172a;
    }
    html.dark .director-header {
        border-color: #1e293b;
        background-color: #0f172a;
    }
    html.dark .director-page-title,
    html.dark .director-section-title { color: #f8fafc; }
    html.dark .director-page-subtitle { color: #94a3b8; }
    html.dark .director-chip {
        border-color: #334155;
        background: #1e293b;
        color: #c7d2fe;
    }
    html.dark .director-code { color: #a5b4fc; }
    html.dark .director-link { color: #a5b4fc; }
    html.dark .director-link:hover { color: #c7d2fe; }
    html.dark .director-btn-secondary {
        border-color: #334155;
        background-color: #1e293b;
        color: #e2e8f0;
    }
</style>
