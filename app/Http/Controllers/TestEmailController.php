<?php

namespace App\Http\Controllers;

use App\Mail\CommissionStatusChangedMail;
use App\Services\WhatsAppService;
use App\Shared\Enums\CommissionStatus;
use App\Shared\Models\Commission;
use App\Shared\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class TestEmailController extends Controller
{
    public function __construct(
        private WhatsAppService $whatsAppService
    ) {
    }

    /**
     * Ruta de prueba para enviar email de cambio de estado
     * GET /api/test-email?email=test@example.com
     */
    public function testCommissionStatusEmail(Request $request): JsonResponse
    {
        try {
            $email = $request->get('email');

            if (! $email) {
                return response()->json([
                    'success' => false,
                    'message' => 'El parámetro email es requerido',
                ], 400);
            }

            // Buscar una comisión real para usar sus datos, o crear datos de prueba
            $commission = Commission::with(['originLocation', 'destinationLocation', 'client'])
                ->first();

            if (! $commission) {
                // Si no hay comisiones, crear datos de prueba
                $commission = $this->createTestCommission();
                $customer = $this->createTestCustomer($email);
            } else {
                // Usar el cliente de la comisión o crear uno de prueba
                $customer = $commission->client;

                if (! $customer) {
                    $customer = $this->createTestCustomer($email);
                } else {
                    // Crear una copia del cliente con el email de prueba
                    $customer = clone $customer;
                    $customer->email = $email;
                }
            }

            // Estados de prueba
            $previousStatus = CommissionStatus::BUSCANDO_CADETE->value;
            $newStatus = CommissionStatus::ENTREGADO->value;
            $details = 'Este es un email de prueba del sistema de notificaciones.';

            // Enviar el email
            Mail::to($email)->send(
                new CommissionStatusChangedMail(
                    $commission,
                    $customer,
                    $previousStatus,
                    $newStatus,
                    $details
                )
            );

            Log::info('Email de prueba enviado', [
                'email' => $email,
                'commission_id' => $commission->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Email de prueba enviado correctamente',
                'data' => [
                    'email' => $email,
                    'commission_id' => $commission->id,
                    'previous_status' => $previousStatus,
                    'new_status' => $newStatus,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error enviando email de prueba', [
                'email' => $request->get('email'),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al enviar el email de prueba',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Crear una comisión de prueba si no hay comisiones reales
     */
    private function createTestCommission(): Commission
    {
        $commission = new Commission();
        $commission->id = 99999;
        $commission->date = now();
        $commission->status = CommissionStatus::ENTREGADO;
        $commission->total = 1500.00;
        $commission->notes = 'Comisión de prueba para envío de email';

        // Crear locations de prueba
        $originLocation = new \App\Shared\Models\Location();
        $originLocation->name = 'Cliente Prueba';
        $originLocation->address = 'Av. Corrientes 1234';
        $originLocation->origin = 'Buenos Aires';
        $originLocation->phone = '+54 11 1234-5678';
        $originLocation->schedule = 'Lunes a Viernes 9:00 - 18:00';

        $destinationLocation = new \App\Shared\Models\Location();
        $destinationLocation->name = 'Destino Prueba';
        $destinationLocation->address = 'Av. Santa Fe 5678';
        $destinationLocation->origin = 'Córdoba';
        $destinationLocation->phone = '+54 351 9876-5432';
        $destinationLocation->schedule = 'Lunes a Viernes 8:00 - 17:00';

        $commission->setRelation('originLocation', $originLocation);
        $commission->setRelation('destinationLocation', $destinationLocation);

        return $commission;
    }

    /**
     * Crear un cliente de prueba
     */
    private function createTestCustomer(string $email): Customer
    {
        $customer = new Customer();
        $customer->id = 99999;
        $customer->name = 'Cliente';
        $customer->last_name = 'Prueba';
        $customer->email = $email;
        $customer->dni = '12345678';
        $customer->phone = '+54 11 1234-5678';
        $customer->mobile = '+54 11 9876-5432';

        return $customer;
    }

    /**
     * Ruta de prueba para enviar WhatsApp de cambio de estado
     * GET /api/test-whatsapp?phone=+5491123456789
     */
    public function testCommissionStatusWhatsApp(Request $request): JsonResponse
    {
        try {
            $phone = $request->get('phone');

            if (! $phone) {
                return response()->json([
                    'success' => false,
                    'message' => 'El parámetro phone es requerido',
                ], 400);
            }

            // Buscar una comisión real para usar sus datos, o crear datos de prueba
            $commission = Commission::with(['originLocation', 'destinationLocation', 'client'])
                ->first();

            if (! $commission) {
                // Si no hay comisiones, crear datos de prueba
                $commission = $this->createTestCommission();
                $customer = $this->createTestCustomer('test@example.com');
            } else {
                // Usar el cliente de la comisión o crear uno de prueba
                $customer = $commission->client;

                if (! $customer) {
                    $customer = $this->createTestCustomer('test@example.com');
                } else {
                    // Crear una copia del cliente
                    $customer = clone $customer;
                }
            }

            // Estados de prueba
            $newStatus = CommissionStatus::RETIRADO_SUCURSAL->value;
            $details = 'Este es un mensaje de prueba del sistema de notificaciones WhatsApp.';

            // Enviar el WhatsApp
            $success = $this->whatsAppService->sendCommissionStatusNotification(
                $phone,
                $commission->id,
                $newStatus,
                $customer->full_name ?? "{$customer->name} {$customer->last_name}",
                $details,
                $commission
            );

            if ($success) {
                Log::info('WhatsApp de prueba enviado', [
                    'phone' => $phone,
                    'commission_id' => $commission->id,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'WhatsApp de prueba enviado correctamente',
                    'data' => [
                        'phone' => $phone,
                        'commission_id' => $commission->id,
                        'status' => $newStatus,
                    ],
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Error al enviar el WhatsApp. Revisa los logs para más detalles.',
                    'data' => [
                        'phone' => $phone,
                        'commission_id' => $commission->id,
                    ],
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('Error enviando WhatsApp de prueba', [
                'phone' => $request->get('phone'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al enviar el WhatsApp de prueba',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
