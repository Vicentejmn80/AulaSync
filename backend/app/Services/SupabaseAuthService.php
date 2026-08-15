<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SupabaseAuthService
{
    public function isConfigured(): bool
    {
        return config('supabase.enabled')
            && config('supabase.url')
            && config('supabase.service_role_key');
    }

    /**
     * Create (or link) a Supabase Auth user for a Laravel user.
     * Requires the plain password — only available at registration time.
     */
    public function syncUser(User $user, string $plainPassword): ?string
    {
        if (! $this->isConfigured()) {
            return null;
        }

        if ($user->supabase_id) {
            return $user->supabase_id;
        }

        $existingId = $this->findAuthUserIdByEmail($user->email);
        if ($existingId) {
            $user->update(['supabase_id' => $existingId]);

            return $existingId;
        }

        $response = Http::withHeaders($this->headers())
            ->post($this->endpoint('/auth/v1/admin/users'), [
                'email' => $user->email,
                'password' => $plainPassword,
                'email_confirm' => true,
                'user_metadata' => [
                    'name' => $user->name,
                    'role' => $user->role,
                    'laravel_user_id' => $user->id,
                ],
            ]);

        if ($response->successful()) {
            $authId = $response->json('id');
            if ($authId) {
                $user->update(['supabase_id' => $authId]);
            }

            return $authId;
        }

        Log::warning('Supabase Auth sync failed', [
            'user_id' => $user->id,
            'email' => $user->email,
            'status' => $response->status(),
            'body' => $response->json() ?? $response->body(),
        ]);

        return null;
    }

    /**
     * Backfill Auth users when the plain password is unknown (existing Laravel users).
     * Creates the Auth account with a random password; user should reset via email.
     */
    public function provisionExistingUser(User $user): ?string
    {
        if (! $this->isConfigured()) {
            return null;
        }

        if ($user->supabase_id) {
            return $user->supabase_id;
        }

        $existingId = $this->findAuthUserIdByEmail($user->email);
        if ($existingId) {
            $user->update(['supabase_id' => $existingId]);

            return $existingId;
        }

        $tempPassword = Str::password(32);

        $response = Http::withHeaders($this->headers())
            ->post($this->endpoint('/auth/v1/admin/users'), [
                'email' => $user->email,
                'password' => $tempPassword,
                'email_confirm' => true,
                'user_metadata' => [
                    'name' => $user->name,
                    'role' => $user->role,
                    'laravel_user_id' => $user->id,
                    'provisioned_by' => 'aulasync-backfill',
                ],
            ]);

        if ($response->successful()) {
            $authId = $response->json('id');
            if ($authId) {
                $user->update(['supabase_id' => $authId]);
            }

            return $authId;
        }

        Log::warning('Supabase Auth backfill failed', [
            'user_id' => $user->id,
            'email' => $user->email,
            'status' => $response->status(),
            'body' => $response->json() ?? $response->body(),
        ]);

        return null;
    }

    private function findAuthUserIdByEmail(string $email): ?string
    {
        $response = Http::withHeaders($this->headers())
            ->get($this->endpoint('/auth/v1/admin/users'), [
                'email' => $email,
            ]);

        if (! $response->successful()) {
            return null;
        }

        $users = $response->json('users') ?? [];

        return $users[0]['id'] ?? null;
    }

    private function endpoint(string $path): string
    {
        return config('supabase.url').$path;
    }

    private function headers(): array
    {
        $key = config('supabase.service_role_key');

        return [
            'apikey' => $key,
            'Authorization' => 'Bearer '.$key,
            'Content-Type' => 'application/json',
        ];
    }
}
