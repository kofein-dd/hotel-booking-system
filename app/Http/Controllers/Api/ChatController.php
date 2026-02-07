<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\User;
use App\Http\Requests\ChatMessage\StoreChatMessageRequest;
use App\Http\Resources\ChatMessageResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Notification;
use App\Notifications\NewChatMessage;

class ChatController extends Controller
{
    /**
     * Display a listing of chat conversations for user.
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();

            // Для пользователей - показываем чат с поддержкой
            if ($user->role === 'user') {
                $messages = ChatMessage::where('user_id', $user->id)
                    ->orWhere('admin_id', $user->id)
                    ->with(['user', 'admin'])
                    ->orderBy('created_at', 'desc')
                    ->get()
                    ->groupBy(function ($message) use ($user) {
                        // Группируем по собеседнику
                        if ($message->user_id === $user->id) {
                            return 'admin';
                        } else {
                            return 'user_' . $message->user_id;
                        }
                    });

                // Получаем последние сообщения из каждой беседы
                $conversations = [];
                foreach ($messages as $key => $group) {
                    $lastMessage = $group->first();
                    $unreadCount = $group->where('is_read', false)
                        ->where('user_id', '!=', $user->id)
                        ->count();

                    $conversations[] = [
                        'conversation_id' => $key,
                        'last_message' => new ChatMessageResource($lastMessage),
                        'unread_count' => $unreadCount,
                        'total_messages' => $group->count(),
                        'last_message_time' => $lastMessage->created_at,
                    ];
                }

                return response()->json([
                    'success' => true,
                    'data' => $conversations,
                    'user_info' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'role' => $user->role
                    ]
                ]);

                // Для администраторов - показываем список диалогов с пользователями
            } elseif ($user->role === 'admin') {
                // Получаем список пользователей, с которыми есть переписка
                $userIds = ChatMessage::select('user_id')
                    ->distinct()
                    ->whereNotNull('user_id')
                    ->pluck('user_id');

                $conversations = [];
                foreach ($userIds as $userId) {
                    // Получаем последнее сообщение в диалоге
                    $lastMessage = ChatMessage::where('user_id', $userId)
                        ->orWhere(function ($query) use ($userId) {
                            $query->where('admin_id', '!=', null)
                                ->where('user_id', $userId);
                        })
                        ->with(['user', 'admin'])
                        ->orderBy('created_at', 'desc')
                        ->first();

                    if ($lastMessage) {
                        // Считаем непрочитанные сообщения от пользователя
                        $unreadCount = ChatMessage::where('user_id', $userId)
                            ->where('is_read', false)
                            ->whereNull('admin_id') // Сообщения от пользователя
                            ->count();

                        $conversations[] = [
                            'user_id' => $userId,
                            'user_name' => $lastMessage->user->name,
                            'user_email' => $lastMessage->user->email,
                            'last_message' => new ChatMessageResource($lastMessage),
                            'unread_count' => $unreadCount,
                            'last_message_time' => $lastMessage->created_at,
                        ];
                    }
                }

                // Сортируем по времени последнего сообщения
                usort($conversations, function ($a, $b) {
                    return strtotime($b['last_message_time']) - strtotime($a['last_message_time']);
                });

                return response()->json([
                    'success' => true,
                    'data' => $conversations,
                    'meta' => [
                        'total_conversations' => count($conversations)
                    ]
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Доступ запрещен'
            ], 403);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении списка чатов',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get messages for specific conversation.
     */
    public function getConversation(Request $request, $userId = null)
    {
        try {
            $currentUser = $request->user();

            // Определяем ID собеседника
            $conversationUserId = $userId;

            if ($currentUser->role === 'user') {
                // Пользователь всегда общается с поддержкой
                $conversationUserId = null;

                $messages = ChatMessage::where(function ($query) use ($currentUser) {
                    $query->where('user_id', $currentUser->id)
                        ->orWhere('admin_id', $currentUser->id);
                })
                    ->with(['user', 'admin'])
                    ->orderBy('created_at', 'asc')
                    ->paginate(50);

            } elseif ($currentUser->role === 'admin' && $conversationUserId) {
                // Админ общается с конкретным пользователем
                $messages = ChatMessage::where('user_id', $conversationUserId)
                    ->orWhere(function ($query) use ($conversationUserId, $currentUser) {
                        $query->where('user_id', $conversationUserId)
                            ->where('admin_id', $currentUser->id);
                    })
                    ->with(['user', 'admin'])
                    ->orderBy('created_at', 'asc')
                    ->paginate(50);

                // Помечаем сообщения как прочитанные
                ChatMessage::where('user_id', $conversationUserId)
                    ->where('is_read', false)
                    ->whereNull('admin_id') // Только сообщения от пользователя
                    ->update(['is_read' => true, 'read_at' => now()]);

            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Не указан пользователь для диалога'
                ], 400);
            }

