<?php

namespace App\Http\Controllers;

use App\Models\ChatSession;
use App\Models\ChatMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ChatController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        $sessions = ChatSession::where('user_id', $user->id)
            ->orWhere('admin_id', $user->id)
            ->with(['user', 'admin', 'messages' => function($query) {
                $query->latest()->limit(1);
            }])
            ->orderBy('last_message_at', 'desc')
            ->paginate(20);

        return view('chat.index', compact('sessions'));
    }

    public function show(ChatSession $session)
    {
        $this->authorize('view', $session);

        $session->load(['user', 'admin', 'messages' => function($query) {
            $query->orderBy('created_at', 'asc');
        }]);

        // Помечаем сообщения как прочитанные
        $session->messages()
            ->where('user_id', '!=', Auth::id())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return view('chat.show', compact('session'));
    }

    public function sendMessage(ChatSession $session, Request $request)
    {
        $this->authorize('sendMessage', $session);

        $request->validate([
            'message' => 'required|string|max:2000',
        ]);

        $user = Auth::user();
        $isAdmin = $user->hasRole('admin');

        $message = ChatMessage::create([
            'chat_session_id' => $session->id,
            'user_id' => $isAdmin ? null : $user->id,
            'admin_id' => $isAdmin ? $user->id : null,
            'message' => $request->message,
            'is_admin_message' => $isAdmin,
        ]);

        // Обновляем время последнего сообщения
        $session->updateLastMessageTime();

        // Отправляем событие WebSocket
        event(new \App\Events\NewChatMessage($message, $user, $isAdmin ? $session->user_id : $session->admin_id));

        return redirect()->route('chat.show', $session)
            ->with('success', 'Сообщение отправлено');
    }

    public function start(Request $request)
    {
        $user = Auth::user();

        $session = ChatSession::create([
            'user_id' => $user->id,
            'session_id' => uniqid('chat_'),
            'status' => 'active',
            'last_message_at' => now(),
        ]);

        return redirect()->route('chat.show', $session);
    }

    public function resolve(ChatSession $session, Request $request)
    {
        $this->authorize('resolve', $session);

        $session->markAsResolved($request->notes);

        return redirect()->route('chat.index')
            ->with('success', 'Чат закрыт');
    }
}
