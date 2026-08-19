<?php

namespace Tests\Feature;

use App\Services\ChatbotService;
use App\Shared\Enums\CommissionStatus;
use App\Shared\Models\Branch;
use App\Shared\Models\ChatbotFaq;
use App\Shared\Models\Commission;
use App\Shared\Models\Customer;
use App\Shared\Models\Destination;
use App\Shared\Models\Location;
use App\Shared\Models\User;
use Database\Seeders\ChatbotFaqSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * RC-490 (CHATBOT BASICO).
 *
 * La card pedía responder preguntas frecuentes y dar el estado de una comisión por
 * número de envío. No existía nada: cero líneas en el proyecto.
 *
 * El canal es público y anónimo, así que la respuesta de estado no puede filtrar
 * datos personales (direcciones, teléfonos, notas ni nombres de empleados).
 */
class ChatbotTest extends TestCase
{
    use RefreshDatabase;

    private ChatbotService $chatbot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->chatbot = app(ChatbotService::class);
        $this->seed(ChatbotFaqSeeder::class);
    }

    private function comision(array $attrs = []): Commission
    {
        $branch = Branch::factory()->create();

        // Id realista: en producción los números de envío tienen 5 dígitos, y el
        // chatbot exige al menos 3 para no confundir un horario con un envío.
        return Commission::factory()->create($attrs + [
            'id' => 53620,
            'client_id' => Customer::factory()->create()->id,
            'destination_id' => Destination::factory()->create()->id,
            'branch_id' => $branch->id,
            'user_id' => User::factory()->create(['role' => 'administrador', 'branch_id' => $branch->id])->id,
            'origin_location_id' => Location::factory()->create(['origin' => 'ROSARIO'])->id,
            'destination_location_id' => Location::factory()->create(['origin' => 'SAN GENARO'])->id,
            'status' => CommissionStatus::EN_TRANSITO_DESTINO->value,
            'notes' => 'RETIRAR A NOMBRE DE JUAN CARBALLO',
        ]);
    }

    // --- Estado de envío ---

    public function test_returns_status_for_a_shipment_number(): void
    {
        $c = $this->comision();

        $r = $this->chatbot->handle((string) $c->id);

        $this->assertSame('estado_envio', $r['type']);
        $this->assertStringContainsString("Envío #{$c->id}", $r['reply']);
        $this->assertStringContainsString('En tránsito a destino', $r['reply']);
    }

    public function test_finds_the_number_inside_a_sentence(): void
    {
        $c = $this->comision();

        $r = $this->chatbot->handle("hola, donde esta mi envio {$c->id}? gracias");

        $this->assertSame('estado_envio', $r['type']);
        $this->assertSame($c->id, $r['commission_id']);
    }

    public function test_status_answer_does_not_leak_personal_data(): void
    {
        $c = $this->comision();

        $r = $this->chatbot->handle((string) $c->id);

        // Ni las notas (que suelen traer nombres de personas) ni direcciones.
        $this->assertStringNotContainsString('CARBALLO', $r['reply']);
        $this->assertStringNotContainsString($c->originLocation->address, $r['reply']);
    }

    public function test_unknown_shipment_number_is_reported(): void
    {
        $r = $this->chatbot->handle('999999');

        $this->assertSame('envio_no_encontrado', $r['type']);
    }

    // --- FAQs ---

    public function test_answers_a_frequent_question(): void
    {
        $r = $this->chatbot->handle('cuanto tarda una entrega?');

        $this->assertSame('faq', $r['type']);
        $this->assertStringContainsString('24 a 48 horas', $r['reply']);
    }

    public function test_matching_ignores_accents_and_case(): void
    {
        $r = $this->chatbot->handle('¿CUÁNTO TARDA?');

        $this->assertSame('faq', $r['type']);
        $this->assertStringContainsString('horas hábiles', $r['reply']);
    }

    public function test_payment_question_is_matched(): void
    {
        $r = $this->chatbot->handle('que formas de pago aceptan');

        $this->assertSame('faq', $r['type']);
        $this->assertStringContainsString('transferencia', $r['reply']);
    }

    public function test_longest_keyword_wins_over_a_loose_word(): void
    {
        // "cuenta corriente" tiene que ganarle a cualquier coincidencia suelta.
        $r = $this->chatbot->handle('quiero ver mi cuenta corriente');

        $this->assertStringContainsString('Portal del Cliente', $r['reply']);
    }

    public function test_faq_hits_are_counted(): void
    {
        $antes = ChatbotFaq::where('category', 'pagos')->sum('hits');

        $this->chatbot->handle('formas de pago');

        $this->assertGreaterThan($antes, ChatbotFaq::where('category', 'pagos')->sum('hits'));
    }

    public function test_inactive_faq_is_not_used(): void
    {
        ChatbotFaq::query()->update(['is_active' => false]);

        $this->assertSame('sin_coincidencia', $this->chatbot->handle('cuanto tarda una entrega')['type']);
    }

    public function test_unmatched_message_offers_suggestions(): void
    {
        $r = $this->chatbot->handle('asdfgh qwerty');

        $this->assertSame('sin_coincidencia', $r['type']);
        $this->assertNotEmpty($r['suggestions']);
    }

    public function test_empty_message_is_handled(): void
    {
        $this->assertSame('vacio', $this->chatbot->handle('   ')['type']);
    }

    public function test_short_numbers_do_not_look_like_shipments(): void
    {
        // "8 a 12" es un horario, no un número de envío.
        $r = $this->chatbot->handle('atienden de 8 a 12?');

        $this->assertNotSame('estado_envio', $r['type']);
    }

    // --- API ---

    public function test_public_endpoint_answers_without_authentication(): void
    {
        $this->postJson('/api/chatbot/message', ['message' => 'cuanto tarda una entrega'])
            ->assertOk()
            ->assertJsonPath('data.type', 'faq');
    }

    public function test_public_endpoint_requires_a_message(): void
    {
        $this->postJson('/api/chatbot/message', [])->assertStatus(422);
    }

    public function test_suggestions_endpoint_is_public(): void
    {
        $this->getJson('/api/chatbot/suggestions')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'question']]]);
    }

    public function test_admin_can_create_a_faq(): void
    {
        $admin = User::factory()->create(['role' => 'administrador', 'branch_id' => Branch::factory()->create()->id]);

        $this->actingAs($admin)->postJson('/api/admin/chatbot/faqs', [
            'question' => '¿Entregan los domingos?',
            'answer' => 'No, los domingos no hay reparto.',
            'keywords' => 'domingo,domingos,fin de semana',
        ])->assertCreated();

        $this->assertSame('faq', $this->chatbot->handle('entregan los domingos?')['type']);
    }

    public function test_faq_abm_requires_admin(): void
    {
        $this->getJson('/api/admin/chatbot/faqs')->assertStatus(401);
    }
}
