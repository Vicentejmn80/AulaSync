<?php

namespace App\Providers;

use App\Models\Course;
use App\Models\Student;
use App\Models\TeacherInvite;
use App\Observers\StudentObserver;
use App\Policies\CoursePolicy;
use App\Policies\StudentPolicy;
use App\Policies\TeacherInvitePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Student::observe(StudentObserver::class);
        Gate::policy(Course::class, CoursePolicy::class);
        Gate::policy(Student::class, StudentPolicy::class);
        Gate::policy(TeacherInvite::class, TeacherInvitePolicy::class);
    }
}
