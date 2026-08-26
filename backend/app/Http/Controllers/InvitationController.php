<?php

namespace App\Http\Controllers;

use App\Models\Invitation;
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
        $invitation = $token !== ''
            ? Invitation::query()->with(['colegio', 'teacherInvite'])->where('token', $token)->first()
            : null;

        if (! $invitation || ! $invitation->isPending()) {
            return view('invitations.expired', compact('invitation'));
        }

        return view('invitations.show', compact('invitation'));
    }

    public function accept(Request $request): RedirectResponse
    {
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

        Auth::login($user);
        $request->session()->regenerate();

        DB::table('users')->where('id', $user->id)->update([
            'last_login_at' => now(),
        ]);

        if ($user->role === 'director') {
            return redirect()->route('onboarding')
                ->with('success', 'Tu cuenta quedó lista. Completa tu perfil para entrar al panel.');
        }

        $hub = $this->invitations->dashboardUrl($user);

        return redirect($hub)
            ->with('success', '¡Cuenta activada exitosamente! Ya puedes iniciar sesión con tu email y contraseña.');
    }
}
