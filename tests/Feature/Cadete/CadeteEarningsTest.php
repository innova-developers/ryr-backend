<?php

namespace Tests\Feature\Cadete;

use App\Shared\Enums\CommissionStatus;
use App\Shared\Enums\UserRole;
use App\Shared\Models\Branch;
use App\Shared\Models\Commission;
use App\Shared\Models\Customer;
use App\Shared\Models\Destination;
use App\Shared\Models\Location;
use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CadeteEarningsTest extends TestCase
{
    use RefreshDatabase;

    private User $cadete;
    private Branch $branch;
    private Customer $client;
    private Location $originLocation;
    private Location $destinationLocation;
    private Destination $destination;

    protected function setUp(): void
    {
        parent::setUp();

        // Crear sucursal
        $this->branch = Branch::factory()->create();

        // Crear cadete
        $this->cadete = User::factory()->create([
            'role' => UserRole::CADETE,
            'branch_id' => $this->branch->id,
            'commission_percentage' => 15.0, // 15% por defecto para los tests
        ]);

        // Crear cliente
        $this->client = Customer::factory()->create();

        // Crear ubicaciones
        $this->originLocation = Location::factory()->create([
            'name' => 'Almacén Central',
            'origin' => 'CABA',
        ]);

        $this->destinationLocation = Location::factory()->create([
            'name' => 'Oficina Norte',
            'origin' => 'Zona Norte',
        ]);

        // Crear destino
        $this->destination = Destination::factory()->create([
            'origin' => 'CABA',
            'destination' => 'Zona Norte',
        ]);
    }

    /** @test */
    public function cadete_can_access_earnings_endpoint()
    {
        $response = $this->actingAs($this->cadete)
            ->getJson('/api/cadete/earnings');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'summary',
                        'breakdown',
                        'payment_status',
                        'daily_breakdown',
                        'top_routes',
                        'performance_metrics',
                        'recent_payments',
                        'pagination',
                    ],
                ]);
    }

    /** @test */
    public function earnings_returns_correct_summary_for_month()
    {
        // Crear comisiones entregadas para este mes
        Commission::factory()->create([
            'cadete_id' => $this->cadete->id,
            'pickup_cadete_id' => $this->cadete->id,
            'client_id' => $this->client->id,
            'destination_id' => $this->destination->id,
            'origin_location_id' => $this->originLocation->id,
            'destination_location_id' => $this->destinationLocation->id,
            'status' => CommissionStatus::ENTREGADO,
            'total' => 1500.00,
            'date' => now()->startOfMonth()->addDays(5),
        ]);

        Commission::factory()->create([
            'cadete_id' => $this->cadete->id,
            'pickup_cadete_id' => $this->cadete->id,
            'client_id' => $this->client->id,
            'destination_id' => $this->destination->id,
            'origin_location_id' => $this->originLocation->id,
            'destination_location_id' => $this->destinationLocation->id,
            'status' => CommissionStatus::ENTREGADO,
            'total' => 2500.00,
            'date' => now()->startOfMonth()->addDays(10),
        ]);

        $response = $this->actingAs($this->cadete)
            ->getJson('/api/cadete/earnings?period=month');

        $response->assertStatus(200);

        $data = $response->json('data');

        // Con 15% de comisión: $4000 * 0.15 = $600
        $this->assertEquals(600.00, $data['summary']['total_earnings']);
        $this->assertEquals(2, $data['summary']['total_deliveries']);
        $this->assertEquals('Este mes', $data['summary']['period_label']);
        $this->assertEquals(4000.00, $data['summary']['total_commission_amount']);
        $this->assertEquals(15.0, $data['summary']['commission_percentage']);
    }

    /** @test */
    public function earnings_returns_correct_summary_for_today()
    {
        // Crear comisión entregada hoy
        Commission::factory()->create([
            'cadete_id' => $this->cadete->id,
            'pickup_cadete_id' => $this->cadete->id,
            'client_id' => $this->client->id,
            'destination_id' => $this->destination->id,
            'origin_location_id' => $this->originLocation->id,
            'destination_location_id' => $this->destinationLocation->id,
            'status' => CommissionStatus::ENTREGADO,
            'total' => 1200.00,
            'date' => now(),
        ]);

        $response = $this->actingAs($this->cadete)
            ->getJson('/api/cadete/earnings?period=today');

        $response->assertStatus(200);

        $data = $response->json('data');

        // Con 15% de comisión: $1200 * 0.15 = $180
        $this->assertEquals(180.00, $data['summary']['total_earnings']);
        $this->assertEquals(1, $data['summary']['total_deliveries']);
        $this->assertEquals('Hoy', $data['summary']['period_label']);
        $this->assertEquals(1200.00, $data['summary']['total_commission_amount']);
        $this->assertEquals(15.0, $data['summary']['commission_percentage']);
    }

    /** @test */
    public function earnings_returns_correct_summary_for_custom_period()
    {
        $startDate = now()->subDays(10)->format('Y-m-d');
        $endDate = now()->subDays(5)->format('Y-m-d');

        // Crear comisión en el período personalizado
        Commission::factory()->create([
            'cadete_id' => $this->cadete->id,
            'pickup_cadete_id' => $this->cadete->id,
            'client_id' => $this->client->id,
            'destination_id' => $this->destination->id,
            'origin_location_id' => $this->originLocation->id,
            'destination_location_id' => $this->destinationLocation->id,
            'status' => CommissionStatus::ENTREGADO,
            'total' => 800.00,
            'date' => now()->subDays(7),
        ]);

        $response = $this->actingAs($this->cadete)
            ->getJson("/api/cadete/earnings?period=custom&date_from={$startDate}&date_to={$endDate}");

        $response->assertStatus(200);

        $data = $response->json('data');

        // Con 15% de comisión: $800 * 0.15 = $120
        $this->assertEquals(120.00, $data['summary']['total_earnings']);
        $this->assertEquals('Período personalizado', $data['summary']['period_label']);
        $this->assertEquals(800.00, $data['summary']['total_commission_amount']);
        $this->assertEquals(15.0, $data['summary']['commission_percentage']);
    }

    /** @test */
    public function earnings_returns_correct_payment_status_breakdown()
    {
        // Comisión entregada (pagada)
        Commission::factory()->create([
            'cadete_id' => $this->cadete->id,
            'pickup_cadete_id' => $this->cadete->id,
            'client_id' => $this->client->id,
            'destination_id' => $this->destination->id,
            'origin_location_id' => $this->originLocation->id,
            'destination_location_id' => $this->destinationLocation->id,
            'status' => CommissionStatus::ENTREGADO,
            'total' => 1000.00,
            'date' => now(),
        ]);

        // Comisión en tránsito (pendiente)
        Commission::factory()->create([
            'cadete_id' => $this->cadete->id,
            'pickup_cadete_id' => $this->cadete->id,
            'client_id' => $this->client->id,
            'destination_id' => $this->destination->id,
            'origin_location_id' => $this->originLocation->id,
            'destination_location_id' => $this->destinationLocation->id,
            'status' => CommissionStatus::EN_TRANSITO_DESTINO,
            'total' => 500.00,
            'date' => now(),
        ]);

        // Comisión cancelada
        Commission::factory()->create([
            'cadete_id' => $this->cadete->id,
            'pickup_cadete_id' => $this->cadete->id,
            'client_id' => $this->client->id,
            'destination_id' => $this->destination->id,
            'origin_location_id' => $this->originLocation->id,
            'destination_location_id' => $this->destinationLocation->id,
            'status' => CommissionStatus::CANCELADO,
            'total' => 300.00,
            'date' => now(),
        ]);

        $response = $this->actingAs($this->cadete)
            ->getJson('/api/cadete/earnings?period=month');

        $response->assertStatus(200);

        $data = $response->json('data');
        $paymentStatus = $data['payment_status'];

        // Con 15% de comisión
        $this->assertEquals(1000.00, $paymentStatus['paid']['commission_amount']);
        $this->assertEquals(150.00, $paymentStatus['paid']['earnings']); // $1000 * 0.15
        $this->assertEquals(1, $paymentStatus['paid']['count']);
        $this->assertEquals(55.6, $paymentStatus['paid']['percentage']);

        $this->assertEquals(500.00, $paymentStatus['pending']['commission_amount']);
        $this->assertEquals(75.00, $paymentStatus['pending']['earnings']); // $500 * 0.15
        $this->assertEquals(1, $paymentStatus['pending']['count']);
        $this->assertEquals(27.8, $paymentStatus['pending']['percentage']);

        $this->assertEquals(300.00, $paymentStatus['cancelled']['commission_amount']);
        $this->assertEquals(45.00, $paymentStatus['cancelled']['earnings']); // $300 * 0.15
        $this->assertEquals(1, $paymentStatus['cancelled']['count']);
        $this->assertEquals(16.7, $paymentStatus['cancelled']['percentage']);
    }

    /** @test */
    public function earnings_returns_correct_daily_breakdown()
    {
        $today = now();
        $yesterday = now()->subDay();

        // Comisión entregada hoy
        Commission::factory()->create([
            'cadete_id' => $this->cadete->id,
            'pickup_cadete_id' => $this->cadete->id,
            'client_id' => $this->client->id,
            'destination_id' => $this->destination->id,
            'origin_location_id' => $this->originLocation->id,
            'destination_location_id' => $this->destinationLocation->id,
            'status' => CommissionStatus::ENTREGADO,
            'total' => 1200.00,
            'date' => $today,
        ]);

        // Comisión entregada ayer
        Commission::factory()->create([
            'cadete_id' => $this->cadete->id,
            'pickup_cadete_id' => $this->cadete->id,
            'client_id' => $this->client->id,
            'destination_id' => $this->destination->id,
            'origin_location_id' => $this->originLocation->id,
            'destination_location_id' => $this->destinationLocation->id,
            'status' => CommissionStatus::ENTREGADO,
            'total' => 800.00,
            'date' => $yesterday,
        ]);

        $response = $this->actingAs($this->cadete)
            ->getJson('/api/cadete/earnings?period=week');

        $response->assertStatus(200);

        $data = $response->json('data');
        $dailyBreakdown = $data['daily_breakdown'];

        // Verificar que hay datos para hoy y ayer
        $todayData = collect($dailyBreakdown)->firstWhere('date', $today->format('Y-m-d'));
        $yesterdayData = collect($dailyBreakdown)->firstWhere('date', $yesterday->format('Y-m-d'));

        $this->assertNotNull($todayData);
        $this->assertEquals(1200.00, $todayData['commission_amount']);
        $this->assertEquals(180.00, $todayData['earnings']); // $1200 * 0.15
        $this->assertEquals(1, $todayData['deliveries']);

        $this->assertNotNull($yesterdayData);
        $this->assertEquals(800.00, $yesterdayData['commission_amount']);
        $this->assertEquals(120.00, $yesterdayData['earnings']); // $800 * 0.15
        $this->assertEquals(1, $yesterdayData['deliveries']);
    }

    /** @test */
    public function earnings_returns_correct_top_routes()
    {
        // Crear destino adicional
        $destination2 = Destination::factory()->create([
            'origin' => 'Palermo',
            'destination' => 'Belgrano',
        ]);

        // Comisiones en ruta CABA → Zona Norte
        Commission::factory()->create([
            'cadete_id' => $this->cadete->id,
            'pickup_cadete_id' => $this->cadete->id,
            'client_id' => $this->client->id,
            'destination_id' => $this->destination->id,
            'origin_location_id' => $this->originLocation->id,
            'destination_location_id' => $this->destinationLocation->id,
            'status' => CommissionStatus::ENTREGADO,
            'total' => 1000.00,
            'date' => now(),
        ]);

        Commission::factory()->create([
            'cadete_id' => $this->cadete->id,
            'pickup_cadete_id' => $this->cadete->id,
            'client_id' => $this->client->id,
            'destination_id' => $this->destination->id,
            'origin_location_id' => $this->originLocation->id,
            'destination_location_id' => $this->destinationLocation->id,
            'status' => CommissionStatus::ENTREGADO,
            'total' => 800.00,
            'date' => now(),
        ]);

        // Comisión en ruta Palermo → Belgrano
        Commission::factory()->create([
            'cadete_id' => $this->cadete->id,
            'pickup_cadete_id' => $this->cadete->id,
            'client_id' => $this->client->id,
            'destination_id' => $destination2->id,
            'origin_location_id' => $this->originLocation->id,
            'destination_location_id' => $this->destinationLocation->id,
            'status' => CommissionStatus::ENTREGADO,
            'total' => 600.00,
            'date' => now(),
        ]);

        $response = $this->actingAs($this->cadete)
            ->getJson('/api/cadete/earnings?period=month');

        $response->assertStatus(200);

        $data = $response->json('data');
        $topRoutes = $data['top_routes'];

        // La primera ruta debe ser CABA → Zona Norte con mayor ganancia
        $this->assertEquals('CABA → Zona Norte', $topRoutes[0]['route']);
        $this->assertEquals(1800.00, $topRoutes[0]['commission_amount']);
        $this->assertEquals(270.00, $topRoutes[0]['earnings']); // $1800 * 0.15
        $this->assertEquals(2, $topRoutes[0]['deliveries']);
        $this->assertEquals(135.00, $topRoutes[0]['average_per_delivery']); // $270 / 2

        // La segunda ruta debe ser Palermo → Belgrano
        $this->assertEquals('Palermo → Belgrano', $topRoutes[1]['route']);
        $this->assertEquals(600.00, $topRoutes[1]['commission_amount']);
        $this->assertEquals(90.00, $topRoutes[1]['earnings']); // $600 * 0.15
        $this->assertEquals(1, $topRoutes[1]['deliveries']);
    }

    /** @test */
    public function earnings_returns_correct_performance_metrics()
    {
        // Comisión entregada
        Commission::factory()->create([
            'cadete_id' => $this->cadete->id,
            'pickup_cadete_id' => $this->cadete->id,
            'client_id' => $this->client->id,
            'destination_id' => $this->destination->id,
            'origin_location_id' => $this->originLocation->id,
            'destination_location_id' => $this->destinationLocation->id,
            'status' => CommissionStatus::ENTREGADO,
            'total' => 1000.00,
            'date' => now(),
        ]);

        // Comisión cancelada
        Commission::factory()->create([
            'cadete_id' => $this->cadete->id,
            'pickup_cadete_id' => $this->cadete->id,
            'client_id' => $this->client->id,
            'destination_id' => $this->destination->id,
            'origin_location_id' => $this->originLocation->id,
            'destination_location_id' => $this->destinationLocation->id,
            'status' => CommissionStatus::CANCELADO,
            'total' => 500.00,
            'date' => now(),
        ]);

        $response = $this->actingAs($this->cadete)
            ->getJson('/api/cadete/earnings?period=month');

        $response->assertStatus(200);

        $data = $response->json('data');
        $performanceMetrics = $data['performance_metrics'];

        // Tasa de éxito debe ser 50% (1 de 2 entregas completadas)
        $this->assertEquals(50.0, $performanceMetrics['delivery_success_rate']);

        // Rating y porcentaje de tiempo son placeholders por ahora
        $this->assertEquals(4.8, $performanceMetrics['customer_rating']);
        $this->assertEquals(87.5, $performanceMetrics['on_time_percentage']);
    }

    /** @test */
    public function earnings_returns_correct_recent_payments_with_pagination()
    {
        // Crear varias comisiones entregadas
        for ($i = 1; $i <= 5; $i++) {
            Commission::factory()->create([
                'cadete_id' => $this->cadete->id,
            'pickup_cadete_id' => $this->cadete->id,
                'client_id' => $this->client->id,
                'destination_id' => $this->destination->id,
                'origin_location_id' => $this->originLocation->id,
                'destination_location_id' => $this->destinationLocation->id,
                'status' => CommissionStatus::ENTREGADO,
                'total' => 1000.00 * $i,
                'date' => now()->subDays($i),
            ]);
        }

        $response = $this->actingAs($this->cadete)
            ->getJson('/api/cadete/earnings?period=month&page=1&per_page=3');

        $response->assertStatus(200);

        $data = $response->json('data');
        $recentPayments = $data['recent_payments'];
        $pagination = $data['pagination'];

        // Verificar paginación
        $this->assertEquals(1, $pagination['current_page']);
        $this->assertEquals(3, $pagination['per_page']);
        $this->assertEquals(5, $pagination['total']);
        $this->assertEquals(2, $pagination['last_page']);
        $this->assertEquals(1, $pagination['from']);
        $this->assertEquals(3, $pagination['to']);

        // Verificar que solo se devuelven 3 pagos
        $this->assertCount(3, $recentPayments);

        // Verificar estructura de pagos
        $this->assertArrayHasKey('id', $recentPayments[0]);
        $this->assertArrayHasKey('commission_amount', $recentPayments[0]);
        $this->assertArrayHasKey('earnings', $recentPayments[0]);
        $this->assertArrayHasKey('method', $recentPayments[0]);
        $this->assertArrayHasKey('status', $recentPayments[0]);
        $this->assertArrayHasKey('reference', $recentPayments[0]);
    }

    /** @test */
    public function earnings_requires_authentication()
    {
        $response = $this->getJson('/api/cadete/earnings');

        $response->assertStatus(401);
    }

    /** @test */
    public function earnings_requires_cadete_role()
    {
        $adminUser = User::factory()->create(['role' => UserRole::ADMINISTRADOR]);

        $response = $this->actingAs($adminUser)
            ->getJson('/api/cadete/earnings');

        $response->assertStatus(403);
    }

    /** @test */
    public function earnings_validates_custom_period_dates()
    {
        $response = $this->actingAs($this->cadete)
            ->getJson('/api/cadete/earnings?period=custom&date_from=2025-01-01');

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['date_to']);
    }

    /** @test */
    public function earnings_validates_date_range()
    {
        $response = $this->actingAs($this->cadete)
            ->getJson('/api/cadete/earnings?period=custom&date_from=2025-01-31&date_to=2025-01-01');

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['date_to']);
    }

    /** @test */
    public function earnings_validates_period_values()
    {
        $response = $this->actingAs($this->cadete)
            ->getJson('/api/cadete/earnings?period=invalid_period');

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['period']);
    }

    /** @test */
    public function earnings_validates_status_values()
    {
        $response = $this->actingAs($this->cadete)
            ->getJson('/api/cadete/earnings?status=invalid_status');

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['status']);
    }

    /** @test */
    public function earnings_validates_pagination_parameters()
    {
        $response = $this->actingAs($this->cadete)
            ->getJson('/api/cadete/earnings?page=0&per_page=0');

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['page', 'per_page']);
    }

    /** @test */
    public function earnings_returns_empty_data_when_no_commissions()
    {
        $response = $this->actingAs($this->cadete)
            ->getJson('/api/cadete/earnings?period=month');

        $response->assertStatus(200);

        $data = $response->json('data');

        $this->assertEquals(0, $data['summary']['total_earnings']);
        $this->assertEquals(0, $data['summary']['total_deliveries']);
        $this->assertEmpty($data['recent_payments']);
        $this->assertEmpty($data['top_routes']);
    }

    /** @test */
    public function earnings_calculates_correctly_with_cadete_commission_percentage()
    {
        // Establecer el porcentaje de comisión del cadete
        $this->cadete->update(['commission_percentage' => 25.0]); // 25%

        // Crear comisión entregada con total de $1000
        Commission::factory()->create([
            'cadete_id' => $this->cadete->id,
            'pickup_cadete_id' => $this->cadete->id,
            'client_id' => $this->client->id,
            'destination_id' => $this->destination->id,
            'origin_location_id' => $this->originLocation->id,
            'destination_location_id' => $this->destinationLocation->id,
            'status' => CommissionStatus::ENTREGADO,
            'total' => 1000.00,
            'date' => now(),
        ]);

        $response = $this->actingAs($this->cadete)
            ->getJson('/api/cadete/earnings?period=month');

        $response->assertStatus(200);

        $data = $response->json('data');

        // Verificar que se incluye información del porcentaje de comisión
        $this->assertArrayHasKey('commission_info', $data);
        $this->assertEquals(25.0, $data['commission_info']['cadete_commission_percentage']);
        $this->assertStringContainsString('25.00%', $data['commission_info']['explanation']);

        // Verificar cálculos correctos
        $summary = $data['summary'];
        $this->assertEquals(1000.00, $summary['total_commission_amount']); // Total de la comisión
        $this->assertEquals(250.00, $summary['total_earnings']); // 25% de $1000
        $this->assertEquals(25.0, $summary['commission_percentage']);

        // Verificar breakdown
        $breakdown = $data['breakdown'];
        $this->assertEquals(1000.00, $breakdown['total_commission_amount']);
        $this->assertEquals(250.00, $breakdown['cash_earnings']); // 25% de $1000
        $this->assertEquals(25.0, $breakdown['commission_percentage']);

        // Verificar payment status
        $paymentStatus = $data['payment_status'];
        $this->assertEquals(1000.00, $paymentStatus['paid']['commission_amount']);
        $this->assertEquals(250.00, $paymentStatus['paid']['earnings']);

        // Verificar daily breakdown
        $dailyBreakdown = $data['daily_breakdown'];
        $todayData = collect($dailyBreakdown)->firstWhere('date', now()->format('Y-m-d'));
        $this->assertNotNull($todayData);
        $this->assertEquals(1000.00, $todayData['commission_amount']);
        $this->assertEquals(250.00, $todayData['earnings']);

        // Verificar top routes
        $topRoutes = $data['top_routes'];
        $this->assertNotEmpty($topRoutes);
        $this->assertEquals(1000.00, $topRoutes[0]['commission_amount']);
        $this->assertEquals(250.00, $topRoutes[0]['earnings']);

        // Verificar recent payments
        $recentPayments = $data['recent_payments'];
        $this->assertNotEmpty($recentPayments);
        $this->assertEquals(1000.00, $recentPayments[0]['commission_amount']);
        $this->assertEquals(250.00, $recentPayments[0]['earnings']);
    }

    /** @test */
    public function earnings_handles_zero_commission_percentage()
    {
        // Establecer el porcentaje de comisión del cadete en 0
        $this->cadete->update(['commission_percentage' => 0.0]);

        // Crear comisión entregada
        Commission::factory()->create([
            'cadete_id' => $this->cadete->id,
            'pickup_cadete_id' => $this->cadete->id,
            'client_id' => $this->client->id,
            'destination_id' => $this->destination->id,
            'origin_location_id' => $this->originLocation->id,
            'destination_location_id' => $this->destinationLocation->id,
            'status' => CommissionStatus::ENTREGADO,
            'total' => 1000.00,
            'date' => now(),
        ]);

        $response = $this->actingAs($this->cadete)
            ->getJson('/api/cadete/earnings?period=month');

        $response->assertStatus(200);

        $data = $response->json('data');

        // Verificar que las ganancias son 0
        $this->assertEquals(0.0, $data['summary']['total_earnings']);
        $this->assertEquals(1000.00, $data['summary']['total_commission_amount']);
        $this->assertEquals(0.0, $data['summary']['commission_percentage']);
    }

    /** @test */
    public function earnings_handles_null_commission_percentage()
    {
        // Establecer el porcentaje de comisión del cadete en null
        $this->cadete->update(['commission_percentage' => null]);

        // Crear comisión entregada
        Commission::factory()->create([
            'cadete_id' => $this->cadete->id,
            'pickup_cadete_id' => $this->cadete->id,
            'client_id' => $this->client->id,
            'destination_id' => $this->destination->id,
            'origin_location_id' => $this->originLocation->id,
            'destination_location_id' => $this->destinationLocation->id,
            'status' => CommissionStatus::ENTREGADO,
            'total' => 1000.00,
            'date' => now(),
        ]);

        $response = $this->actingAs($this->cadete)
            ->getJson('/api/cadete/earnings?period=month');

        $response->assertStatus(200);

        $data = $response->json('data');

        // Verificar que las ganancias son 0 cuando no hay porcentaje definido
        $this->assertEquals(0.0, $data['summary']['total_earnings']);
        $this->assertEquals(1000.00, $data['summary']['total_commission_amount']);
        $this->assertEquals(0.0, $data['summary']['commission_percentage']);
    }
}
