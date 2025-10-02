<?php

namespace Vendor\UserDiscounts\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Vendor\UserDiscounts\Models\DiscountAudit;

class DiscountApplied
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public DiscountAudit $audit
    ) {}
}
