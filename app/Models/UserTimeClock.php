<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserTimeClock extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'user_time_clock';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'shop_id',
        'user_id',
        'date_at',
        'time_at',
        'date_time',
        'formated_date_time',
        'shift_start',
        'shift_end',
        'type',
        'comment',
        'buffer_time',
        'created_from',
        'updated_from',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'date_at' => 'date',
        'time_at' => 'datetime:H:i:s',
        'date_time' => 'datetime',
        'formated_date_time' => 'datetime',
        'shift_start' => 'datetime:H:i:s',
        'shift_end' => 'datetime:H:i:s',
        'buffer_time' => 'integer',
        'shop_id' => 'integer',
    ];

    /**
     * Scope a query to only include events for a specific date.
     */
    public function scopeForDate($query, string $date)
    {
        return $query->where('date_at', $date);
    }

    /**
     * Scope a query to only include events for a specific shop.
     */
    public function scopeForShop($query, int $shopId)
    {
        return $query->where('shop_id', $shopId);
    }

    /**
     * Scope a query to only include events for a specific user.
     */
    public function scopeForUser($query, ?int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope a query to only include events of a specific type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Get the user that owns the time clock entry.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
