<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Vendor\UserDiscounts\Models\Discount;
use Vendor\UserDiscounts\Models\UserDiscount;
use Vendor\UserDiscounts\Services\DiscountService;

class DiscountServiceTest extends TestCase
{
    use RefreshDatabase;

    private DiscountService $discountService;
    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->discountService = app(DiscountService::class);
        $this->userId = 1;
    }

    /** @test */
    public function it_enforces_usage_caps_correctly()
    {
        // Create a discount with usage limits
        $discount = Discount::create([
            'name' => 'Test Discount',
            'code' => 'TEST10',
            'type' => 'percentage',
            'value' => 10,
            'max_uses_per_user' => 2,
            'is_active' => true,
        ]);

        // Assign discount to user
        $userDiscount = $this->discountService->assign($this->userId, $discount->id);

        // First usage - should work
        $result1 = $this->discountService->apply($this->userId, 100);
        $this->assertEquals(10, $result1->discountAmount);
        $this->assertCount(1, $result1->appliedDiscounts);

        // Second usage - should work
        $result2 = $this->discountService->apply($this->userId, 100);
        $this->assertEquals(10, $result2->discountAmount);
        $this->assertCount(1, $result2->appliedDiscounts);

        // Third usage - should NOT apply (cap reached)
        $result3 = $this->discountService->apply($this->userId, 100);
        $this->assertEquals(0, $result3->discountAmount);
        $this->assertCount(0, $result3->appliedDiscounts);

        // Verify usage counts
        $userDiscount->refresh();
        $this->assertEquals(2, $userDiscount->times_used);
    }

    /** @test */
    public function it_applies_deterministic_discount_stacking()
    {
        // Create multiple discounts
        $percentageDiscount = Discount::create([
            'name' => '10% Off',
            'code' => 'PERC10',
            'type' => 'percentage',
            'value' => 10,
            'is_active' => true,
        ]);

        $fixedDiscount = Discount::create([
            'name' => '$5 Off',
            'code' => 'FIXED5',
            'type' => 'fixed',
            'value' => 5,
            'is_active' => true,
        ]);

        // Assign both discounts
        $this->discountService->assign($this->userId, $percentageDiscount->id);
        $this->discountService->assign($this->userId, $fixedDiscount->id);

        $result = $this->discountService->apply($this->userId, 100);

        // With stacking order [percentage, fixed]:
        // 10% of 100 = 10, then $5 off remaining 90 = 85 final
        $this->assertEquals(15, $result->discountAmount);
        $this->assertEquals(85, $result->finalAmount);
        $this->assertCount(2, $result->appliedDiscounts);
    }

    /** @test */
    public function it_respects_max_percentage_cap()
    {
        config(['user-discounts.max_percentage_cap' => 25]);

        // Create multiple percentage discounts that would exceed cap
        $discount1 = Discount::create([
            'name' => '15% Off',
            'code' => 'PERC15',
            'type' => 'percentage',
            'value' => 15,
            'is_active' => true,
        ]);

        $discount2 = Discount::create([
            'name' => '20% Off',
            'code' => 'PERC20',
            'type' => 'percentage',
            'value' => 20,
            'is_active' => true,
        ]);

        $this->discountService->assign($this->userId, $discount1->id);
        $this->discountService->assign($this->userId, $discount2->id);

        $result = $this->discountService->apply($this->userId, 100);

        // Should cap at 25% total discount
        $this->assertEquals(25, $result->discountAmount);
        $this->assertEquals(75, $result->finalAmount);
    }

    /** @test */
    public function it_ignores_expired_and_inactive_discounts()
    {
        $activeDiscount = Discount::create([
            'name' => 'Active Discount',
            'code' => 'ACTIVE',
            'type' => 'percentage',
            'value' => 10,
            'is_active' => true,
        ]);

        $inactiveDiscount = Discount::create([
            'name' => 'Inactive Discount',
            'code' => 'INACTIVE',
            'type' => 'percentage',
            'value' => 20,
            'is_active' => false,
        ]);

        $expiredDiscount = Discount::create([
            'name' => 'Expired Discount',
            'code' => 'EXPIRED',
            'type' => 'percentage',
            'value' => 30,
            'is_active' => true,
            'ends_at' => now()->subDay(),
        ]);

        $this->discountService->assign($this->userId, $activeDiscount->id);
        $this->discountService->assign($this->userId, $inactiveDiscount->id);
        $this->discountService->assign($this->userId, $expiredDiscount->id);

        $result = $this->discountService->apply($this->userId, 100);

        // Only active discount should be applied
        $this->assertEquals(10, $result->discountAmount);
        $this->assertCount(1, $result->appliedDiscounts);
    }
}
