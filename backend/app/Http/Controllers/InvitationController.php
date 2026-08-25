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

    public function show(string $token): View
    {
        $invitation = Invitation::query()->where('token', $token)->first();

        if (! $invitation || ! $invitation->isPending()) {
            return view('invitations.expired');
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
                ->route('invitations.show', $invitation->token)
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

        return redirect($this->invitations->dashboardUrl($user))
            ->with('success', 'Tu cuenta quedó lista. Desde ahora entra por Iniciar sesión.');
    }
}
