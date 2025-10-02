<?php

use Illuminate\Support\Facades\Route;
use Vendor\UserDiscounts\Controllers\DiscountController;

// Prefix all routes with 'user-discounts' to avoid conflicts
Route::prefix('user-discounts')->group(function () {

    // Show eligible discounts for a user
    Route::get('{user}', [DiscountController::class, 'index'])
        ->name('userdiscounts.index');

    // Apply discounts to a given amount for a user
    Route::post('{user}/apply', [DiscountController::class, 'apply'])
        ->name('userdiscounts.apply');
});
