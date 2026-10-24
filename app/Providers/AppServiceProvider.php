<?php

namespace App\Providers;

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
    }
}
