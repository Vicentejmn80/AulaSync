<?php

namespace App\Http\Controllers;

use App\Helpers\InviteCodeHelper;
use App\Models\Colegio;
use App\Models\Invitation;
use App\Models\TeacherInvite;
use App\Services\InvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class InvitationController extends Controller
{
    public function __construct(private InvitationService $invitations) {}

    public function show(Request $request, ?string $token = null): View
    {
        $token = $token ?: (string) $request->query('token', '');
        if ($token !== '') {
            $invitation = Invitation::query()->with(['colegio', 'teacherInvite'])->where('token', $token)->first();

            if (! $invitation || ! $invitation->isPending()) {
                return view('invitations.expired', compact('invitation'));
            }

            return view('invitations.show', [
                'invitation' => $invitation,
                'teacherInvite' => null,
                'colegio' => $invitation->colegio,
            ]);
        }

        $resolved = $this->resolveTeacherInvite(
            (string) $request->query('school', ''),
            (string) $request->query('code', '')
        );

        if (! $resolved) {
            return view('invitations.expired', ['invitation' => null]);
        }

        return view('invitations.show', [
            'invitation' => null,
            'teacherInvite' => $resolved['invite'],
            'colegio' => $resolved['colegio'],
        ]);
    }

    public function accept(Request $request): RedirectResponse
    {
        if ($request->filled('school') && $request->filled('code') && ! $request->filled('token')) {
            return $this->acceptTeacherCode($request);
        }

        $data = $request->validate([
            'token' => ['required', 'exists:invitations,token'],
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $invitation = Invitation::query()->where('token', $data['token'])->firstOrFail();

        if (! $invitation->isPending()) {
            return redirect()
                ->route('onboarding.teacher', ['token' => $invitation->token])
                ->with('error', 'Esta invitación ya no es válida.');
        }

        $user = $this->invitations->accept($invitation, $data['name'], $data['password']);

        return $this->finishAccept($request, $user);
    }

    private function acceptTeacherCode(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'school' => ['required', 'string', 'max:20'],
            'code' => ['required', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:180'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $resolved = $this->resolveTeacherInvite($data['school'], $data['code']);
        if (! $resolved) {
            return redirect()
                ->route('onboarding.teacher', [
                    'school' => $data['school'],
                    'code' => $data['code'],
                ])
                ->with('error', 'Esta invitación ya no es válida.');
        }

        $user = $this->invitations->acceptTeacherInvite(
            $resolved['invite'],
            $data['name'],
            $data['email'],
            $data['password']
        );

        return $this->finishAccept($request, $user);
    }

    /**
     * @return array{colegio: Colegio, invite: TeacherInvite}|null
     */
    private function resolveTeacherInvite(string $school, string $code): ?array
    {
        $school = InviteCodeHelper::normalize($school);
        $code = InviteCodeHelper::normalize($code);
        if ($school === '' || $code === '') {
            return null;
        }

        $colegio = Colegio::query()->where('invite_code', $school)->first();
        if (! $colegio) {
            return null;
        }

        $invite = TeacherInvite::query()
            ->where('colegio_id', $colegio->id)
            ->where('invite_code', $code)
            ->whereNull('claimed_by')
            ->whereNull('claimed_at')
            ->whereNull('revoked_at')
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->first();

        if (! $invite || ! $invite->isActive() || $invite->isClaimed()) {
            return null;
        }

        return ['colegio' => $colegio, 'invite' => $invite];
    }

    private function finishAccept(Request $request, $user): RedirectResponse
    {
        Auth::login($user);
        $request->session()->regenerate();

        DB::table('users')->where('id', $user->id)->update([
            'last_login_at' => now(),
        ]);

        if ($user->role === 'director') {
            return redirect()->route('onboarding')
                ->with('success', 'Tu cuenta quedó lista. Completa tu perfil para entrar al panel.');
        }

        return redirect($this->invitations->dashboardUrl($user))
            ->with('success', '¡Cuenta activada exitosamente! Ya puedes iniciar sesión con tu email y contraseña.');
    }
}
