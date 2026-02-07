<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'booking_id',
        'hotel_id',
        'room_id',
        'rating',
        'title',
        'comment',
        'advantages',
        'disadvantages',
        'is_verified',
        'status',
        'response',
        'response_date',
    ];

    protected $casts = [
        'rating' => 'integer',
        'is_verified' => 'boolean',
        'response_date' => 'datetime',
        'advantages' => 'array',
        'disadvantages' => 'array',
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';

    // Отношения
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function hotel()
    {
        return $this->belongsTo(Hotel::class);
    }

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    // Скоупы
    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }

    public function scopeForHotel($query, $hotelId)
    {
        return $query->where('hotel_id', $hotelId);
    }

    public function scopeForRoom($query, $roomId)
    {
        return $query->where('room_id', $roomId);
    }

    public function scopeWithRating($query, $minRating = 1)
    {
        return $query->where('rating', '>=', $minRating);
    }

    // Методы
    public function approve()
    {
        $this->update(['status' => self::STATUS_APPROVED]);
    }

    public function reject()
    {
        $this->update(['status' => self::STATUS_REJECTED]);
    }

    public function addResponse($response)
    {
        $this->update([
            'response' => $response,
            'response_date' => now(),
        ]);
    }

    public function isApproved()
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function getRatingStarsAttribute()
    {
        return str_repeat('★', $this->rating) . str_repeat('☆', 5 - $this->rating);
    }
}
