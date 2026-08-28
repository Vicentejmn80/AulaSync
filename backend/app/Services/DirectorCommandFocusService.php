<?php

namespace App\Services;

/**
 * Extrae las órdenes ejecutables de un texto largo.
 * Destaca "necesito que…" y verbos (crea, modifica, agrega, elimina, aumenta, disminuye…).
 */
class DirectorCommandFocusService
{
    public const CUES = 'necesito que|necesito|quiero que me|quiero que|me gustar[ií]a que|te pido que|haz que|ordeno que|requiero que|por favor|favor de|i need you to|i need to|please';

    public const VERBS = 'crea(?:r|me)?|crees|cree|creo|modifica(?:r|s|n)?|modifiques?|agrega(?:r|le|lo|s|n)?|agregues?|elimina(?:r|s|n)?|elimines?|borra(?:r|s)?|quita(?:r|s)?|aumenta(?:r|s|n)?|aumentes?|incrementa(?:r)?|disminu(?:ye|ir|yas)?|reduce(?:r)?|asigna(?:r|le|lo|s)?|matricula(?:r|s)?|inscribe(?:r)?|mueve(?:r|s)?|cambia(?:r|s)?|actualiza(?:r)?|edita(?:r)?|invita(?:r)?|desmatricula(?:r)?|sube|baja|create|add|update|delete|remove|assign';

    /**
     * @return array{
     *     original:string,
     *     focused:string,
     *     working:string,
     *     for_model:string,
     *     cues:array<int,string>,
     *     verbs:array<int,string>,
     *     clauses:array<int,string>
     * }
     */
    public function extract(string $text): array
    {
        $original = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
        if ($original === '') {
            return $this->pack('', '', [], []);
        }

        $cues = $this->matchTokens($original, self::CUES);
        $verbs = $this->matchTokens($original, self::VERBS);
        $fromCue = $this->fromFirstCue($original);
        $focused = $fromCue ?? $original;
        $clauses = $fromCue ? [$fromCue] : [];

        return $this->pack($original, $focused, $cues, $verbs, $clauses);
    }

    public function workingText(string $text): string
    {
        return $this->extract($text)['working'];
    }

    private function fromFirstCue(string $text): ?string
    {
        if (! preg_match('/\b(?:'.self::CUES.')\b.+/iu', $text, $match, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $slice = trim((string) $match[0][0]);
        if (mb_strlen($slice) < 12) {
            return null;
        }

        // Nunca recortar si el texto anterior al "cue" ya contiene una orden.
        // Antes se perdían acciones completas ("Crea al profesor X. Necesito que
        // agregues a Y") porque solo viajaba al LLM el trozo posterior al cue.
        $prefix = substr($text, 0, (int) $match[0][1]);
        if ($prefix !== '' && (
            preg_match('/\b(?:'.self::VERBS.')\b/iu', $prefix)
            || preg_match('/\b(?:profesor(?:a)?|docente|maestr[oa]|alumn[oa]|estudiante)s?\b/iu', $prefix)
        )) {
            return null;
        }

        if (! preg_match('/\b(?:'.self::VERBS.')\b/iu', $slice) && ! preg_match('/\b(?:'.self::CUES.')\b/iu', $slice)) {
            return null;
        }

        return $slice;
    }

    /**
     * @param  array<int,string>  $cues
     * @param  array<int,string>  $verbs
     * @param  array<int,string>  $clauses
     * @return array{
     *     original:string,
     *     focused:string,
     *     working:string,
     *     for_model:string,
     *     cues:array<int,string>,
     *     verbs:array<int,string>,
     *     clauses:array<int,string>
     * }
     */
    private function pack(string $original, string $focused, array $cues, array $verbs, array $clauses = []): array
    {
        $working = $focused !== '' ? $focused : $original;

        return [
            'original' => $original,
            'focused' => $working,
            'working' => $working,
            'for_model' => $this->modelPrompt($original, $working, $cues, $verbs),
            'cues' => $cues,
            'verbs' => $verbs,
            'clauses' => $clauses,
        ];
    }

    /**
     * @param  array<int,string>  $cues
     * @param  array<int,string>  $verbs
     */
    private function modelPrompt(string $original, string $focused, array $cues, array $verbs): string
    {
        if ($focused === '' || mb_strtolower($focused) === mb_strtolower($original)) {
            return $original;
        }

        $cueList = $cues !== [] ? implode(', ', $cues) : '—';
        $verbList = $verbs !== [] ? implode(', ', $verbs) : '—';

        return "ÓRDENES CLAVE (prioridad, ejecuta estas):\n{$focused}\n\n"
            ."Palabras destacadas: {$cueList} / {$verbList}\n\n"
            ."Texto original del director:\n{$original}";
    }

    /**
     * @return array<int,string>
     */
    private function matchTokens(string $text, string $pattern): array
    {
        if (! preg_match_all('/\b(?:'.$pattern.')\b/iu', $text, $matches)) {
            return [];
        }

        $tokens = [];
        foreach ($matches[0] as $token) {
            $normalized = mb_strtolower(trim((string) $token));
            if ($normalized !== '' && ! in_array($normalized, $tokens, true)) {
                $tokens[] = $normalized;
            }
        }

        return $tokens;
    }
}
