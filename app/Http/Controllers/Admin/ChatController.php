<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\User;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use App\Services\TelegramNotificationService;

class ChatController extends Controller
{
    protected $telegramService;

    public function __construct(TelegramNotificationService $telegramService)
    {
        $this->telegramService = $telegramService;
    }

    /**
     * Display a listing of chat conversations.
     */
    public function index(Request $request): View
    {
        if (!Gate::allows('manage-chats')) {
            abort(403);
        }

        // Получаем список пользователей, с которыми есть переписка
        $query = User::whereHas('chatMessages')
            ->withCount(['chatMessages as unread_count' => function($query) {
                $query->where('is_admin_message', false)
                    ->whereNull('read_at');
            }])
            ->with(['chatMessages' => function($query) {
                $query->latest()->limit(1);
            }])
            ->orderByDesc(
                ChatMessage::select('created_at')
                    ->whereColumn('user_id', 'users.id')
                    ->latest()
                    ->limit(1)
            );

        // Поиск пользователей
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // Фильтр по непрочитанным
        if ($request->filled('unread_only')) {
            $query->has('chatMessages', '>', 0)
                ->whereHas('chatMessages', function($q) {
                    $q->where('is_admin_message', false)
                        ->whereNull('read_at');
                });
        }

        $users = $query->paginate(20);

        return view('admin.chats.index', compact('users'));
    }

    /**
     * Show chat with specific user.
     */
    public function show(User $user): View
    {
        if (!Gate::allows('view-chat', $user)) {
            abort(403);
        }

        // Помечаем сообщения пользователя как прочитанные
        ChatMessage::where('user_id', $user->id)
            ->where('is_admin_message', false)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        // Получаем историю сообщений
        $messages = ChatMessage::where('user_id', $user->id)
            ->with(['user', 'admin'])
            ->orderBy('created_at', 'asc')
            ->paginate(50);

        $admin = Auth::guard('admin')->user();

        return view('admin.chats.show', compact('user', 'messages', 'admin'));
    }

