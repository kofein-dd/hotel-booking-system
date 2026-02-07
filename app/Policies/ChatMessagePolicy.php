<?php

namespace App\Policies;

use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ChatMessagePolicy
{
    use HandlesAuthorization;

    public function view(User $user, ChatMessage $message)
    {
        return $user->id === $message->user_id ||
            ($user->hasRole('admin') && $user->id === $message->admin_id);
    }

    public function delete(User $user, ChatMessage $message)
    {
        return $user->hasRole('admin');
    }
}
