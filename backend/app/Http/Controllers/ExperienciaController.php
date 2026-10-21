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
            'typingVideoUrl' => $this->publicVideoUrl(config('experiencia.idle_video')),
            'waveVideoUrl' => $this->publicVideoUrl(config('experiencia.wave_video')),
            'celebrateVideoUrl' => $this->publicVideoUrl(config('experiencia.celebrate_video')),
            'stageBackground' => $this->stageBackground(),
            'kiosk' => config('experiencia.kiosk'),
        ]);
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
