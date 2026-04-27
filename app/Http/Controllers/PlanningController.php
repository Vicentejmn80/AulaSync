<?php

namespace App\Http\Controllers;

use App\Models\Planificacion;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class PlanningController extends Controller
{
    public function index(): View
    {
        $teacherId = Auth::id();
        $plans = Planificacion::query()
            ->where('user_id', $teacherId)
            ->latest()
            ->get();

        Log::debug('MY_PLANS_QUERY', [
            'teacher_id' => $teacherId,
            'source' => 'PlanningController@index',
            'filters' => [
                'user_id' => $teacherId,
            ],
            'count' => $plans->count(),
        ]);

        return view('planning.index', compact('plans'));
    }
}

