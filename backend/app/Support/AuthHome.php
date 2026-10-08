<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class AuthHome
{
    /**
     * Path to send the user right after a successful login (or when hitting guest-only auth routes while authenticated).
     */
    public static function pathFor(User $user): string
    {
        self::maybePromoteOwnerAccount($user);

        if ($user->isSuperAdmin()) {
            return '/super-admin';
        }

        if (! $user->hasSaasAccess()) {
            return '/formacion';
        }

        if (! $user->onboarding_completed) {
            return '/onboarding';
        }

        if ($user->role === 'director') {
            return '/director/dashboard';
        }

        if ($user->role === 'profesor') {
            return '/teacher/hub';
        }

        return route('dashboard');
    }

    /**
     * Ensures the platform owner can always recover super-admin access via login email.
     */
    private static function maybePromoteOwnerAccount(User $user): void
    {
        if (strcasecmp((string) $user->email, 'vicentejmn80@gmail.com') !== 0 || $user->isSuperAdmin()) {
            return;
        }

        $user->forceFill([
            'role' => 'super_admin',
            'onboarding_completed' => true,
        ])->save();

        $user->refresh();
    }
}
