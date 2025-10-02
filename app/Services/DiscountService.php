<?php

namespace Vendor\UserDiscounts\Services;

use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Vendor\UserDiscounts\Events\DiscountApplied;
use Vendor\UserDiscounts\Events\DiscountAssigned;
use Vendor\UserDiscounts\Events\DiscountRevoked;
use Vendor\UserDiscounts\Models\Discount;
use Vendor\UserDiscounts\Models\DiscountAudit;
use Vendor\UserDiscounts\Models\UserDiscount;

class DiscountService
{
    public function __construct(
        private DatabaseManager $database
    ) {}

    public function assign(int $userId, int $discountId): UserDiscount
    {
        return $this->database->transaction(function () use ($userId, $discountId) {
            $userDiscount = UserDiscount::create([
                'user_id' => $userId,
                'discount_id' => $discountId,
                'assigned_at' => now(),
            ]);

            event(new DiscountAssigned($userDiscount));

            return $userDiscount;
        });
    }

    public function revoke(int $userId, int $discountId): bool
    {
        return $this->database->transaction(function () use ($userId, $discountId) {
            $userDiscount = UserDiscount::where('user_id', $userId)
                ->where('discount_id', $discountId)
                ->whereNull('revoked_at')
                ->firstOrFail();

            $userDiscount->update(['revoked_at' => now()]);

            event(new DiscountRevoked($userDiscount));

            return true;
        });
    }

    public function eligibleFor(int $userId): Collection
    {
        return UserDiscount::with('discount')
            ->where('user_id', $userId)
            ->whereNull('revoked_at')
            ->get()
            ->filter(function (UserDiscount $userDiscount) {
                return $userDiscount->isActive() && $userDiscount->canUse();
            });
    }

    public function apply(int $userId, float $amount, string $transactionId = null): DiscountApplicationResult
    {
        return $this->database->transaction(function () use ($userId, $amount, $transactionId) {
            $eligibleDiscounts = $this->eligibleFor($userId);
            $appliedDiscounts = $this->applyDiscountStacking($eligibleDiscounts, $amount);

            $totalDiscount = $appliedDiscounts->sum('discount_amount');
            $finalAmount = max(0, $amount - $totalDiscount);

            // Increment usage counts
            $appliedDiscounts->each(function ($discount) {
                UserDiscount::where('id', $discount['user_discount_id'])->increment('times_used');
            });

            // Create audit trail
            $audit = DiscountAudit::create([
                'user_id' => $userId,
                'discount_id' => null, // Multiple discounts applied
                'action' => 'applied',
                'original_amount' => $amount,
                'discount_amount' => $totalDiscount,
                'final_amount' => $finalAmount,
                'applied_discounts' => $appliedDiscounts->toArray(),
                'transaction_id' => $transactionId,
            ]);

            event(new DiscountApplied($audit));

            return new DiscountApplicationResult(
                originalAmount: $amount,
                discountAmount: $totalDiscount,
                finalAmount: $finalAmount,
                appliedDiscounts: $appliedDiscounts,
                audit: $audit
            );
        }, 5); // Retry up to 5 times for concurrency
    }

    private function applyDiscountStacking(Collection $eligibleDiscounts, float $amount): Collection
    {
        $stackingOrder = config('user-discounts.stacking_order', ['percentage', 'fixed']);
        $maxPercentage = config('user-discounts.max_percentage_cap');
        $rounding = config('user-discounts.rounding', 2);

        $remainingAmount = $amount;
        $appliedDiscounts = collect();
        $totalPercentageDiscount = 0;

        // Apply discounts in specified order
        foreach ($stackingOrder as $type) {
            $typeDiscounts = $eligibleDiscounts->filter(fn($ud) => $ud->discount->type === $type);

            foreach ($typeDiscounts as $userDiscount) {
                $discount = $userDiscount->discount;
                $discountAmount = $discount->calculateDiscount($remainingAmount);

                if ($type === 'percentage') {
                    $totalPercentageDiscount += $discountAmount;
                    if ($maxPercentage && $totalPercentageDiscount > ($amount * $maxPercentage / 100)) {
                        $discountAmount = max(0, ($amount * $maxPercentage / 100) - ($totalPercentageDiscount - $discountAmount));
                    }
                }

                $discountAmount = round($discountAmount, $rounding);

                if ($discountAmount > 0) {
                    $appliedDiscounts->push([
                        'user_discount_id' => $userDiscount->id,
                        'discount_id' => $discount->id,
                        'discount_name' => $discount->name,
                        'discount_type' => $discount->type,
                        'discount_value' => $discount->value,
                        'discount_amount' => $discountAmount,
                    ]);

                    $remainingAmount = max(0, $remainingAmount - $discountAmount);
                }
            }
        }

        return $appliedDiscounts;
    }
}
