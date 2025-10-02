@extends('layouts.app')

@section('content')
    <div class="container">
        <h2>Apply Discounts</h2>

        <div class="card mb-4">
            <div class="card-body">
                <h5>Order Summary</h5>
                <p>Original Amount: ${{ number_format($originalAmount, 2) }}</p>
                <p class="text-success">Discount: -${{ number_format($discountAmount, 2) }}</p>
                <h4 class="text-primary">Final Amount: ${{ number_format($finalAmount, 2) }}</h4>
            </div>
        </div>

        @if ($appliedDiscounts->count() > 0)
            <div class="card">
                <div class="card-header">
                    <h5>Applied Discounts</h5>
                </div>
                <div class="card-body">
                    <ul class="list-group">
                        @foreach ($appliedDiscounts as $discount)
                            <li class="list-group-item">
                                {{ $discount['discount_name'] }}
                                ({{ $discount['discount_type'] === 'percentage' ? $discount['discount_value'] . '%' : '$' . $discount['discount_value'] }})
                                <span
                                    class="float-end text-success">-${{ number_format($discount['discount_amount'], 2) }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif
    </div>
@endsection
