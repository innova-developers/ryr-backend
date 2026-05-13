<?php

namespace App\Http\Controllers\Admin;

use App\Shared\Enums\PaymentMethod;
use App\Shared\Models\SystemSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SystemSettingsController
{
    public function getIvaConfig(): JsonResponse
    {
        $enabledMethods = SystemSetting::get('iva_payment_methods', ['TRANSFERENCIA']);

        $methods = collect(PaymentMethod::cases())->map(fn (PaymentMethod $pm) => [
            'value' => $pm->value,
            'label' => $pm->label(),
            'enabled' => in_array($pm->value, $enabledMethods),
        ]);

        return response()->json([
            'payment_methods' => $methods,
        ]);
    }

    public function updateIvaConfig(Request $request): JsonResponse
    {
        $request->validate([
            'payment_methods' => 'required|array',
            'payment_methods.*' => 'string|in:' . implode(',', PaymentMethod::values()),
        ]);

        SystemSetting::set('iva_payment_methods', $request->payment_methods);

        return response()->json([
            'message' => 'Configuración IVA actualizada',
            'payment_methods' => $request->payment_methods,
        ]);
    }
}