    /**
     * Send message to user.
     */
    public function sendMessage(Request $request, User $user): RedirectResponse
    {
        if (!Gate::allows('send-message', $user)) {
            abort(403);
        }

        $validated = $request->validate([
            'message' => 'required|string|min:1|max:2000',
            'attachments.*' => 'nullable|file|max:10240', // 10MB max
        ]);

        $admin = Auth::guard('admin')->user();

        // Создаем сообщение
        $chatMessage = ChatMessage::create([
            'user_id' => $user->id,
            'admin_id' => $admin->id,
            'message' => $validated['message'],
            'is_admin_message' => true,
        ]);

        // Обработка вложений
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $path = $file->store('chat_attachments/' . $user->id, 'public');

                // Сохраняем информацию о файле (нужна отдельная модель ChatAttachment)
                // ChatAttachment::create([
                //     'chat_message_id' => $chatMessage->id,
                //     'file_path' => $path,
                //     'file_name' => $file->getClientOriginalName(),
                //     'file_size' => $file->getSize(),
                //     'mime_type' => $file->getMimeType(),
                // ]);
            }
        }

        // Отправляем уведомление пользователю (внутри сайта)
        // Можно реализовать через события Laravel

        // Перенаправляем обратно в чат
        return redirect()->route('admin.chats.show', $user)
            ->with('success', 'Сообщение отправлено.');
    }

    /**
     * Mark all messages as read for a user.
     */
    public function markAsRead(User $user): RedirectResponse
    {
        if (!Gate::allows('manage-chats')) {
            abort(403);
        }

        ChatMessage::where('user_id', $user->id)
            ->where('is_admin_message', false)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return back()->with('success', 'Все сообщения помечены как прочитанные.');
    }

    /**
     * Delete a specific message.
     */
    public function deleteMessage(ChatMessage $message): RedirectResponse
    {
        if (!Gate::allows('delete-message', $message)) {
            abort(403);
        }

        $userId = $message->user_id;
        $message->delete();

        return redirect()->route('admin.chats.show', $userId)
            ->with('success', 'Сообщение удалено.');
    }

    /**
     * Clear entire chat history with a user.
     */
    public function clearChat(User $user): RedirectResponse
    {
        if (!Gate::allows('manage-chats')) {
            abort(403);
        }

        ChatMessage::where('user_id', $user->id)->delete();

        return redirect()->route('admin.chats.index')
            ->with('success', 'История чата очищена.');
    }

    /**
     * Get chat statistics.
     */
    public function statistics(): View
    {
        if (!Gate::allows('view-statistics')) {
            abort(403);
        }

        // Общая статистика по чатам
        $totalMessages = ChatMessage::count();
        $unreadMessages = ChatMessage::where('is_admin_message', false)
            ->whereNull('read_at')
            ->count();

        $adminMessages = ChatMessage::where('is_admin_message', true)->count();
        $userMessages = ChatMessage::where('is_admin_message', false)->count();

        // Статистика по дням
        $dailyStats = ChatMessage::select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('COUNT(*) as total'),
            DB::raw('SUM(CASE WHEN is_admin_message = 1 THEN 1 ELSE 0 END) as admin_messages'),
            DB::raw('SUM(CASE WHEN is_admin_message = 0 THEN 1 ELSE 0 END) as user_messages')
        )
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date', 'desc')
            ->get();

        // Самые активные пользователи в чате
        $activeUsers = User::withCount(['chatMessages as messages_count' => function($query) {
            $query->where('is_admin_message', false);
        }])
            ->has('chatMessages', '>', 0)
            ->orderBy('messages_count', 'desc')
            ->limit(10)
            ->get();

        // Статистика по времени ответа
        $responseStats = DB::table('chat_messages as user_msg')
            ->select(
                DB::raw('AVG(TIMESTAMPDIFF(MINUTE, user_msg.created_at, admin_msg.created_at)) as avg_response_time')
            )
            ->join('chat_messages as admin_msg', function($join) {
                $join->on('user_msg.user_id', '=', 'admin_msg.user_id')
                    ->where('admin_msg.is_admin_message', true)
                    ->whereRaw('admin_msg.created_at > user_msg.created_at')
                    ->whereRaw('admin_msg.created_at = (
                        SELECT MIN(cm2.created_at)
                        FROM chat_messages cm2
                        WHERE cm2.user_id = user_msg.user_id
                        AND cm2.is_admin_message = true
                        AND cm2.created_at > user_msg.created_at
                    )');
            })
            ->where('user_msg.is_admin_message', false)
            ->first();

        return view('admin.chats.statistics', compact(
            'totalMessages',
            'unreadMessages',
            'adminMessages',
            'userMessages',
            'dailyStats',
            'activeUsers',
            'responseStats'
        ));
    }

    /**
     * Get unread messages count for navbar notification.
     */
    public function getUnreadCount(Request $request)
    {
        if (!Auth::guard('admin')->check()) {
            return response()->json(['count' => 0]);
        }

        $count = ChatMessage::where('is_admin_message', false)
            ->whereNull('read_at')
            ->count();

        return response()->json(['count' => $count]);
    }

    /**
     * Send notification to Telegram about new user message.
     * Этот метод будет вызываться из вебхука или через события
     */
    public function notifyTelegram(ChatMessage $message)
    {
        try {
            $user = $message->user;

            $telegramMessage = "📨 *Новое сообщение в чате поддержки*\n\n"
                . "👤 *Пользователь:* {$user->name}\n"
                . "📧 *Email:* {$user->email}\n"
                . "📞 *Телефон:* " . ($user->phone ?? 'не указан') . "\n"
                . "💬 *Сообщение:* " . substr($message->message, 0, 200) . "...\n\n"
                . "🕐 *Время:* " . $message->created_at->format('d.m.Y H:i') . "\n"
                . "🔗 *Перейти в чат:* " . route('admin.chats.show', $user);

            $this->telegramService->sendMessage($telegramMessage, 'markdown');

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            \Log::error('Telegram notification failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Export chat history with a user.
     */
    public function exportChat(User $user)
    {
        if (!Gate::allows('export-chats')) {
            abort(403);
        }

        $messages = ChatMessage::where('user_id', $user->id)
            ->with(['user', 'admin'])
            ->orderBy('created_at', 'asc')
            ->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="chat_' . $user->id . '_' . date('Y-m-d') . '.csv"',
        ];

        $callback = function() use ($messages, $user) {
            $file = fopen('php://output', 'w');

            // Заголовок
            fputcsv($file, ['Чат с пользователем: ' . $user->name . ' (' . $user->email . ')']);
            fputcsv($file, ['Экспорт от: ' . date('d.m.Y H:i:s')]);
            fputcsv($file, []); // Пустая строка

            // Заголовки таблицы
            fputcsv($file, ['Дата/Время', 'Отправитель', 'Сообщение', 'Прочитано']);

            foreach ($messages as $message) {
                $sender = $message->is_admin_message
                    ? ($message->admin ? 'Администратор' : 'Система')
                    : 'Пользователь';

                $readStatus = $message->is_admin_message
                    ? '-'
                    : ($message->read_at ? $message->read_at->format('d.m.Y H:i') : 'Нет');

                fputcsv($file, [
                    $message->created_at->format('d.m.Y H:i:s'),
                    $sender,
                    $message->message,
                    $readStatus
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Search in chat messages.
     */
    public function search(Request $request, User $user = null)
    {
        if (!Gate::allows('manage-chats')) {
            abort(403);
        }

        $query = ChatMessage::query()
            ->with(['user', 'admin'])
            ->orderBy('created_at', 'desc');

        if ($user) {
            $query->where('user_id', $user->id);
        }

        if ($request->filled('keyword')) {
            $keyword = $request->keyword;
            $query->where('message', 'like', "%{$keyword}%");
        }

        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->date_to . ' 23:59:59');
        }

        if ($request->filled('sender')) {
            $query->where('is_admin_message', $request->sender === 'admin');
        }

        $messages = $query->paginate(30);

        return view('admin.chats.search', compact('messages', 'user'));
    }
}
