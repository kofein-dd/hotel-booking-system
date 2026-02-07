<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        // ... существующие политики ...
        \App\Models\ChatSession::class => \App\Policies\ChatSessionPolicy::class,
        \App\Models\ChatMessage::class => \App\Policies\ChatMessagePolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // Определяем gate для админа
        Gate::define('admin', function ($user) {
            return $user->hasRole('admin');
        });

        // Gate для управления предложениями FAQ
        Gate::define('view-faq-suggestions', function ($user) {
            return $user->hasRole('admin') || $user->hasRole('moderator');
        });

        Gate::define('manage-faq-suggestions', function ($user) {
            return $user->hasRole('admin');
        });
    }
}
