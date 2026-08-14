<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class OnboardingLayout extends Component
{
    public function render(): View
    {
        return view('layouts.onboarding');
    }
}
