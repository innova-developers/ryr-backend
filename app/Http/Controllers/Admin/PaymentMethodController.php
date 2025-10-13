<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Routing\Controller;
use App\Shared\Enums\PaymentMethod;
use App\Shared\Models\Commission;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class PaymentMethodController extends Controller
{
    /**
     * Get all available payment methods
     */
    public function index(): JsonResponse
    {
        $paymentMethods = collect(PaymentMethod::cases())->map(function ($method) {
            return [
                'value' => $method->value,
                'label' => $method->label(),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $paymentMethods,
        ]);
    }

    /**
     * Associate payment method with a commission
     */
    public function associatePaymentMethod(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'commission_id' => 'required|exists:commissions,id',
            'payment_method' => 'required|in:' . implode(',', PaymentMethod::values()),
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $commission = Commission::find($request->commission_id);
        
        if (!$commission) {
            return response()->json([
                'success' => false,
                'message' => 'Commission not found',
            ], 404);
        }

        $commission->update([
            'payment_method' => $request->payment_method,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Payment method associated successfully',
            'data' => [
                'commission_id' => $commission->id,
                'payment_method' => $request->payment_method,
                'payment_method_label' => PaymentMethod::from($request->payment_method)->label(),
            ],
        ]);
    }

    /**
     * Get payment methods summary for commissions
     */
    public function getPaymentMethodsSummary(): JsonResponse
    {
        $summary = Commission::selectRaw('payment_method, COUNT(*) as count')
            ->whereNotNull('payment_method')
            ->groupBy('payment_method')
            ->get()
            ->map(function ($item) {
                return [
                    'payment_method' => $item->payment_method,
                    'payment_method_label' => $item->payment_method->label(),
                    'count' => $item->count,
                ];
            });

        $totalCommissions = Commission::whereNotNull('payment_method')->count();
        $commissionsWithoutMethod = Commission::whereNull('payment_method')->count();

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => $summary,
                'total_with_method' => $totalCommissions,
                'total_without_method' => $commissionsWithoutMethod,
                'total_commissions' => $totalCommissions + $commissionsWithoutMethod,
            ],
        ]);
    }
}
