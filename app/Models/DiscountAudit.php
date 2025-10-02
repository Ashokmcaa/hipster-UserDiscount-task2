<?php

namespace Vendor\UserDiscounts\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiscountAudit extends Model
{
    protected $fillable = [
        'user_id',
        'discount_id',
        'action',
        'original_amount',
        'discount_amount',
        'final_amount',
        'applied_discounts',
        'transaction_id',
        'metadata',
    ];

    protected $casts = [
        'original_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'final_amount' => 'decimal:2',
        'applied_discounts' => 'array',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'));
    }

    public function discount(): BelongsTo
    {
        return $this->belongsTo(Discount::class);
    }
}
