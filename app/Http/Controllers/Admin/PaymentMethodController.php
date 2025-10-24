<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Routing\Controller;
use App\Shared\Enums\PaymentMethod;
use App\Shared\Models\Commission;
use App\Services\IvaCalculationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class PaymentMethodController extends Controller
{
    public function __construct(
        private IvaCalculationService $ivaCalculationService
    ) {
    }
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

        $newPaymentMethod = PaymentMethod::from($request->payment_method);
        $customer = $commission->client;
        
        // Actualizar método de pago y recalcular IVA
        $commission = $this->ivaCalculationService->updateIvaForPaymentMethodChange($commission, $newPaymentMethod);
        
        // Agregar nota sobre IVA si se aplicó
        if ($commission->iva_applied && $commission->iva_amount > 0) {
            $ivaNote = $this->ivaCalculationService->generateIvaNote($commission->iva_amount, true);
            $commission->notes = $commission->notes ? $commission->notes . "\n" . $ivaNote : $ivaNote;
            $commission->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'Payment method associated successfully',
            'data' => [
                'commission_id' => $commission->id,
                'payment_method' => $request->payment_method,
                'payment_method_label' => $newPaymentMethod->label(),
                'iva_applied' => $commission->iva_applied,
                'iva_amount' => $commission->iva_amount,
                'total_with_iva' => $commission->total,
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
