<?php

namespace Vendor\UserDiscounts\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Discount extends Model
{
    protected $fillable = [
        'name',
        'code',
        'type',
        'value',
        'max_uses',
        'max_uses_per_user',
        'starts_at',
        'ends_at',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    public function userDiscounts(): HasMany
    {
        return $this->hasMany(UserDiscount::class);
    }

    public function isActive(): bool
    {
        return $this->is_active &&
            (!$this->starts_at || Carbon::now()->gte($this->starts_at)) &&
            (!$this->ends_at || Carbon::now()->lte($this->ends_at));
    }

    public function hasReachedMaxUses(): bool
    {
        if (!$this->max_uses) {
            return false;
        }

        return $this->userDiscounts()->sum('times_used') >= $this->max_uses;
    }

    public function calculateDiscount(float $amount): float
    {
        return match ($this->type) {
            'percentage' => $amount * ($this->value / 100),
            'fixed' => min($this->value, $amount),
            default => 0,
        };
    }
}
