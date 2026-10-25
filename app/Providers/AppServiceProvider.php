<?php

namespace App\Providers;

use App\Models\Center;
use App\Models\Course;
use App\Models\CourseType;
use App\Models\Exam;
use App\Models\RetakeFee;
use App\Models\Student;
use App\Models\Subject;
use App\Models\SubjectTariff;
use App\Models\User;
use App\Observers\AuditObserver;
use App\Policies\RolePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // نموذج Role من حزمة خارجية، فلا تُكتشف سياسته تلقائيًا.
        Gate::policy(Role::class, RolePolicy::class);

        // سجل التدقيق التلقائي للإضافة والتعديل.
        foreach ([
            Student::class,
            Course::class,
            Subject::class,
            SubjectTariff::class,
            CourseType::class,
            Center::class,
            RetakeFee::class,
            Exam::class,
            User::class,
        ] as $model) {
            $model::observe(AuditObserver::class);
        }
    }
}
