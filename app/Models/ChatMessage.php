<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChatMessage extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'conversation_id',
        'user_id',
        'admin_id',
        'booking_id',
        'message',
        'attachments',
        'message_type',
        'is_admin_message',
        'read_at',
        'delivered_at',
        'deleted_by',
        'metadata',
    ];

    protected $casts = [
        'attachments' => 'array',
        'metadata' => 'array',
        'read_at' => 'datetime',
        'delivered_at' => 'datetime',
        'is_admin_message' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Генерация ID диалога при создании
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($message) {
            if (!$message->conversation_id) {
                $message->conversation_id = \Illuminate\Support\Str::uuid()->toString();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    // Проверка, прочитано ли сообщение
    public function isRead(): bool
    {
        return !is_null($this->read_at);
    }

    // Проверка, доставлено ли сообщение
    public function isDelivered(): bool
    {
        return !is_null($this->delivered_at);
    }

    // Отметить как прочитанное
    public function markAsRead(): bool
    {
        if (!$this->read_at) {
            $this->read_at = now();
            return $this->save();
        }

        return false;
    }

    // Отметить как доставленное
    public function markAsDelivered(): bool
    {
        if (!$this->delivered_at) {
            $this->delivered_at = now();
            return $this->save();
        }

        return false;
    }

    // Получить имя отправителя
    public function getSenderName(): string
    {
        if ($this->is_admin_message && $this->admin) {
            return $this->admin->name . ' (Администратор)';
        }

        return $this->user->name;
    }

    // Получить аватар отправителя
    public function getSenderAvatar(): ?string
    {
        if ($this->is_admin_message && $this->admin) {
            return $this->admin->avatar;
        }

        return $this->user->avatar;
    }

    // Проверка, есть ли вложения
    public function hasAttachments(): bool
    {
        return !empty($this->attachments);
    }

    // Получить список вложений
    public function getAttachmentsList(): array
    {
        if (!$this->attachments) {
            return [];
        }

        $attachments = [];
        foreach ($this->attachments as $attachment) {
            $attachments[] = [
                'url' => $attachment['url'] ?? $attachment,
                'name' => $attachment['name'] ?? basename($attachment['url'] ?? $attachment),
                'type' => $attachment['type'] ?? $this->getAttachmentType($attachment['url'] ?? $attachment),
                'size' => $attachment['size'] ?? null,
            ];
        }

        return $attachments;
    }

    // Определить тип вложения по расширению
    private function getAttachmentType(string $url): string
    {
        $extension = strtolower(pathinfo($url, PATHINFO_EXTENSION));

        $imageTypes = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
        $documentTypes = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt'];

        if (in_array($extension, $imageTypes)) {
            return 'image';
        } elseif (in_array($extension, $documentTypes)) {
            return 'document';
        }

        return 'file';
    }

    // Проверка, является ли сообщение системным
    public function isSystemMessage(): bool
    {
        return $this->message_type === 'system';
    }

    // Проверка, можно ли удалить сообщение (в течение 5 минут после отправки)
    public function canBeDeleted(): bool
    {
        return $this->created_at->diffInMinutes(now()) <= 5 && !$this->isSystemMessage();
    }

    // Удалить сообщение (мягкое удаление)
    public function deleteMessage(User $deletedBy): bool
    {
        if ($this->canBeDeleted() || $deletedBy->isAdmin()) {
            $this->deleted_at = now();
            $this->deleted_by = $deletedBy->id;
            return $this->save();
        }

        return false;
    }

    // Создать системное сообщение
    public static function createSystemMessage(string $conversationId, int $userId, string $message): self
    {
        return self::create([
            'conversation_id' => $conversationId,
            'user_id' => $userId,
            'message' => $message,
            'message_type' => 'system',
            'is_admin_message' => false,
            'delivered_at' => now(),
            'read_at' => now(),
        ]);
    }

    // Получить последние сообщения в диалоге
    public static function getConversationMessages(string $conversationId, int $limit = 50)
    {
        return self::where('conversation_id', $conversationId)
            ->with(['user', 'admin'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->reverse();
    }

    // Получить или создать диалог для пользователя
    public static function getOrCreateConversation(int $userId, ?int $bookingId = null): string
    {
        $existingMessage = self::where('user_id', $userId)
            ->when($bookingId, function ($query) use ($bookingId) {
                return $query->where('booking_id', $bookingId);
            })
            ->first();

        if ($existingMessage) {
            return $existingMessage->conversation_id;
        }

        // Создаем первое системное сообщение
        $message = self::create([
            'user_id' => $userId,
            'booking_id' => $bookingId,
            'message' => 'Диалог начат. Задайте ваш вопрос.',
            'message_type' => 'system',
            'is_admin_message' => false,
            'delivered_at' => now(),
            'read_at' => now(),
        ]);

        return $message->conversation_id;
    }
}
