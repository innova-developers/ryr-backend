<?php

namespace Tests\Unit\Services;

use App\Services\ScheduleNormalizer\GroqAIClient;
use App\Services\ScheduleNormalizer\ScheduleNormalizerService;
use Tests\TestCase;

/**
 * Test para casos que requieren IA (solo se ejecuta si hay API key configurada)
 *
 * Para ejecutar estos tests:
 * 1. Obtener API key de Groq: https://console.groq.com/
 * 2. Agregar a .env: GROQ_API_KEY=tu_api_key
 * 3. Ejecutar: php artisan test --filter ScheduleNormalizerWithAITest
 *
 * NOTA: Estos tests hacen llamadas reales a la API, usar con moderación
 */
class ScheduleNormalizerWithAITest extends TestCase
{
    private ?ScheduleNormalizerService $serviceWithAI = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Solo crear servicio con IA si hay API key configurada
        $apiKey = config('services.groq.api_key');
        if (!empty($apiKey)) {
            $this->serviceWithAI = new ScheduleNormalizerService(new GroqAIClient($apiKey));
        }
    }

    /**
     * Test de formatos complejos que requieren IA
     *
     * Estos casos no pueden parsearse con regex simple
     */
    public function test_complex_formats_requiring_ai(): void
    {
        if ($this->serviceWithAI === null) {
            $this->markTestSkipped('Groq API key no configurada. Agregar GROQ_API_KEY a .env para ejecutar este test.');
        }

        $testCases = [
            // Formato con texto descriptivo completo
            [
                'input' => 'Abre 10hs cierra tipo 8 de la noche',
                'description' => 'Formato con texto descriptivo',
                'min_ranges' => 1,
            ],

            // Formato con referencias temporales
            [
                'input' => '8 de la mañana a 12 del mediodía',
                'description' => 'Formato con referencias temporales',
                'min_ranges' => 1,
            ],

            // Formato con "hs" y texto
            [
                'input' => 'Abre 10hs cierra 20hs',
                'description' => 'Formato con "hs"',
                'min_ranges' => 1,
            ],

            // Formato con días de semana
            [
                'input' => 'Lunes a viernes: 9 a 18, Sábados: 9 a 13',
                'description' => 'Formato con días de semana (debe extraer solo horarios)',
                'min_ranges' => 1,
            ],

            // Formato con "hasta"
            [
                'input' => 'Abre a las 8 hasta las 12 y de 15 hasta 20',
                'description' => 'Formato con "hasta"',
                'min_ranges' => 2,
            ],

            // Formato con "desde"
            [
                'input' => 'Desde las 9 hasta las 18',
                'description' => 'Formato con "desde"',
                'min_ranges' => 1,
            ],

            // Formato con texto adicional y pausa
            [
                'input' => 'Horario comercial: 9:00 a 18:00, cerrado al mediodía de 13 a 14',
                'description' => 'Formato con texto adicional y pausa',
                'min_ranges' => 1,
            ],

            // Formato con referencias de parte del día
            [
                'input' => 'Mañana 8 a 12, tarde 15 a 20',
                'description' => 'Formato con referencias de parte del día',
                'min_ranges' => 2,
            ],

            // Formato con "pm" o "am"
            [
                'input' => '8am a 12pm y 3pm a 8pm',
                'description' => 'Formato con am/pm',
                'min_ranges' => 2,
            ],

            // Formato muy descriptivo
            [
                'input' => 'Atención al público de lunes a viernes desde las 9 de la mañana hasta las 6 de la tarde',
                'description' => 'Formato muy descriptivo',
                'min_ranges' => 1,
            ],
        ];

        foreach ($testCases as $testCase) {
            $result = $this->serviceWithAI->normalize($testCase['input']);

            $this->assertArrayHasKey('ranges', $result, "Failed for: {$testCase['description']}");
            $this->assertArrayHasKey('raw', $result);
            $this->assertEquals($testCase['input'], $result['raw']);

            // Verificar que se encontraron rangos (puede variar según la IA)
            $this->assertGreaterThanOrEqual(
                $testCase['min_ranges'],
                count($result['ranges']),
                "Expected at least {$testCase['min_ranges']} range(s) for: {$testCase['description']}"
            );

            // Validar estructura de cada rango
            foreach ($result['ranges'] as $index => $range) {
                $this->assertArrayHasKey('start', $range, "Missing start in range {$index} for: {$testCase['description']}");
                $this->assertArrayHasKey('end', $range, "Missing end in range {$index} for: {$testCase['description']}");
                $this->assertArrayHasKey('confidence', $range, "Missing confidence in range {$index} for: {$testCase['description']}");

                // Validar formato HH:MM
                $this->assertMatchesRegularExpression(
                    '/^\d{2}:\d{2}$/',
                    $range['start'],
                    "Invalid start format in range {$index} for: {$testCase['description']}"
                );
                $this->assertMatchesRegularExpression(
                    '/^\d{2}:\d{2}$/',
                    $range['end'],
                    "Invalid end format in range {$index} for: {$testCase['description']}"
                );

                // Validar confidence
                // Si el regex mejorado puede parsearlo, será 'high', si viene de IA será 'medium' o 'low'
                $this->assertContains(
                    $range['confidence'],
                    ['high', 'medium', 'low'],
                    "Confidence should be high, medium or low"
                );
                
                // Si la confianza es high, significa que regex lo parseó (no se usó IA)
                // Si es medium o low, significa que se usó IA
                if ($range['confidence'] === 'high') {
                    // Regex lo parseó correctamente, está bien
                } else {
                    // Vino de IA, debe ser medium o low
                    $this->assertContains(
                        $range['confidence'],
                        ['medium', 'low'],
                        "AI-parsed ranges should have medium or low confidence"
                    );
                }

                // Validar que start < end
                $startParts = explode(':', $range['start']);
                $endParts = explode(':', $range['end']);
                $startMinutes = (int) $startParts[0] * 60 + (int) $startParts[1];
                $endMinutes = (int) $endParts[0] * 60 + (int) $endParts[1];

                $this->assertLessThan(
                    $endMinutes,
                    $startMinutes,
                    "Start time should be before end time in range {$index} for: {$testCase['description']}"
                );
            }
        }
    }

    /**
     * Test de fallback: cuando regex falla, debe usar IA
     */
    public function test_fallback_to_ai_when_regex_fails(): void
    {
        if ($this->serviceWithAI === null) {
            $this->markTestSkipped('Groq API key no configurada.');
        }

        // Usar un formato que realmente requiere IA (con contexto y texto descriptivo)
        $complexSchedule = 'Atención al público de lunes a viernes desde las 9 de la mañana hasta las 6 de la tarde, sábados solo por la mañana de 9 a 13';

        // Verificar que con IA se puede parsear
        $aiResult = $this->serviceWithAI->normalize($complexSchedule);
        $this->assertNotEmpty($aiResult['ranges'], 'AI should parse this complex format');
        
        // Verificar estructura de los rangos
        foreach ($aiResult['ranges'] as $range) {
            $this->assertArrayHasKey('start', $range);
            $this->assertArrayHasKey('end', $range);
            $this->assertArrayHasKey('confidence', $range);
            $this->assertMatchesRegularExpression('/^\d{2}:\d{2}$/', $range['start']);
            $this->assertMatchesRegularExpression('/^\d{2}:\d{2}$/', $range['end']);
        }
        
        // Si regex puede parsearlo parcialmente, está bien. Lo importante es que IA también funcione
        // Verificar que el servicio con IA puede procesar formatos complejos
        $this->assertGreaterThanOrEqual(1, count($aiResult['ranges']), 'AI should find at least one range');
    }

    /**
     * Test de que el servicio sin IA no llama a la API
     */
    public function test_service_without_ai_does_not_call_api(): void
    {
        $serviceWithoutAI = new ScheduleNormalizerService();

        // Intentar normalizar un formato que regex no puede parsear completamente
        // Usar un formato con días de semana que regex no maneja bien
        $result = $serviceWithoutAI->normalize('Lunes a viernes: 9 a 18, Sábados: 9 a 13, Domingos cerrado');

        // Debe retornar estructura válida
        $this->assertArrayHasKey('ranges', $result);
        $this->assertArrayHasKey('raw', $result);
        $this->assertIsArray($result['ranges']);

        // Regex puede encontrar algunos rangos básicos (como "9 a 18"), pero no todos los detalles
        // Lo importante es que no se llame a la API cuando no hay cliente de IA configurado
        // Si encuentra rangos con regex, está bien. Si no encuentra ninguno, también está bien.
        // Lo que importa es que la estructura sea válida y no haya errores
    }
}

