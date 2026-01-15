<?php

namespace App\Providers;

use App\Enums\Role;
use App\Models\Conversation;
use App\Models\User;
use App\Policies\ConversationPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Conversation::class => ConversationPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        Gate::define('admin', function (User $user): bool {
            return $user->role === Role::Admin || $user->hasRole(Role::Admin->value);
        });

        Gate::define('agent', function (User $user): bool {
            return $user->role === Role::Agent || $user->hasRole(Role::Agent->value);
        });
    }
}