            return response()->json([
                'success' => true,
                'data' => ChatMessageResource::collection($messages),
                'meta' => [
                    'total' => $messages->total(),
                    'per_page' => $messages->perPage(),
                    'current_page' => $messages->currentPage(),
                    'last_page' => $messages->lastPage(),
                ],
                'conversation_info' => $conversationUserId ? [
                    'user_id' => $conversationUserId,
                    'user' => User::find($conversationUserId)->only(['id', 'name', 'email'])
                ] : null
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении сообщений',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Store a newly created chat message.
     */
    public function send(StoreChatMessageRequest $request)
    {
        try {
            DB::beginTransaction();

            $user = $request->user();
            $data = $request->validated();

            // Определяем отправителя и получателя
            $messageData = [
                'message' => $data['message'],
                'is_read' => false,
            ];

            if ($user->role === 'user') {
                // Пользователь пишет в поддержку
                $messageData['user_id'] = $user->id;
                $messageData['is_admin_message'] = false;

                // Находим активного администратора или оставляем null
                $admin = User::where('role', 'admin')
                    ->where('is_online', true)
                    ->inRandomOrder()
                    ->first();

                if ($admin) {
                    $messageData['admin_id'] = $admin->id;
                }

            } elseif ($user->role === 'admin') {
                // Администратор отвечает пользователю
                if (!isset($data['user_id'])) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Не указан получатель сообщения'
                    ], 400);
                }

                $recipient = User::findOrFail($data['user_id']);

                $messageData['user_id'] = $recipient->id;
                $messageData['admin_id'] = $user->id;
                $messageData['is_admin_message'] = true;
                $messageData['is_read'] = true; // Сообщение от админа считается прочитанным
                $messageData['read_at'] = now();

            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Доступ запрещен'
                ], 403);
            }

            // Добавляем метаданные
            $messageData['ip_address'] = $request->ip();
            $messageData['user_agent'] = $request->userAgent();

            // Создаем сообщение
            $message = ChatMessage::create($messageData);

            // Отправляем уведомления
            $this->sendNotifications($message, $user);

            DB::commit();

            // Отправляем сообщение через WebSocket (если настроено)
            $this->broadcastMessage($message);

            return response()->json([
                'success' => true,
                'message' => 'Сообщение отправлено',
                'data' => new ChatMessageResource($message->load(['user', 'admin']))
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при отправке сообщения',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Mark message as read.
     */
    public function markAsRead(Request $request, $messageId)
    {
        try {
            $user = $request->user();
            $message = ChatMessage::findOrFail($messageId);

            // Проверяем права доступа
            if ($user->role === 'user' && $message->user_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Доступ запрещен'
                ], 403);
            }

            if ($user->role === 'admin' && $message->admin_id !== $user->id && $message->user_id) {
                // Админ может отмечать как прочитанные сообщения от пользователей
                if ($message->user_id && !$message->is_admin_message) {
                    $message->update([
                        'is_read' => true,
                        'read_at' => now(),
                        'read_by' => $user->id
                    ]);
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'Доступ запрещен'
                    ], 403);
                }
            }

            $message->update([
                'is_read' => true,
                'read_at' => now(),
                'read_by' => $user->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Сообщение отмечено как прочитанное',
                'data' => new ChatMessageResource($message)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при обновлении сообщения',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Mark all messages as read in conversation.
     */
    public function markAllAsRead(Request $request, $userId = null)
    {
        try {
            $user = $request->user();

            if ($user->role === 'user') {
                // Пользователь отмечает все сообщения от поддержки как прочитанные
                $updated = ChatMessage::where('user_id', $user->id)
                    ->where('is_admin_message', true)
                    ->where('is_read', false)
                    ->update([
                        'is_read' => true,
                        'read_at' => now(),
                        'read_by' => $user->id
                    ]);

            } elseif ($user->role === 'admin' && $userId) {
                // Админ отмечает все сообщения от пользователя как прочитанные
                $updated = ChatMessage::where('user_id', $userId)
                    ->where('is_admin_message', false)
                    ->where('is_read', false)
                    ->update([
                        'is_read' => true,
                        'read_at' => now(),
                        'read_by' => $user->id
                    ]);

            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Не указан пользователь для диалога'
                ], 400);
            }

            return response()->json([
                'success' => true,
                'message' => "$updated сообщений отмечено как прочитанные",
                'updated_count' => $updated
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при обновлении сообщений',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Delete a chat message.
     */
    public function delete(Request $request, $messageId)
    {
        try {
            $user = $request->user();
            $message = ChatMessage::findOrFail($messageId);

            // Проверяем права доступа
            $canDelete = false;

            if ($user->role === 'admin') {
                $canDelete = true; // Админ может удалять любые сообщения
            } elseif ($user->role === 'user') {
                // Пользователь может удалять только свои сообщения
                if ($message->user_id === $user->id && !$message->is_admin_message) {
                    $canDelete = true;
                }
            }

            if (!$canDelete) {
                return response()->json([
                    'success' => false,
                    'message' => 'Доступ запрещен'
                ], 403);
            }

            // Вместо полного удаления помечаем как удаленное
            $message->update([
                'deleted_at' => now(),
                'deleted_by' => $user->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Сообщение удалено'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при удалении сообщения',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get unread messages count.
     */
    public function unreadCount(Request $request)
    {
        try {
            $user = $request->user();

            if ($user->role === 'user') {
                $count = ChatMessage::where('user_id', $user->id)
                    ->where('is_admin_message', true)
                    ->where('is_read', false)
                    ->count();

            } elseif ($user->role === 'admin') {
                $count = ChatMessage::where('is_admin_message', false)
                    ->where('is_read', false)
                    ->count();

            } else {
                $count = 0;
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'unread_count' => $count
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении количества непрочитанных сообщений'
            ], 500);
        }
    }

    /**
     * Search in chat messages.
     */
    public function search(Request $request)
    {
        try {
            $user = $request->user();
            $query = $request->get('query', '');
            $limit = $request->get('limit', 20);

            if (strlen($query) < 2) {
                return response()->json([
                    'success' => false,
                    'message' => 'Минимум 2 символа для поиска'
                ], 400);
            }

            $searchQuery = ChatMessage::query();

            // Ограничиваем поиск доступными сообщениями
            if ($user->role === 'user') {
                $searchQuery->where('user_id', $user->id);
            } elseif ($user->role === 'admin') {
                // Админ может искать по всем сообщениям
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Доступ запрещен'
                ], 403);
            }

            $messages = $searchQuery->where('message', 'LIKE', "%$query%")
                ->with(['user', 'admin'])
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get();

            return response()->json([
                'success' => true,
                'data' => ChatMessageResource::collection($messages),
                'meta' => [
                    'query' => $query,
                    'found_count' => $messages->count()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при поиске сообщений',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get chat statistics.
     */
    public function statistics(Request $request)
    {
        try {
            $user = $request->user();

            if ($user->role !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Доступ запрещен'
                ], 403);
            }

            $period = $request->get('period', '30days');

            switch ($period) {
                case '7days':
                    $startDate = Carbon::now()->subDays(7);
                    break;
                case '30days':
                    $startDate = Carbon::now()->subDays(30);
                    break;
                case '90days':
                    $startDate = Carbon::now()->subDays(90);
                    break;
                default:
                    $startDate = Carbon::now()->subDays(30);
            }

            // Сообщения по дням
            $messagesByDay = ChatMessage::select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as count')
            )
                ->where('created_at', '>=', $startDate)
                ->groupBy('date')
                ->orderBy('date')
                ->get()
                ->mapWithKeys(function ($item) {
                    return [$item->date => $item->count];
                });

            // Сообщения по типам (пользователь/админ)
            $messagesByType = ChatMessage::select(
                DB::raw('CASE WHEN is_admin_message THEN "admin" ELSE "user" END as message_type'),
                DB::raw('COUNT(*) as count')
            )
                ->where('created_at', '>=', $startDate)
                ->groupBy('message_type')
                ->get();

            // Самые активные пользователи в чате
            $activeUsers = ChatMessage::select(
                'user_id',
                DB::raw('COUNT(*) as message_count')
            )
                ->with('user')
                ->where('created_at', '>=', $startDate)
                ->whereNotNull('user_id')
                ->groupBy('user_id')
                ->orderBy('message_count', 'desc')
                ->limit(10)
                ->get();

            // Среднее время ответа
            $avgResponseTime = $this->calculateAverageResponseTime($startDate);

            // Статистика по времени суток
            $messagesByHour = ChatMessage::select(
                DB::raw('HOUR(created_at) as hour'),
                DB::raw('COUNT(*) as count')
            )
                ->where('created_at', '>=', $startDate)
                ->groupBy('hour')
                ->orderBy('hour')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'messages_by_day' => $messagesByDay,
                    'messages_by_type' => $messagesByType,
                    'active_users' => $activeUsers,
                    'average_response_time' => $avgResponseTime,
                    'messages_by_hour' => $messagesByHour,
                    'period' => $period,
                    'total_messages_period' => ChatMessage::where('created_at', '>=', $startDate)->count()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении статистики'
            ], 500);
        }
    }

    /**
     * Upload file in chat.
     */
    public function uploadFile(Request $request)
    {
        try {
            $user = $request->user();

            $request->validate([
                'file' => 'required|file|max:10240', // 10MB max
                'conversation_id' => 'nullable|string'
            ]);

            $file = $request->file('file');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $filePath = $file->storeAs('chat_files', $fileName, 'public');

            // Определяем тип файла
            $fileType = $this->getFileType($file->getMimeType());
            $fileSize = $file->getSize();

            // Создаем сообщение с файлом
            $messageData = [
                'user_id' => $user->id,
                'message' => "Файл: " . $file->getClientOriginalName(),
                'file_path' => $filePath,
                'file_name' => $file->getClientOriginalName(),
                'file_type' => $fileType,
                'file_size' => $fileSize,
                'is_admin_message' => $user->role !== 'admin',
            ];

            if ($user->role === 'admin' && $request->has('conversation_id')) {
                // Админ отправляет файл конкретному пользователю
                $messageData['admin_id'] = $user->id;
                $messageData['is_admin_message'] = true;
            }

            $message = ChatMessage::create($messageData);

            return response()->json([
                'success' => true,
                'message' => 'Файл загружен',
                'data' => [
                    'file_url' => asset('storage/' . $filePath),
                    'file_name' => $file->getClientOriginalName(),
                    'file_type' => $fileType,
                    'file_size' => $fileSize,
                    'message' => new ChatMessageResource($message)
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при загрузке файла',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Helper method to send notifications.
     */
    private function sendNotifications($message, $sender)
    {
        try {
            if ($sender->role === 'user') {
                // Уведомляем администраторов о новом сообщении от пользователя
                $admins = User::where('role', 'admin')
                    ->where('is_online', false) // Только оффлайн админам
                    ->get();

                foreach ($admins as $admin) {
                    Notification::send($admin, new NewChatMessage($message, $sender));
                }

                // Отправляем в Telegram (если настроено)
                $this->sendTelegramNotification($message, $sender);

            } elseif ($sender->role === 'admin') {
                // Уведомляем пользователя о ответе поддержки
                $recipient = User::find($message->user_id);
                if ($recipient) {
                    Notification::send($recipient, new NewChatMessage($message, $sender));
                }
            }

        } catch (\Exception $e) {
            // Логируем ошибку, но не прерываем отправку сообщения
            \Log::error('Ошибка при отправке уведомления: ' . $e->getMessage());
        }
    }

    /**
     * Helper method to broadcast message via WebSocket.
     */
    private function broadcastMessage($message)
    {
        try {
            // Реализация WebSocket бродкаста (зависит от вашей реализации)
            // Пример для Laravel Echo + Pusher
            /*
            broadcast(new \App\Events\NewChatMessage($message))->toOthers();
            */

            // Или для Socket.io
            // event(new \App\Events\ChatMessageSent($message));

        } catch (\Exception $e) {
            \Log::error('Ошибка при бродкасте сообщения: ' . $e->getMessage());
        }
    }

    /**
     * Calculate average response time.
     */
    private function calculateAverageResponseTime($startDate)
    {
        try {
            $messages = ChatMessage::where('created_at', '>=', $startDate)
                ->whereNotNull('admin_id')
                ->orderBy('created_at', 'asc')
                ->get();

            $totalResponseTime = 0;
            $responseCount = 0;

            $userLastMessageTime = [];

            foreach ($messages as $message) {
                if (!$message->is_admin_message) {
                    // Сообщение от пользователя
                    $userLastMessageTime[$message->user_id] = $message->created_at;
                } else {
                    // Ответ админа
                    if (isset($userLastMessageTime[$message->user_id])) {
                        $responseTime = $message->created_at->diffInMinutes($userLastMessageTime[$message->user_id]);
                        $totalResponseTime += $responseTime;
                        $responseCount++;
                        unset($userLastMessageTime[$message->user_id]);
                    }
                }
            }

            return $responseCount > 0 ? round($totalResponseTime / $responseCount, 2) : 0;

        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Get file type from mime type.
     */
    private function getFileType($mimeType)
    {
        $imageTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $documentTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        $spreadsheetTypes = ['application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];

        if (in_array($mimeType, $imageTypes)) {
            return 'image';
        } elseif (in_array($mimeType, $documentTypes)) {
            return 'document';
        } elseif (in_array($mimeType, $spreadsheetTypes)) {
            return 'spreadsheet';
        } else {
            return 'other';
        }
    }

    /**
     * Send Telegram notification.
     */
    private function sendTelegramNotification($message, $sender)
    {
        try {
            $telegramBotToken = config('services.telegram.bot_token');
            $chatId = config('services.telegram.chat_id');

            if (!$telegramBotToken || !$chatId) {
                return;
            }

            $text = "📨 Новое сообщение в чате поддержки\n";
            $text .= "От: {$sender->name} ({$sender->email})\n";
            $text .= "Сообщение: " . substr($message->message, 0, 200) . "\n";
            $text .= "Время: " . now()->format('d.m.Y H:i');

            $url = "https://api.telegram.org/bot{$telegramBotToken}/sendMessage";

            $client = new \GuzzleHttp\Client();
            $client->post($url, [
                'form_params' => [
                    'chat_id' => $chatId,
                    'text' => $text,
                    'parse_mode' => 'HTML'
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Ошибка при отправке в Telegram: ' . $e->getMessage());
        }
    }
}
