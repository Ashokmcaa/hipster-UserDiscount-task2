<?php

namespace Vendor\UserDiscounts\Services;

use Illuminate\Support\Collection;
use Vendor\UserDiscounts\Models\DiscountAudit;

class DiscountApplicationResult
{
    public function __construct(
        public float $originalAmount,
        public float $discountAmount,
        public float $finalAmount,
        public Collection $appliedDiscounts,
        public DiscountAudit $audit
    ) {}

    public function getSavingsPercentage(): float
    {
        if ($this->originalAmount <= 0) {
            return 0;
        }

        return ($this->discountAmount / $this->originalAmount) * 100;
    }
}
