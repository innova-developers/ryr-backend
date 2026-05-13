<?php

namespace Tests\Feature\Cadete;

use App\Shared\Enums\CommissionStatus;
use App\Shared\Enums\UserRole;
use App\Shared\Models\Branch;
use App\Shared\Models\Commission;
use App\Shared\Models\Customer;
use App\Shared\Models\Destination;
use App\Shared\Models\Location;
use App\Shared\Models\Transport;
use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CadeteProfileTest extends TestCase
{
    use RefreshDatabase;

    private User $cadete;
    private Transport $transport;
    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        // Crear sucursal
        $this->branch = Branch::factory()->create([
            'name' => 'Sucursal Centro',
            'address' => 'Av. Principal 123',
            'phone' => '011-1234-5678',
            'secondary_phone' => '011-1234-5679',
            'schedule' => 'Lun-Vie 8:00-18:00',
        ]);

        // Crear cadete con información completa
        $this->cadete = User::factory()->create([
            'role' => UserRole::CADETE,
            'branch_id' => $this->branch->id,
            'contract_type' => 'commission_based',
            'base_salary' => 150000.00,
            'commission_percentage' => 15.50,
            'income_percentage' => 80.00,
        ]);

        // Crear transporte asignado al cadete
        $this->transport = Transport::factory()->create([
            'cadete_id' => $this->cadete->id,
            'plate' => 'ABC123',
            'description' => 'Moto Honda CG 150',
            'phone' => '011-9876-5432',
            'insurance' => 'Seguros XYZ',
            'usage' => 'Carga general',
        ]);
    }

    public function test_cadete_can_access_profile()
    {
        $response = $this->actingAs($this->cadete)
                         ->getJson('/api/cadete/profile');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'cadete' => [
                        'id',
                        'name',
                        'email',
                        'role',
                        'role_label',
                        'contract_type',
                        'contract_type_label',
                        'base_salary',
                        'commission_percentage',
                        'income_percentage',
                        'branch',
                        'transport',
                        'stats',
                        'created_at',
                        'last_login',
                    ],
                ]);
    }

    public function test_profile_returns_correct_cadete_information()
    {
        $response = $this->actingAs($this->cadete)
                         ->getJson('/api/cadete/profile');

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'cadete' => [
                        'id' => $this->cadete->id,
                        'name' => $this->cadete->name,
                        'email' => $this->cadete->email,
                        'role' => 'cadete',
                        'role_label' => 'Cadete',
                        'contract_type' => 'commission_based',
                        'contract_type_label' => 'Por Comisión',
                        'base_salary' => '150000.00',
                        'commission_percentage' => '15.50',
                        'income_percentage' => '80.00',
                    ],
                ]);
    }

    public function test_profile_returns_correct_branch_information()
    {
        $response = $this->actingAs($this->cadete)
                         ->getJson('/api/cadete/profile');

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'cadete' => [
                        'branch' => [
                            'id' => $this->branch->id,
                            'name' => 'Sucursal Centro',
                            'address' => 'Av. Principal 123',
                            'phone' => '011-1234-5678',
                            'secondary_phone' => '011-1234-5679',
                            'schedule' => 'Lun-Vie 8:00-18:00',
                        ],
                    ],
                ]);
    }

    public function test_profile_returns_correct_transport_information()
    {
        $response = $this->actingAs($this->cadete)
                         ->getJson('/api/cadete/profile');

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'cadete' => [
                        'transport' => [
                            'id' => $this->transport->id,
                            'plate' => 'ABC123',
                            'description' => 'Moto Honda CG 150',
                            'phone' => '011-9876-5432',
                            'insurance' => 'Seguros XYZ',
                            'usage' => 'Carga general',
                        ],
                    ],
                ]);
    }

    public function test_profile_returns_correct_stats_with_commissions()
    {
        // Crear cliente y ubicaciones para las comisiones
        $client = Customer::factory()->create();
        $originLocation = Location::factory()->create();
        $destinationLocation = Location::factory()->create();
        $destination = Destination::factory()->create();

        // Crear comisiones para el cadete
        Commission::factory()->create([
            'client_id' => $client->id,
            'cadete_id' => $this->cadete->id,
            'branch_id' => $this->branch->id,
            'destination_id' => $destination->id,
            'origin_location_id' => $originLocation->id,
            'destination_location_id' => $destinationLocation->id,
            'status' => CommissionStatus::ENTREGADO,
        ]);

        Commission::factory()->create([
            'client_id' => $client->id,
            'cadete_id' => $this->cadete->id,
            'branch_id' => $this->branch->id,
            'destination_id' => $destination->id,
            'origin_location_id' => $originLocation->id,
            'destination_location_id' => $destinationLocation->id,
            'status' => CommissionStatus::EN_TRANSITO_DESTINO,
        ]);

        $response = $this->actingAs($this->cadete)
                         ->getJson('/api/cadete/profile');

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'cadete' => [
                        'stats' => [
                            'total_commissions' => 2,
                            'completed_commissions' => 1,
                            'success_rate' => 50.0,
                        ],
                    ],
                ]);
    }

    public function test_profile_returns_correct_stats_without_commissions()
    {
        $response = $this->actingAs($this->cadete)
                         ->getJson('/api/cadete/profile');

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'cadete' => [
                        'stats' => [
                            'total_commissions' => 0,
                            'completed_commissions' => 0,
                            'success_rate' => 0,
                        ],
                    ],
                ]);
    }

    public function test_profile_requires_authentication()
    {
        $response = $this->getJson('/api/cadete/profile');
        $response->assertStatus(401);
    }

    public function test_profile_requires_cadete_role()
    {
        $admin = User::factory()->create(['role' => UserRole::ADMINISTRADOR]);

        $response = $this->actingAs($admin)
                         ->getJson('/api/cadete/profile');

        $response->assertStatus(403);
    }

    public function test_profile_without_transport()
    {
        // Crear cadete sin transporte
        $cadeteWithoutTransport = User::factory()->create([
            'role' => UserRole::CADETE,
            'branch_id' => $this->branch->id,
        ]);

        $response = $this->actingAs($cadeteWithoutTransport)
                         ->getJson('/api/cadete/profile');

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'cadete' => [
                        'transport' => null,
                    ],
                ]);
    }

    public function test_profile_without_branch()
    {
        // Crear cadete sin sucursal
        $cadeteWithoutBranch = User::factory()->create([
            'role' => UserRole::CADETE,
            'branch_id' => null,
        ]);

        $response = $this->actingAs($cadeteWithoutBranch)
                         ->getJson('/api/cadete/profile');

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'cadete' => [
                        'branch' => null,
                    ],
                ]);
    }

    public function test_cadete_can_update_profile()
    {
        $response = $this->actingAs($this->cadete)
                         ->putJson('/api/cadete/profile', [
                             'name' => 'Nuevo Nombre',
                             'email' => 'nuevo@email.com',
                         ]);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Perfil actualizado exitosamente',
                    'cadete' => [
                        'name' => 'Nuevo Nombre',
                        'email' => 'nuevo@email.com',
                    ],
                ]);
    }

    public function test_cadete_can_update_only_name()
    {
        $originalEmail = $this->cadete->email;

        $response = $this->actingAs($this->cadete)
                         ->putJson('/api/cadete/profile', [
                             'name' => 'Solo Nombre Cambiado',
                         ]);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Perfil actualizado exitosamente',
                    'cadete' => [
                        'name' => 'Solo Nombre Cambiado',
                        'email' => $originalEmail,
                    ],
                ]);
    }

    public function test_cadete_can_update_only_email()
    {
        $originalName = $this->cadete->name;

        $response = $this->actingAs($this->cadete)
                         ->putJson('/api/cadete/profile', [
                             'email' => 'soloemail@cambiado.com',
                         ]);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Perfil actualizado exitosamente',
                    'cadete' => [
                        'name' => $originalName,
                        'email' => 'soloemail@cambiado.com',
                    ],
                ]);
    }

    public function test_profile_update_validates_email_format()
    {
        $response = $this->actingAs($this->cadete)
                         ->putJson('/api/cadete/profile', [
                             'email' => 'email-invalido',
                         ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['email']);
    }

    public function test_profile_update_validates_email_uniqueness()
    {
        // Crear otro usuario con email diferente
        $otherUser = User::factory()->create([
            'email' => 'otro@email.com',
            'role' => UserRole::CADETE,
        ]);

        $response = $this->actingAs($this->cadete)
                         ->putJson('/api/cadete/profile', [
                             'email' => 'otro@email.com',
                         ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['email']);
    }

    public function test_profile_update_allows_same_email_for_same_user()
    {
        $originalEmail = $this->cadete->email;

        $response = $this->actingAs($this->cadete)
                         ->putJson('/api/cadete/profile', [
                             'email' => $originalEmail,
                         ]);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Perfil actualizado exitosamente',
                ]);
    }

    public function test_profile_update_validates_name_length()
    {
        $longName = str_repeat('a', 256); // Más de 255 caracteres

        $response = $this->actingAs($this->cadete)
                         ->putJson('/api/cadete/profile', [
                             'name' => $longName,
                         ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['name']);
    }

    public function test_profile_update_requires_authentication()
    {
        $response = $this->putJson('/api/cadete/profile', [
            'name' => 'Nuevo Nombre',
        ]);

        $response->assertStatus(401);
    }

    public function test_profile_update_requires_cadete_role()
    {
        $admin = User::factory()->create(['role' => UserRole::ADMINISTRADOR]);

        $response = $this->actingAs($admin)
                         ->putJson('/api/cadete/profile', [
                             'name' => 'Nuevo Nombre',
                         ]);

        $response->assertStatus(403);
    }
}
