# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

```bash
# Initial setup
composer setup          # Install deps, generate .env, app key, run migrations, npm install + build

# Development
composer dev            # Start Laravel server, queue listener, log viewer, and Vite dev server concurrently
npm run dev             # Vite dev server only (CSS/JS hot reload)
npm run build           # Build production assets

# Testing
composer test           # Clear config cache and run PHPUnit test suite
php artisan test --filter TestName   # Run a single test by name

# Code formatting
./vendor/bin/pint       # Laravel Pint PHP code formatter
```

## Architecture

**Laravel 12 + Vite + Alpine.js** app that helps teachers generate AI-powered lesson plans via OpenAI GPT-3.5-turbo.

### Request Flow

```
Browser (Alpine.js fetch) → routes/web.php → AIController → OpenAI API → JSON response → Alpine.js renders UI
```

### Key Files

- `routes/web.php` — All application routes (auth-protected with `auth` middleware)
- `app/Http/Controllers/AIController.php` — Core AI logic: generates/improves lesson plans by calling OpenAI, returns JSON
- `app/Models/UserSettings.php` — Per-user teacher preferences (subjects, pedagogical style, tone, schedule) stored as JSON columns
- `app/Models/Planificacion.php` — Saved lesson plans
- `resources/views/ia-dashboard.blade.php` — Main UI (932 lines); Alpine.js handles all interactivity and fetch calls

### AI Integration

- **Provider:** OpenAI GPT-3.5-turbo via `config/services.php` → env var `OPENAI_API_KEY`
- **Main endpoint:** `POST /generate-ai` — builds a prompt using `UserSettings` context, returns JSON with `tema`, `objetivo`, `inicio`, `desarrollo`, `cierre` (lesson phases), and `recursos`
- **Improvement endpoint:** `POST /improve-section` — re-prompts OpenAI to refine a single phase
- **Pro endpoints:** `POST /plan-pro/nee` (special needs adaptation), `/plan-pro/calendario` (timeline), `/plan-pro/materiales` (materials list)
- **Response parsing:** Responses may wrap JSON in markdown code fences; the controller strips them before `json_decode`

### Models & Relationships

```
User (1) ──has one──> UserSettings   (teacher preferences)
User (1) ──has many─> Planificacion  (saved lesson plans)
```

`UserSettings` stores arrays (materias, dias_clase, cursos_grados, preferencias) as JSON columns.

### Frontend Stack

- **Alpine.js 3** — all dashboard interactivity (no Vue/React)
- **Tailwind CSS 3** (via Vite) + **Bootstrap 5.3** (CDN)
- **Font Awesome 6.4** and **Animate.css 4.1.1** loaded via CDN (Animate.css is CSS-only — no JS file)
- Vite processes `resources/css/app.css` and `resources/js/app.js`

### Database

SQLite by default (configured in `.env`). Uses the `database` driver for sessions, cache, and queues. Test suite uses an in-memory SQLite database (`phpunit.xml`).
