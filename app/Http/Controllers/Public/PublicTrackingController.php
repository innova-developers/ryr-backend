<?php

namespace App\Http\Controllers\Public;

use App\Services\TrackingOtpService;
use App\Shared\Models\Commission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * RC-500 — Seguimiento público.
 *
 * Antes este endpoint devolvía, sin autenticación y con id secuencial: direcciones y
 * teléfonos de origen y destino, las notas (que en 7.174 comisiones traen nombres de
 * personas) y el historial completo con el nombre del empleado que tocó cada estado.
 * Recorriendo 1..9353 se bajaba la agenda entera.
 *
 * Ahora la vista pública muestra sólo lo necesario para saber dónde está el envío, y
 * el detalle exige un código de un solo uso que llega al titular.
 */
class PublicTrackingController extends Controller
{
    private const SESION_MINUTOS = 15;

    public function __construct(private readonly TrackingOtpService $otpService)
    {
    }

    /**
     * Datos mínimos. Acepta el código opaco o, por compatibilidad con los links ya
     * enviados a clientes, el id numérico.
     */
    public function show(string $identifier): JsonResponse
    {
        $commission = $this->resolver($identifier);

        if (! $commission) {
            return response()->json([
                'success' => false,
                'message' => 'No se encontró el envío con el número proporcionado',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'tracking_number' => $commission->id,
                'tracking_code' => $commission->tracking_code,
                'status' => $commission->status?->value,
                'status_label' => $commission->status?->getClienteStatus(),
                // Sólo la localidad: nunca dirección, teléfono ni horarios.
                'origin_city' => $commission->originLocation?->origin,
                'destination_city' => $commission->destinationLocation?->origin,
                'date' => $commission->date?->format('Y-m-d'),
                'last_update' => $commission->updated_at?->format('Y-m-d H:i'),
                'items_count' => (int) $commission->items->sum('quantity'),
                'detail_requires_verification' => true,
            ],
        ]);
    }

    /**
     * Pide un código al titular.
     *
     * La respuesta es SIEMPRE la misma exista o no el envío y tenga o no contacto
     * cargado: si variara, el endpoint serviría para descubrir qué números existen.
     */
    public function requestCode(Request $request, string $identifier): JsonResponse
    {
        $commission = $this->resolver($identifier);

        $masked = null;

        if ($commission) {
            $masked = $this->otpService->emitir($commission, $request->ip());
        }

        return response()->json([
            'success' => true,
            'message' => 'Si el envío existe y tiene un contacto asociado, te enviamos un código.',
            'sent_to' => $masked,
        ]);
    }

    /**
     * Verifica el código y devuelve un token de sesión corto para ver el detalle.
     */
    public function verifyCode(Request $request, string $identifier): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $commission = $this->resolver($identifier);

        if (! $commission || ! $this->otpService->verificar($commission, $validated['code'])) {
            return response()->json([
                'success' => false,
                'message' => 'El código no es válido o ya venció.',
            ], 401);
        }

        $token = Str::random(64);

        Cache::put(
            $this->cacheKey($token),
            $commission->id,
            now()->addMinutes(self::SESION_MINUTOS)
        );

        return response()->json([
            'success' => true,
            'token' => $token,
            'expires_in_minutes' => self::SESION_MINUTOS,
        ]);
    }

    /**
     * Detalle completo. Sólo con un token emitido por verifyCode().
     */
    public function detail(Request $request, string $identifier): JsonResponse
    {
        $token = $request->header('X-Tracking-Token') ?? $request->query('token');
        $commission = $this->resolver($identifier);

        if (! $commission || ! $token || Cache::get($this->cacheKey($token)) !== $commission->id) {
            return response()->json([
                'success' => false,
                'message' => 'Necesitás validar tu identidad para ver el detalle.',
            ], 401);
        }

        $commission->load(['originLocation', 'destinationLocation', 'items', 'logs']);

        return response()->json([
            'success' => true,
            'data' => [
                'tracking_number' => $commission->id,
                'status' => $commission->status?->value,
                'status_label' => $commission->status?->getClienteStatus(),
                'date' => $commission->date?->format('Y-m-d'),
                'notes' => $commission->notes,
                'origin' => $this->ubicacion($commission->originLocation),
                'destination' => $this->ubicacion($commission->destinationLocation),
                'items' => $commission->items->map(fn ($i) => [
                    'size' => $i->size?->value,
                    'quantity' => (int) $i->quantity,
                    'detail' => $i->detail,
                ])->values(),
                // Historial SIN el nombre del empleado: al cliente le sirve saber qué
                // pasó y cuándo, no quién de la empresa lo hizo.
                'history' => $commission->logs
                    ->sortBy('created_at')
                    ->map(fn ($l) => [
                        'status' => $l->new_status,
                        'at' => $l->created_at?->format('Y-m-d H:i'),
                    ])->values(),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function ubicacion($location): ?array
    {
        if (! $location) {
            return null;
        }

        return [
            'name' => $location->name,
            'address' => $location->address,
            'city' => $location->origin,
            'phone' => $location->phone,
            'schedule' => $location->schedule,
        ];
    }

    /**
     * Busca por código opaco; si el identificador es numérico, cae al id para no
     * romper los links de seguimiento ya enviados a los clientes.
     */
    private function resolver(string $identifier): ?Commission
    {
        $commission = Commission::with(['originLocation:id,origin,name,address,phone,schedule', 'destinationLocation:id,origin,name,address,phone,schedule', 'items'])
            ->where('tracking_code', mb_strtoupper(trim($identifier)))
            ->first();

        if ($commission) {
            return $commission;
        }

        if (ctype_digit($identifier)) {
            return Commission::with(['originLocation', 'destinationLocation', 'items'])->find((int) $identifier);
        }

        return null;
    }

    private function cacheKey(string $token): string
    {
        return 'tracking_session:' . hash('sha256', $token);
    }
}
