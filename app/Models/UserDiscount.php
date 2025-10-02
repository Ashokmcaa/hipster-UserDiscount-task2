<?php

namespace Vendor\UserDiscounts\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserDiscount extends Model
{
    protected $fillable = [
        'user_id',
        'discount_id',
        'times_used',
        'assigned_at',
        'revoked_at',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'));
    }

    public function discount(): BelongsTo
    {
        return $this->belongsTo(Discount::class);
    }

    public function isActive(): bool
    {
        return !$this->revoked_at && $this->discount->isActive();
    }

    public function canUse(): bool
    {
        if (!$this->isActive()) {
            return false;
        }

        if (!$this->discount->max_uses_per_user) {
            return true;
        }

        return $this->times_used < $this->discount->max_uses_per_user;
    }

    public function incrementUsage(): void
    {
        $this->increment('times_used');
    }
}
