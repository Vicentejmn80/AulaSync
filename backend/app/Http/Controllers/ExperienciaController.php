<?php

namespace App\Http\Controllers;

class ExperienciaController extends Controller
{
    /**
     * Pantalla de espera: el robot escribe en bucle.
     * El toque cruza a robot-saludando.mp4 desde la vista.
     */
    public function idle()
    {
        return view('experiencia.idle', [
            'typing' => $this->clip('idle'),
            'wave' => $this->clip('wave'),
            'celebrate' => $this->clip('celebrate'),
            'kiosk' => config('experiencia.kiosk'),
        ]);
    }

    private function clip(string $base): array
    {
        $mp4 = config('experiencia.'.$base.'_video');
        if ($mp4 === null) {
            return ['mp4' => null, 'webm' => null, 'mov' => null, 'poster' => null];
        }

        return [
            'mp4' => $this->publicVideoUrl($mp4),
            'webm' => $this->publicVideoUrl(config('experiencia.'.$base.'_webm')),
            'mov' => $this->publicVideoUrl(config('experiencia.'.$base.'_mov')),
            'poster' => $base === 'idle' ? $this->publicVideoUrl(config('experiencia.idle_poster')) : null,
        ];
    }

    private function publicVideoUrl(mixed $relative): ?string
    {
        $path = $this->sanitizeRelativePath($relative);

        if ($path === null || ! is_file(public_path($path))) {
            return null;
        }

        return asset($path).'?v='.filemtime(public_path($path));
    }

    private function sanitizeRelativePath(mixed $relative): ?string
    {
        if (! is_string($relative)) {
            return null;
        }

        $relative = ltrim(trim($relative), '/');

        if ($relative === '' || str_contains($relative, '..') || str_contains($relative, '\\')) {
            return null;
        }

        return $relative;
    }

    private function stageBackground(): string
    {
        $color = config('experiencia.stage_background');

        return is_string($color) && preg_match('/^#[0-9A-Fa-f]{6}$/', $color) === 1
            ? $color
            : '#ffffff';
    }
}
