<?php

namespace App\Policies;

use App\Models\ChatSession;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ChatSessionPolicy
{
    use HandlesAuthorization;

    public function view(User $user, ChatSession $session)
    {
        return $user->id === $session->user_id ||
            ($user->hasRole('admin') && $user->id === $session->admin_id);
    }

    public function sendMessage(User $user, ChatSession $session)
    {
        return $this->view($user, $session) && $session->isActive();
    }

    public function resolve(User $user, ChatSession $session)
    {
        return $user->hasRole('admin') && $session->isActive();
    }

    public function delete(User $user, ChatSession $session)
    {
        return $user->hasRole('admin');
    }
}
