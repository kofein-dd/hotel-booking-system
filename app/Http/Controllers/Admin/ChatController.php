<?php

namespace App\Http\Controllers;

use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ChatController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        // Для админов показываем список чатов с пользователями
        if ($user->isAdmin()) {
            $chats = User::whereHas('chatMessages')
                ->with(['chatMessages' => function($query) {
                    $query->latest()->first();
                }])
                ->paginate(20);

            return view('admin.chat.index', compact('chats'));
        }

        // Для обычных пользователей показываем их чат
        $messages = ChatMessage::where('user_id', $user->id)
            ->orWhere('admin_id', $user->id)
            ->orderBy('created_at')
            ->get();

        $unreadCount = ChatMessage::where('user_id', $user->id)
            ->where('is_admin_message', true)
            ->whereNull('read_at')
            ->count();

        return view('chat.index', compact('messages', 'unreadCount'));
    }

    public function show(User $user = null)
    {
        $currentUser = Auth::user();

        if ($currentUser->isAdmin() && $user) {
            // Админ просматривает чат с конкретным пользователем
            $messages = ChatMessage::where(function($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhere('admin_id', $user->id);
            })
                ->orderBy('created_at')
                ->get();

            // Помечаем сообщения как прочитанные
            ChatMessage::where('user_id', $user->id)
                ->where('is_admin_message', false)
                ->whereNull('read_at')
                ->update(['read_at' => now()]);

            return view('admin.chat.show', compact('messages', 'user'));
        }

        // Пользователь просматривает свой чат
        $messages = ChatMessage::where('user_id', $currentUser->id)
            ->orWhere('admin_id', $currentUser->id)
            ->orderBy('created_at')
            ->get();

        // Помечаем сообщения админа как прочитанные
        ChatMessage::where('user_id', $currentUser->id)
            ->where('is_admin_message', true)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return view('chat.show', compact('messages'));
    }

    public function store(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'message' => 'required|string|max:2000',
            'user_id' => 'nullable|exists:users,id', // Для админа
        ]);

        if ($user->isAdmin()) {
            // Сообщение от админа пользователю
            ChatMessage::create([
                'user_id' => $validated['user_id'],
                'admin_id' => $user->id,
                'message' => $validated['message'],
                'is_admin_message' => true,
            ]);

            // Отправляем уведомление в Telegram
            $this->sendTelegramNotification($validated['user_id'], $validated['message']);
        } else {
            // Сообщение от пользователя админу
            ChatMessage::create([
                'user_id' => $user->id,
                'message' => $validated['message'],
                'is_admin_message' => false,
            ]);

            // Отправляем уведомление админам в Telegram
            $this->notifyAdmins($user, $validated['message']);
        }

        return redirect()->back()->with('success', 'Сообщение отправлено');
    }

    private function sendTelegramNotification($userId, $message)
    {
        // Интеграция с Telegram ботом для уведомления пользователя
        // Реализация зависит от выбранного способа интеграции
    }

    private function notifyAdmins($user, $message)
    {
        // Отправка уведомления всем админам о новом сообщении
        $admins = User::where('role', 'admin')->get();

        foreach ($admins as $admin) {
            // Отправка в Telegram
            // Можно также отправлять email уведомления
        }
    }

    public function getNewMessages(Request $request)
    {
        $user = Auth::user();
        $lastMessageId = $request->get('last_message_id', 0);

        $messages = ChatMessage::where('id', '>', $lastMessageId)
            ->where(function($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhere('admin_id', $user->id);
            })
            ->orderBy('created_at')
            ->get();

        return response()->json([
            'messages' => $messages,
            'count' => $messages->count()
        ]);
    }
}
