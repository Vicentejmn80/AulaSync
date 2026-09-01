<?php

namespace App\Http\Controllers;

use App\Models\FamilyInvite;
use App\Services\FamilyInviteService;
use App\Services\InvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class FamilyInviteController extends Controller
{
    public function __construct(
        private FamilyInviteService $families,
        private InvitationService $invitations,
    ) {}

    public function show(Request $request): View|RedirectResponse
    {
        $invite = $this->families->resolve(
            (string) $request->query('school', ''),
            (string) $request->query('code', '')
        );

        if (! $invite) {
            return view('invitations.expired', ['invitation' => null]);
        }

        $user = $request->user();
        if ($user && $user->role === 'representante') {
            try {
                $this->families->attachUser($user, $invite);
            } catch (\Illuminate\Validation\ValidationException $e) {
                return redirect()->route('representante.dashboard')
                    ->with('error', collect($e->errors())->flatten()->first());
            }

            return redirect()->route('representante.dashboard')
                ->with('success', 'Ya puedes ver a tus hijos en el panel familiar.');
        }

        if ($user && $user->role !== 'representante') {
            return view('familia.join', [
                'invite' => $invite,
                'share' => $this->families->serialize($invite),
                'blocked' => true,
            ]);
        }

        return view('familia.join', [
            'invite' => $invite,
            'share' => $this->families->serialize($invite),
            'blocked' => false,
        ]);
    }

    public function accept(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'school' => ['nullable', 'string', 'max:20'],
            'code' => ['required', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:180'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $invite = $this->families->resolve((string) ($data['school'] ?? ''), $data['code']);
        if (! $invite) {
            return redirect()
                ->route('familia.join', ['school' => $data['school'] ?? null, 'code' => $data['code']])
                ->with('error', 'Esta invitación ya no es válida.');
        }

        $user = $this->families->accept($invite, $data['name'], $data['email'], $data['password']);

        Auth::login($user);
        $request->session()->regenerate();
        DB::table('users')->where('id', $user->id)->update(['last_login_at' => now()]);

        return redirect($this->invitations->dashboardUrl($user))
            ->with('success', 'Cuenta lista. Ya puedes ver el calendario y las notas de tus hijos.');
    }
}
