<?php

namespace Vendor\UserDiscounts\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Vendor\UserDiscounts\Models\Discount;
use Vendor\UserDiscounts\Models\DiscountAudit;
use Vendor\UserDiscounts\Models\UserDiscount;
use Vendor\UserDiscounts\Services\DiscountService;
use Vendor\UserDiscounts\Services\DiscountApplicationResult;
use Illuminate\Support\Facades\Log;

class DiscountController extends Controller
{
    // public function __construct(
    //     private DiscountService $discountService
    // ) {}

    // Discount Management
    public function index()
    {

        $discounts = Discount::withCount(['userDiscounts' => function ($query) {
            $query->whereNull('revoked_at');
        }])->latest()->paginate(20);

        return view('user-discounts::discounts.index', compact('discounts'));
    }

    public function create()
    {
        return view('user-discounts::discounts.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|unique:discounts,code',
            'type' => 'required|in:percentage,fixed',
            'value' => 'required|numeric|min:0',
            'max_uses' => 'nullable|integer|min:1',
            'max_uses_per_user' => 'nullable|integer|min:1',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after:starts_at',
            'is_active' => 'boolean',
        ]);

        Discount::create($validated);

        return redirect()->route('discounts.index')
            ->with('success', 'Discount created successfully.');
    }

    public function edit(Discount $discount)
    {
        return view('user-discounts::discounts.edit', compact('discount'));
    }

    public function update(Request $request, Discount $discount)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|unique:discounts,code,' . $discount->id,
            'type' => 'required|in:percentage,fixed',
            'value' => 'required|numeric|min:0',
            'max_uses' => 'nullable|integer|min:1',
            'max_uses_per_user' => 'nullable|integer|min:1',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after:starts_at',
            'is_active' => 'boolean',
        ]);

        $discount->update($validated);

        return redirect()->route('discounts.index')
            ->with('success', 'Discount updated successfully.');
    }

    public function destroy(Discount $discount)
    {
        $discount->delete();

        return redirect()->route('discounts.index')
            ->with('success', 'Discount deleted successfully.');
    }

    public function toggle(Discount $discount)
    {
        $discount->update(['is_active' => !$discount->is_active]);

        return back()->with('success', 'Discount status updated.');
    }

    // User Discount Assignment
    public function assignToUser(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'discount_id' => 'required|exists:discounts,id',
        ]);

        try {
            $userDiscount = $this->discountService->assign(
                $request->user_id,
                $request->discount_id
            );

            return response()->json([
                'success' => true,
                'message' => 'Discount assigned successfully.',
                'user_discount' => $userDiscount
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to assign discount: ' . $e->getMessage()
            ], 422);
        }
    }

    public function revokeFromUser(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'discount_id' => 'required|exists:discounts,id',
        ]);

        try {
            $this->discountService->revoke($request->user_id, $request->discount_id);

            return response()->json([
                'success' => true,
                'message' => 'Discount revoked successfully.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to revoke discount: ' . $e->getMessage()
            ], 422);
        }
    }

    public function eligibleDiscounts(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $eligibleDiscounts = $this->discountService->eligibleFor($request->user_id);

        if ($request->expectsJson()) {
            return response()->json([
                'eligible_discounts' => $eligibleDiscounts->map(function ($userDiscount) {
                    return [
                        'id' => $userDiscount->discount->id,
                        'name' => $userDiscount->discount->name,
                        'type' => $userDiscount->discount->type,
                        'value' => $userDiscount->discount->value,
                        'times_used' => $userDiscount->times_used,
                        'max_uses_per_user' => $userDiscount->discount->max_uses_per_user,
                    ];
                })
            ]);
        }

        return view('user-discounts::user-discounts.eligible', compact('eligibleDiscounts'));
    }

    // Discount Application
    public function showApplyForm()
    {
        return view('user-discounts::apply.form');
    }

    public function apply(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0',
            'user_id' => 'required|exists:users,id',
            'transaction_id' => 'nullable|string',
        ]);

        try {
            $result = $this->discountService->apply(
                $request->user_id,
                $request->amount,
                $request->transaction_id
            );

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'original_amount' => $result->originalAmount,
                    'discount_amount' => $result->discountAmount,
                    'final_amount' => $result->finalAmount,
                    'applied_discounts' => $result->appliedDiscounts,
                    'savings_percentage' => $result->getSavingsPercentage(),
                ]);
            }

            return view('user-discounts::apply.result', [
                'originalAmount' => $result->originalAmount,
                'discountAmount' => $result->discountAmount,
                'finalAmount' => $result->finalAmount,
                'appliedDiscounts' => $result->appliedDiscounts,
                'audit' => $result->audit,
            ]);
        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to apply discounts: ' . $e->getMessage()
                ], 422);
            }

            return back()->withErrors(['error' => 'Failed to apply discounts: ' . $e->getMessage()]);
        }
    }

    public function preview(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0',
            'user_id' => 'required|exists:users,id',
        ]);

        try {
            // Create a preview without actually applying discounts
            $eligibleDiscounts = $this->discountService->eligibleFor($request->user_id);

            // Simulate application logic for preview
            $totalDiscount = 0;
            $appliedDiscounts = collect();
            $remainingAmount = $request->amount;

            foreach ($eligibleDiscounts as $userDiscount) {
                if ($userDiscount->canUse()) {
                    $discountAmount = $userDiscount->discount->calculateDiscount($remainingAmount);
                    $totalDiscount += $discountAmount;
                    $remainingAmount = max(0, $remainingAmount - $discountAmount);

                    $appliedDiscounts->push([
                        'name' => $userDiscount->discount->name,
                        'type' => $userDiscount->discount->type,
                        'value' => $userDiscount->discount->value,
                        'discount_amount' => $discountAmount,
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'original_amount' => $request->amount,
                'discount_amount' => $totalDiscount,
                'final_amount' => $request->amount - $totalDiscount,
                'applied_discounts' => $appliedDiscounts,
                'eligible_count' => $eligibleDiscounts->count(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to preview discounts: ' . $e->getMessage()
            ], 422);
        }
    }

    // Audit and Reporting
    public function auditLogs(Request $request)
    {
        $audits = DiscountAudit::with(['user', 'discount'])
            ->latest()
            ->paginate(20);

        return view('user-discounts::audits.index', compact('audits'));
    }

    public function usageHistory(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $history = DiscountAudit::with('discount')
            ->where('user_id', $request->user_id)
            ->latest()
            ->paginate(20);

        return view('user-discounts::user-discounts.history', compact('history'));
    }

    public function exportAuditLogs(Request $request)
    {
        // Implementation for exporting audit logs to CSV/Excel
        // This would use Laravel Excel or similar package
    }

    // Admin Functions
    public function analytics()
    {
        $stats = [
            'total_discounts' => Discount::count(),
            'active_discounts' => Discount::where('is_active', true)->count(),
            'total_redemptions' => UserDiscount::sum('times_used'),
            'total_savings' => DiscountAudit::sum('discount_amount'),
        ];

        $recentActivity = DiscountAudit::with(['user', 'discount'])
            ->latest()
            ->limit(10)
            ->get();

        return view('user-discounts::admin.analytics', compact('stats', 'recentActivity'));
    }

    public function bulkAssign(Request $request)
    {
        $request->validate([
            'discount_id' => 'required|exists:discounts,id',
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
        ]);

        DB::transaction(function () use ($request) {
            foreach ($request->user_ids as $userId) {
                try {
                    $this->discountService->assign($userId, $request->discount_id);
                } catch (\Exception $e) {
                    // Log error but continue with other assignments
                    // \Log::error("Failed to assign discount to user {$userId}: " . $e->getMessage());
                }
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Bulk assignment completed.'
        ]);
    }
}
