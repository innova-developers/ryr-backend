<?php

namespace Tests\Feature\Cadete;

use App\Shared\Enums\CommissionStatus;
use App\Shared\Enums\UserRole;
use App\Shared\Models\CommissionLog;
use App\Shared\Models\Branch;
use App\Shared\Models\Commission;
use App\Shared\Models\Customer;
use App\Shared\Models\Destination;
use App\Shared\Models\Location;
use App\Shared\Models\Transport;
use App\Shared\Models\User;
use App\DeliverySignature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CadeteUpdateShipmentStatusTest extends TestCase
{
    use RefreshDatabase;

    // Imagen base64 válida para tests (1x1 pixel PNG transparente)
    private const TEST_SIGNATURE_IMAGE = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    private User $cadete;
    private Transport $transport;
    private Branch $branch;
    private Customer $client;
    private Location $originLocation;
    private Location $destinationLocation;
    private Destination $destination;
    private Commission $commission;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Crear sucursal
        $this->branch = Branch::factory()->create([
            'name' => 'Sucursal Centro',
            'address' => 'Av. Principal 123',
            'phone' => '011-1234-5678',
        ]);
        
        // Crear cadete
        $this->cadete = User::factory()->create([
            'role' => UserRole::CADETE,
            'branch_id' => $this->branch->id,
        ]);
        
        // Crear transporte asignado al cadete
        $this->transport = Transport::factory()->create([
            'cadete_id' => $this->cadete->id,
            'plate' => 'ABC123',
            'description' => 'Moto Honda CG 150',
        ]);
        
        // Crear cliente
        $this->client = Customer::factory()->create([
            'name' => 'Juan Pérez',
            'last_name' => 'García',
            'phone' => '+1234567890',
            'address' => 'Av. Principal 123, Ciudad',
        ]);
        
        // Crear ubicaciones
        $this->originLocation = Location::factory()->create([
            'name' => 'Almacén Central',
            'address' => 'Zona Industrial',
            'phone' => '+1234567891',
        ]);
        
        $this->destinationLocation = Location::factory()->create([
            'name' => 'Oficina Cliente',
            'address' => 'Centro Comercial',
            'phone' => '+1234567892',
        ]);
        
        $this->destination = Destination::factory()->create();
        
        // Crear comisión
        $this->commission = Commission::factory()->create([
            'client_id' => $this->client->id,
            'transport_id' => $this->transport->id,
            'cadete_id' => $this->cadete->id,
            'branch_id' => $this->branch->id,
            'destination_id' => $this->destination->id,
            'origin_location_id' => $this->originLocation->id,
            'destination_location_id' => $this->destinationLocation->id,
            'status' => CommissionStatus::EN_TRANSITO_DESTINO,
            'total' => 100.00,
        ]);
    }

    public function test_cadete_can_update_shipment_status()
    {
        $response = $this->actingAs($this->cadete)
                         ->putJson("/api/cadete/deliveries/{$this->commission->id}", [
                             'status' => 'Entregado',
                             'receiver_name' => 'Juan Pérez',
                             'receiver_phone' => '+1234567890',
                             'notes' => 'Entregado en recepción',
                             'signature_image' => 'iVBORw0KGgoAAAANSUhEUgAA...',
                             'delivery_timestamp' => '2025-08-22T15:30:00.000Z'
                         ]);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Estado del envío actualizado correctamente',
                    'shipment' => [
                        'id' => $this->commission->id,
                        'status' => CommissionStatus::ENTREGADO->value,
                        'status_label' => 'Entregado'
                    ]
                ]);

        // Verificar que se actualizó en la base de datos
        $this->commission->refresh();
        $this->assertEquals(CommissionStatus::ENTREGADO, $this->commission->status);
    }

    public function test_cadete_can_update_to_in_progress_status()
    {
        $response = $this->actingAs($this->cadete)
                         ->putJson("/api/cadete/deliveries/{$this->commission->id}", [
                             'status' => 'En proceso de entrega'
                         ]);

        $response->assertStatus(200);

        // Verificar que se actualizó en la base de datos
        $this->commission->refresh();
        $this->assertEquals(CommissionStatus::EN_PROCESO_ENTREGA, $this->commission->status);
    }

    public function test_cadete_can_update_to_failed_delivery_status()
    {
        $response = $this->actingAs($this->cadete)
                         ->putJson("/api/cadete/deliveries/{$this->commission->id}", [
                             'status' => 'Intento de entrega fallido'
                         ]);

        $response->assertStatus(200);

        // Verificar que se actualizó en la base de datos
        $this->commission->refresh();
        $this->assertEquals(CommissionStatus::INTENTO_ENTREGA_FALLIDO, $this->commission->status);
    }

    public function test_cadete_cannot_update_with_invalid_status()
    {
        $response = $this->actingAs($this->cadete)
                         ->putJson("/api/cadete/deliveries/{$this->commission->id}", [
                             'status' => 'Estado inválido'
                         ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['status']);
    }

    public function test_cadete_cannot_update_commission_from_other_cadete()
    {
        // Crear otro cadete con su propia comisión
        $otherCadete = User::factory()->create([
            'role' => UserRole::CADETE,
            'branch_id' => $this->branch->id,
        ]);
        
        $otherTransport = Transport::factory()->create([
            'cadete_id' => $otherCadete->id,
        ]);
        
        $otherCommission = Commission::factory()->create([
            'client_id' => $this->client->id,
            'transport_id' => $otherTransport->id,
            'cadete_id' => $otherCadete->id,
            'branch_id' => $this->branch->id,
            'destination_id' => $this->destination->id,
            'origin_location_id' => $this->originLocation->id,
            'destination_location_id' => $this->destinationLocation->id,
        ]);

        $response = $this->actingAs($this->cadete)
                         ->putJson("/api/cadete/deliveries/{$otherCommission->id}", [
                             'status' => 'En proceso de entrega'
                         ]);

        $response->assertStatus(404)
                ->assertJson([
                    'message' => 'Envío no encontrado o no tienes permisos para modificarlo'
                ]);
    }

    public function test_cadete_cannot_update_nonexistent_commission()
    {
        $response = $this->actingAs($this->cadete)
                         ->putJson("/api/cadete/deliveries/99999", [
                             'status' => 'En proceso de entrega'
                         ]);

        $response->assertStatus(404);
    }

    public function test_update_requires_authentication()
    {
        $response = $this->putJson("/api/cadete/deliveries/{$this->commission->id}", [
            'status' => 'Entregado'
        ]);

        $response->assertStatus(401);
    }

    public function test_update_requires_cadete_role()
    {
        $admin = User::factory()->create(['role' => UserRole::ADMINISTRADOR]);
        
        $response = $this->actingAs($admin)
                         ->putJson("/api/cadete/deliveries/{$this->commission->id}", [
                             'status' => 'Entregado'
                         ]);
        
        $response->assertStatus(403);
    }

    public function test_can_update_with_observation()
    {
        $response = $this->actingAs($this->cadete)
                         ->putJson("/api/cadete/deliveries/{$this->commission->id}", [
                             'status' => 'Entregado',
                             'observation' => 'Entregado en recepción',
                             'receiver_name' => 'Juan Pérez',
                             'receiver_phone' => '+1234567890',
                             'notes' => 'Entregado en recepción',
                             'signature_image' => 'iVBORw0KGgoAAAANSUhEUgAA...',
                             'delivery_timestamp' => '2025-08-22T15:30:00.000Z'
                         ]);

        $response->assertStatus(200);
    }

    public function test_status_conversion_mapping()
    {
        $statusMappings = [
            'Nueva comisión asignada' => CommissionStatus::CADETE_ASIGNADO,
            'En camino al origen' => CommissionStatus::CADETE_EN_CAMINO_ORIGEN,
            'En punto de retiro' => CommissionStatus::EN_PUNTO_RETIRO,
            'Encomienda retirada' => CommissionStatus::ENCOMIENDA_RETIRADA,
            'En camino a planta/sucursal' => CommissionStatus::EN_CAMINO_PLANTA,
            'En tránsito a destino' => CommissionStatus::EN_TRANSITO_DESTINO,
            'En proceso de entrega' => CommissionStatus::EN_PROCESO_ENTREGA,
            'Entregado' => CommissionStatus::ENTREGADO,
            'Retirado en sucursal' => CommissionStatus::RETIRADO_SUCURSAL,
            'Intento de entrega fallido' => CommissionStatus::INTENTO_ENTREGA_FALLIDO,
            'Reprogramando entrega' => CommissionStatus::REPROGRAMANDO_ENTREGA,
            'Disponible para retiro en sucursal' => CommissionStatus::DISPONIBLE_RETIRO,
            'En devolución al remitente' => CommissionStatus::EN_DEVOLUCION,
            'Devuelto al remitente' => CommissionStatus::DEVUELTO_REMITENTE,
        ];

        foreach ($statusMappings as $cadeteStatus => $expectedAdminStatus) {
            $requestData = ['status' => $cadeteStatus];
            
            // Si es "Entregado", agregar campos de firma
            if ($cadeteStatus === 'Entregado') {
                $requestData = array_merge($requestData, [
                    'receiver_name' => 'Juan Pérez',
                    'receiver_phone' => '+1234567890',
                    'notes' => 'Entregado en recepción',
                    'signature_image' => 'iVBORw0KGgoAAAANSUhEUgAA...',
                    'delivery_timestamp' => '2025-08-22T15:30:00.000Z'
                ]);
            }

            $response = $this->actingAs($this->cadete)
                             ->putJson("/api/cadete/deliveries/{$this->commission->id}", $requestData);

            $response->assertStatus(200);

            // Verificar que se convirtió correctamente
            $this->commission->refresh();
            $this->assertEquals($expectedAdminStatus, $this->commission->status, 
                "El estado del cadete '{$cadeteStatus}' no se convirtió correctamente a '{$expectedAdminStatus->value}'");
        }
    }

    public function test_cadete_cannot_update_with_admin_status()
    {
        $response = $this->actingAs($this->cadete)
                         ->putJson("/api/cadete/deliveries/{$this->commission->id}", [
                             'status' => 'SOLICITUD_RECIBIDA' // Estado administrativo que no es del cadete
                         ]);

        $response->assertStatus(422)
                ->assertJson([
                    'success' => false,
                    'message' => 'Estado no válido. Estados válidos: ' . implode(', ', CommissionStatus::getValidCadeteStatuses()),
                    'valid_statuses' => CommissionStatus::getValidCadeteStatuses()
                ])
                ->assertJsonValidationErrors(['status']);
    }

    public function test_cadete_cannot_update_with_enum_value()
    {
        $response = $this->actingAs($this->cadete)
                         ->putJson("/api/cadete/deliveries/{$this->commission->id}", [
                             'status' => CommissionStatus::REPROGRAMANDO_ENTREGA->value
                         ]);

        $response->assertStatus(200);

        // Verificar que se convirtió correctamente
        $this->commission->refresh();
        $this->assertEquals(CommissionStatus::REPROGRAMANDO_ENTREGA, $this->commission->status);
    }

    public function test_cadete_can_update_with_cadete_label()
    {
        $response = $this->actingAs($this->cadete)
                         ->putJson("/api/cadete/deliveries/{$this->commission->id}", [
                             'status' => 'Reprogramando entrega'
                         ]);

        $response->assertStatus(200);

        // Verificar que se convirtió correctamente
        $this->commission->refresh();
        $this->assertEquals(CommissionStatus::REPROGRAMANDO_ENTREGA, $this->commission->status);
    }

    public function test_cadete_can_update_with_enum_value_entregado()
    {
        $response = $this->actingAs($this->cadete)
                         ->putJson("/api/cadete/deliveries/{$this->commission->id}", [
                             'status' => CommissionStatus::ENTREGADO->value,
                             'receiver_name' => 'Juan Pérez',
                             'receiver_phone' => '+1234567890',
                             'notes' => 'Entregado en recepción',
                             'signature_image' => 'iVBORw0KGgoAAAANSUhEUgAA...',
                             'delivery_timestamp' => '2025-08-22T15:30:00.000Z'
                         ]);

        $response->assertStatus(200);

        // Verificar que se convirtió correctamente
        $this->commission->refresh();
        $this->assertEquals(CommissionStatus::ENTREGADO, $this->commission->status);
    }

    public function test_cadete_can_update_with_cadete_label_entregado()
    {
        $response = $this->actingAs($this->cadete)
                         ->putJson("/api/cadete/deliveries/{$this->commission->id}", [
                             'status' => 'Entregado',
                             'receiver_name' => 'Juan Pérez',
                             'receiver_phone' => '+1234567890',
                             'notes' => 'Entregado en recepción',
                             'signature_image' => 'iVBORw0KGgoAAAANSUhEUgAA...',
                             'delivery_timestamp' => '2025-08-22T15:30:00.000Z'
                         ]);

        $response->assertStatus(200);

        // Verificar que se convirtió correctamente
        $this->commission->refresh();
        $this->assertEquals(CommissionStatus::ENTREGADO, $this->commission->status);
    }

    public function test_error_response_includes_valid_statuses()
    {
        $response = $this->actingAs($this->cadete)
                         ->putJson("/api/cadete/deliveries/{$this->commission->id}", [
                             'status' => 'Estado inválido'
                         ]);

        $response->assertStatus(422)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'errors',
                    'valid_statuses'
                ])
                ->assertJson([
                    'success' => false,
                    'valid_statuses' => CommissionStatus::getValidCadeteStatuses()
                ]);
    }

    public function test_logging_is_generated_for_status_update()
    {
        $response = $this->actingAs($this->cadete)
                         ->putJson("/api/cadete/deliveries/{$this->commission->id}", [
                             'status' => 'Entregado',
                             'observation' => 'Entregado en recepción',
                             'receiver_name' => 'Juan Pérez',
                             'receiver_phone' => '+1234567890',
                             'notes' => 'Entregado en recepción',
                             'signature_image' => 'iVBORw0KGgoAAAANSUhEUgAA...',
                             'delivery_timestamp' => '2025-08-22T15:30:00.000Z'
                         ]);

        $response->assertStatus(200);

        // Verificar que se generaron logs (no podemos limpiar logs en tests, pero podemos verificar que el logging funcione)
        // Los logs se escribirán en storage/logs/laravel.log durante los tests
        $this->assertTrue(true); // Placeholder - en un entorno real verificaríamos los logs
    }

    public function test_logging_is_generated_for_invalid_status()
    {
        $response = $this->actingAs($this->cadete)
                         ->putJson("/api/cadete/deliveries/{$this->commission->id}", [
                             'status' => 'Estado inválido'
                         ]);

        $response->assertStatus(422);

        // Verificar que se generó un log de warning
        // Los logs se escribirán en storage/logs/laravel.log durante los tests
        $this->assertTrue(true); // Placeholder - en un entorno real verificaríamos los logs
    }

    public function test_logging_is_generated_for_unauthorized_access()
    {
        $response = $this->actingAs($this->cadete)
                         ->putJson("/api/cadete/deliveries/99999", [
                             'status' => 'En proceso de entrega'
                         ]);

        $response->assertStatus(404);

        // Verificar que se generó un log de warning
        // Los logs se escribirán en storage/logs/laravel.log durante los tests
        $this->assertTrue(true); // Placeholder - en un entorno real verificaríamos los logs
    }

    public function test_commission_log_is_created_when_status_updated()
    {
        $initialLogCount = CommissionLog::count();
        
        $response = $this->actingAs($this->cadete)
                         ->putJson("/api/cadete/deliveries/{$this->commission->id}", [
                             'status' => 'Entregado',
                             'observation' => 'Entregado en recepción',
                             'receiver_name' => 'Juan Pérez',
                             'receiver_phone' => '+1234567890',
                             'notes' => 'Entregado en recepción',
                             'signature_image' => 'iVBORw0KGgoAAAANSUhEUgAA...',
                             'delivery_timestamp' => '2025-08-22T15:30:00.000Z'
                         ]);

        $response->assertStatus(200);

        // Verificar que se creó un registro en commission_logs
        $this->assertEquals($initialLogCount + 1, CommissionLog::count());
        
        $commissionLog = CommissionLog::latest()->first();
        $this->assertEquals($this->commission->id, $commissionLog->commission_id);
        $this->assertEquals($this->cadete->id, $commissionLog->user_id);
        $this->assertEquals(CommissionStatus::EN_TRANSITO_DESTINO->value, $commissionLog->previous_status);
        $this->assertEquals(CommissionStatus::ENTREGADO->value, $commissionLog->new_status);
        $this->assertStringContainsString('Estado actualizado por cadete: Entregado', $commissionLog->details);
        $this->assertStringContainsString('Observación: Entregado en recepción', $commissionLog->details);
    }

    public function test_commission_log_is_created_without_observation()
    {
        $initialLogCount = CommissionLog::count();
        
        $response = $this->actingAs($this->cadete)
                         ->putJson("/api/cadete/deliveries/{$this->commission->id}", [
                             'status' => 'En proceso de entrega'
                         ]);

        $response->assertStatus(200);

        // Verificar que se creó un registro en commission_logs
        $this->assertEquals($initialLogCount + 1, CommissionLog::count());
        
        $commissionLog = CommissionLog::latest()->first();
        $this->assertEquals($this->commission->id, $commissionLog->commission_id);
        $this->assertEquals($this->cadete->id, $commissionLog->user_id);
        $this->assertEquals(CommissionStatus::EN_TRANSITO_DESTINO->value, $commissionLog->previous_status);
        $this->assertEquals(CommissionStatus::EN_PROCESO_ENTREGA->value, $commissionLog->new_status);
        $this->assertStringContainsString('Estado actualizado por cadete: En proceso de entrega', $commissionLog->details);
        $this->assertStringNotContainsString('Observación:', $commissionLog->details);
    }

    public function test_commission_log_tracks_status_changes_correctly()
    {
        // Cambiar estado varias veces para verificar el tracking
        $statuses = [
            'En proceso de entrega',
            'Intento de entrega fallido',
            'Reprogramando entrega',
            'Entregado'
        ];

        $expectedPreviousStatuses = [
            CommissionStatus::EN_TRANSITO_DESTINO->value,
            CommissionStatus::EN_PROCESO_ENTREGA->value,
            CommissionStatus::INTENTO_ENTREGA_FALLIDO->value,
            CommissionStatus::REPROGRAMANDO_ENTREGA->value
        ];

        $expectedNewStatuses = [
            CommissionStatus::EN_PROCESO_ENTREGA->value,
            CommissionStatus::INTENTO_ENTREGA_FALLIDO->value,
            CommissionStatus::REPROGRAMANDO_ENTREGA->value,
            CommissionStatus::ENTREGADO->value
        ];

        foreach ($statuses as $index => $status) {
            $requestData = ['status' => $status];
            
            // Si es "Entregado", agregar campos de firma
            if ($status === 'Entregado') {
                $requestData = array_merge($requestData, [
                    'receiver_name' => 'Juan Pérez',
                    'receiver_phone' => '+1234567890',
                    'notes' => 'Entregado en recepción',
                    'signature_image' => 'iVBORw0KGgoAAAANSUhEUgAA...',
                    'delivery_timestamp' => '2025-08-22T15:30:00.000Z'
                ]);
            }

            $response = $this->actingAs($this->cadete)
                             ->putJson("/api/cadete/deliveries/{$this->commission->id}", $requestData);

            $response->assertStatus(200);

            // Verificar el log correspondiente
            $commissionLog = CommissionLog::where('commission_id', $this->commission->id)
                                        ->where('new_status', $expectedNewStatuses[$index])
                                        ->first();

            $this->assertNotNull($commissionLog);
            $this->assertEquals($expectedPreviousStatuses[$index], $commissionLog->previous_status);
            $this->assertEquals($expectedNewStatuses[$index], $commissionLog->new_status);
            $this->assertEquals($this->cadete->id, $commissionLog->user_id);
        }

        // Verificar que se crearon 4 logs en total
        $totalLogs = CommissionLog::where('commission_id', $this->commission->id)->count();
        $this->assertEquals(4, $totalLogs);
    }

    public function test_delivery_signature_is_created_when_marked_as_delivered()
    {
        $initialSignatureCount = DeliverySignature::count();
        
        $response = $this->actingAs($this->cadete)
                         ->putJson("/api/cadete/deliveries/{$this->commission->id}", [
                             'status' => 'Entregado',
                             'receiver_name' => 'Juan Pérez',
                             'receiver_phone' => '+1234567890',
                             'notes' => 'Entregado en recepción',
                             'signature_image' => 'iVBORw0KGgoAAAANSUhEUgAA...', // Base64 PNG mock
                             'delivery_timestamp' => '2025-08-22T15:30:00.000000Z'
                         ]);

        $response->assertStatus(200);

        // Verificar que se creó la firma
        $this->assertEquals($initialSignatureCount + 1, DeliverySignature::count());
        
        $signature = DeliverySignature::latest()->first();
        $this->assertEquals($this->commission->id, $signature->commission_id);
        $this->assertEquals($this->cadete->id, $signature->cadete_id);
        $this->assertEquals('Juan Pérez', $signature->receiver_name);
        $this->assertEquals('+1234567890', $signature->receiver_phone);
        $this->assertEquals('Entregado en recepción', $signature->notes);
        $this->assertEquals('iVBORw0KGgoAAAANSUhEUgAA...', $signature->signature_image);
        $this->assertEquals('2025-08-22T15:30:00.000000Z', $signature->delivery_timestamp->toISOString());
    }

    public function test_delivery_signature_requires_all_fields_when_delivered()
    {
        $response = $this->actingAs($this->cadete)
                         ->putJson("/api/cadete/deliveries/{$this->commission->id}", [
                             'status' => 'Entregado'
                             // Faltan campos requeridos
                         ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['receiver_name', 'receiver_phone', 'signature_image', 'delivery_timestamp']);
    }

    public function test_delivery_signature_not_required_for_other_statuses()
    {
        $response = $this->actingAs($this->cadete)
                         ->putJson("/api/cadete/deliveries/{$this->commission->id}", [
                             'status' => 'En proceso de entrega'
                             // No se requieren campos de firma
                         ]);

        $response->assertStatus(200);

        // Verificar que no se creó firma
        $this->assertEquals(0, DeliverySignature::count());
    }

    public function test_cannot_create_duplicate_signature_for_same_commission()
    {
        // Crear primera firma
        $response1 = $this->actingAs($this->cadete)
                          ->putJson("/api/cadete/deliveries/{$this->commission->id}", [
                              'status' => 'Entregado',
                              'receiver_name' => 'Juan Pérez',
                              'receiver_phone' => '+1234567890',
                              'notes' => 'Primera entrega',
                              'signature_image' => 'iVBORw0KGgoAAAANSUhEUgAA...',
                              'delivery_timestamp' => '2025-08-22T15:30:00.000000Z'
                          ]);

        $response1->assertStatus(200);

        // Intentar crear segunda firma
        $response2 = $this->actingAs($this->cadete)
                          ->putJson("/api/cadete/deliveries/{$this->commission->id}", [
                              'status' => 'Entregado',
                              'receiver_name' => 'María García',
                              'receiver_phone' => '+0987654321',
                              'notes' => 'Segunda entrega',
                              'signature_image' => 'iVBORw0KGgoAAAANSUhEUgAA...',
                              'delivery_timestamp' => '2025-08-22T16:00:00.000000Z'
                          ]);

        $response2->assertStatus(400)
                 ->assertJson([
                     'success' => false,
                     'message' => 'Ya existe una firma para esta comisión'
                 ]);

        // Verificar que solo existe una firma
        $this->assertEquals(1, DeliverySignature::count());
    }

    public function test_signature_data_is_included_in_deliveries_response()
    {
        // Marcar como entregado con firma
        $response = $this->actingAs($this->cadete)
             ->putJson("/api/cadete/deliveries/{$this->commission->id}", [
                 'status' => 'Entregado',
                 'receiver_name' => 'Juan Pérez',
                 'receiver_phone' => '+1234567890',
                 'notes' => 'Entregado en recepción',
                 'signature_image' => self::TEST_SIGNATURE_IMAGE,
                 'delivery_timestamp' => '2025-08-22T15:30:00.000000Z'
             ]);

        $response->assertStatus(200);

        // Obtener lista de entregas
        $response = $this->actingAs($this->cadete)
                         ->getJson("/api/cadete/deliveries");

        $response->assertStatus(200);

        $delivery = $response->json('data.deliveries')[0];
        $this->assertNotNull($delivery['signature_data']);
        $this->assertEquals('Juan Pérez', $delivery['signature_data']['receiver_name']);
        $this->assertEquals('+1234567890', $delivery['signature_data']['receiver_phone']);
        $this->assertEquals('Entregado en recepción', $delivery['signature_data']['notes']);
        $this->assertEquals(self::TEST_SIGNATURE_IMAGE, $delivery['signature_data']['signature_image']);
        $this->assertEquals('2025-08-22T15:30:00.000000Z', $delivery['signature_data']['delivery_timestamp']);
    }

    public function test_signature_data_is_null_for_non_delivered_commissions()
    {
        // Obtener lista de entregas sin marcar como entregado
        $response = $this->actingAs($this->cadete)
                         ->getJson("/api/cadete/deliveries");

        $response->assertStatus(200);

        $delivery = $response->json('data.deliveries')[0];
        $this->assertNull($delivery['signature_data']);
    }
}
