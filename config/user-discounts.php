<?php

return [
    'stacking_order' => ['percentage', 'fixed'],
    'max_percentage_cap' => 80, // Maximum total percentage discount allowed
    'rounding' => 2,

    'models' => [
        'user' => App\Models\User::class,
        'discount' => Vendor\UserDiscounts\Models\Discount::class,
        'user_discount' => Vendor\UserDiscounts\Models\UserDiscount::class,
        'discount_audit' => Vendor\UserDiscounts\Models\DiscountAudit::class,
    ],

    'events' => [
        'enabled' => true,
    ],
];
