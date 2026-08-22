<?php

namespace App\Http\Controllers;

use App\Models\Planificacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        $user = Auth::user();

        if ($user->isSuperAdmin()) {
            return redirect()->route('super-admin');
        }

        if (! $user->onboarding_completed) {
            return redirect()->route('onboarding');
        }

        $role = $user->role ?? 'profesor';

        // Directors have their own dedicated analytics dashboard
        if ($role === 'director') {
            return redirect()->route('director.dashboard');
        }

        // Teachers land on the academic hub (unless loading a specific plan)
        if ($role === 'profesor' && ! $request->has('plan')) {
            return redirect()->route('teacher.hub');
        }

        // Representatives land on their family dashboard
        if ($role === 'representante') {
            return redirect()->route('representante.dashboard');
        }

        $user->loadMissing('settings');
        $settings = $user->settings;

        $diasEn = ['Sunday' => 'Domingo', 'Monday' => 'Lunes', 'Tuesday' => 'Martes', 'Wednesday' => 'Miércoles', 'Thursday' => 'Jueves', 'Friday' => 'Viernes', 'Saturday' => 'Sábado'];
        $onboardingData = [
            'nombre' => $user->name ?? 'Docente',
            'dia_hoy' => $settings ? $settings->dia_hoy : ($diasEn[date('l')] ?? 'hoy'),
            'materia_principal' => $settings && ! empty($settings->materias_list) ? $settings->materias_list : 'hoy',
            'nivel_educativo' => $settings->nivel_educativo ?? null,
            'modelo_pedagogico' => $settings->modelo_pedagogico ?? null,
        ];

        $recentPlans = Cache::remember(
            "user:{$user->id}:recent-plans",
            now()->addMinutes(5),
            fn () => Planificacion::query()
                ->where('user_id', $user->id)
                ->latest()
                ->limit(3)
                ->get(['id', 'tema', 'created_at'])
        );

        $initialPlan = null;
        $planId = $request->query('plan');
        if ($planId) {
            $record = Planificacion::query()
                ->where('id', $planId)
                ->where('user_id', $user->id)
                ->first();

            if ($record) {
                $initialPlan = $record->payload;
                if (is_string($initialPlan)) {
                    $decoded = json_decode($initialPlan, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        $initialPlan = $decoded;
                    }
                }
                if (is_array($initialPlan) && isset($initialPlan['payload']) && is_array($initialPlan['payload'])) {
                    $initialPlan = $initialPlan['payload'];
                }
                if (! is_array($initialPlan) || empty($initialPlan)) {
                    $initialPlan = [
                        'tema' => $record->tema,
                        'objetivo' => $record->objetivo,
                        'inicio' => ['actividades' => []],
                        'desarrollo' => ['actividades' => []],
                        'cierre' => ['actividades' => []],
                        'recursos' => [],
                    ];
                }
            }
        }

        return view('ia-dashboard', compact('user', 'role', 'onboardingData', 'initialPlan', 'recentPlans'));
    }
}