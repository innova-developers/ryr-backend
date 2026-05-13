<?php

namespace Tests\Unit\Services;

use App\Services\ScheduleNormalizer\ScheduleNormalizerService;
use Tests\TestCase;

/**
 * Test comprehensivo para validar múltiples formatos de horarios comerciales
 *
 * Este test cubre:
 * - Formatos simples con regex
 * - Formatos complejos que pueden requerir IA
 * - Casos edge
 * - Validación de normalización correcta
 */
class ScheduleNormalizerComprehensiveTest extends TestCase
{
    private ScheduleNormalizerService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ScheduleNormalizerService();
    }

    /**
     * Test de formatos básicos que deberían parsearse con regex
     */
    public function test_basic_formats_with_regex(): void
    {
        $testCases = [
            // Formato simple sin minutos
            [
                'input' => '8 a 12',
                'expected_ranges' => [
                    ['start' => '08:00', 'end' => '12:00', 'confidence' => 'high'],
                ],
            ],
            [
                'input' => '8-12',
                'expected_ranges' => [
                    ['start' => '08:00', 'end' => '12:00', 'confidence' => 'high'],
                ],
            ],
            [
                'input' => '8a12',
                'expected_ranges' => [
                    ['start' => '08:00', 'end' => '12:00', 'confidence' => 'high'],
                ],
            ],

            // Formato con minutos
            [
                'input' => '08:30-12:00',
                'expected_ranges' => [
                    ['start' => '08:30', 'end' => '12:00', 'confidence' => 'high'],
                ],
            ],
            [
                'input' => '8:30 a 12:00',
                'expected_ranges' => [
                    ['start' => '08:30', 'end' => '12:00', 'confidence' => 'high'],
                ],
            ],
            [
                'input' => '04:30-07:30',
                'expected_ranges' => [
                    ['start' => '04:30', 'end' => '07:30', 'confidence' => 'high'],
                ],
            ],

            // Múltiples rangos con "y"
            [
                'input' => '8a12 y 15 a 20',
                'expected_ranges' => [
                    ['start' => '08:00', 'end' => '12:00', 'confidence' => 'high'],
                    ['start' => '15:00', 'end' => '20:00', 'confidence' => 'high'],
                ],
            ],

            // Múltiples rangos con "/"
            [
                'input' => '8 a 12 / 15 a 20',
                'expected_ranges' => [
                    ['start' => '08:00', 'end' => '12:00', 'confidence' => 'high'],
                    ['start' => '15:00', 'end' => '20:00', 'confidence' => 'high'],
                ],
            ],

            // Múltiples rangos con coma
            [
                'input' => '8:00-12:00, 15:00-20:00',
                'expected_ranges' => [
                    ['start' => '08:00', 'end' => '12:00', 'confidence' => 'high'],
                    ['start' => '15:00', 'end' => '20:00', 'confidence' => 'high'],
                ],
            ],

            // Horarios con ceros a la izquierda
            [
                'input' => '09:00-18:00',
                'expected_ranges' => [
                    ['start' => '09:00', 'end' => '18:00', 'confidence' => 'high'],
                ],
            ],
        ];

        foreach ($testCases as $testCase) {
            $result = $this->service->normalize($testCase['input']);

            $this->assertArrayHasKey('ranges', $result, "Failed for input: {$testCase['input']}");
            $this->assertCount(
                count($testCase['expected_ranges']),
                $result['ranges'],
                "Expected " . count($testCase['expected_ranges']) . " ranges for: {$testCase['input']}"
            );

            foreach ($testCase['expected_ranges'] as $index => $expectedRange) {
                $this->assertArrayHasKey($index, $result['ranges'], "Missing range at index {$index} for: {$testCase['input']}");
                $actualRange = $result['ranges'][$index];

                $this->assertEquals(
                    $expectedRange['start'],
                    $actualRange['start'],
                    "Start time mismatch for: {$testCase['input']}"
                );
                $this->assertEquals(
                    $expectedRange['end'],
                    $actualRange['end'],
                    "End time mismatch for: {$testCase['input']}"
                );
                $this->assertEquals(
                    $expectedRange['confidence'],
                    $actualRange['confidence'],
                    "Confidence mismatch for: {$testCase['input']}"
                );
            }
        }
    }

    /**
     * Test de formatos complejos que pueden requerir IA
     */
    public function test_complex_formats_that_may_need_ai(): void
    {
        $testCases = [
            // Formato con texto descriptivo
            [
                'input' => 'Abre 10hs cierra tipo 8 de la noche',
                'description' => 'Formato con texto descriptivo',
            ],

            // Formato con "de la mañana/tarde/noche"
            [
                'input' => '8 de la mañana a 12 del mediodía',
                'description' => 'Formato con referencias temporales',
            ],

            // Formato con "hs"
            [
                'input' => 'Abre 10hs cierra 20hs',
                'description' => 'Formato con "hs"',
            ],

            // Formato con múltiples espacios y texto
            [
                'input' => 'Lunes a viernes: 9 a 18, Sábados: 9 a 13',
                'description' => 'Formato con días de semana',
            ],

            // Formato con "hasta"
            [
                'input' => 'Abre a las 8 hasta las 12 y de 15 hasta 20',
                'description' => 'Formato con "hasta"',
            ],

            // Formato con "desde"
            [
                'input' => 'Desde las 9 hasta las 18',
                'description' => 'Formato con "desde"',
            ],

            // Formato con "pm" o "am"
            [
                'input' => '8am a 12pm y 3pm a 8pm',
                'description' => 'Formato con am/pm',
            ],

            // Formato con texto adicional
            [
                'input' => 'Horario comercial: 9:00 a 18:00, cerrado al mediodía de 13 a 14',
                'description' => 'Formato con texto adicional y pausa',
            ],

            // Formato con "tarde" y "noche"
            [
                'input' => 'Mañana 8 a 12, tarde 15 a 20',
                'description' => 'Formato con referencias de parte del día',
            ],
        ];

        foreach ($testCases as $testCase) {
            $result = $this->service->normalize($testCase['input']);

            $this->assertArrayHasKey('ranges', $result, "Failed for: {$testCase['description']}");
            $this->assertArrayHasKey('raw', $result);
            $this->assertEquals($testCase['input'], $result['raw']);

            // Verificar que cada rango tiene la estructura correcta
            foreach ($result['ranges'] as $range) {
                $this->assertArrayHasKey('start', $range);
                $this->assertArrayHasKey('end', $range);
                $this->assertArrayHasKey('confidence', $range);
                $this->assertContains($range['confidence'], ['high', 'medium', 'low']);

                // Validar formato HH:MM
                $this->assertMatchesRegularExpression('/^\d{2}:\d{2}$/', $range['start']);
                $this->assertMatchesRegularExpression('/^\d{2}:\d{2}$/', $range['end']);

                // Validar que start < end (o manejar casos especiales)
                $startParts = explode(':', $range['start']);
                $endParts = explode(':', $range['end']);
                $startMinutes = (int) $startParts[0] * 60 + (int) $startParts[1];
                $endMinutes = (int) $endParts[0] * 60 + (int) $endParts[1];

                $this->assertLessThan(
                    $endMinutes,
                    $startMinutes,
                    "Start time should be before end time for: {$testCase['input']}"
                );
            }
        }
    }

    /**
     * Test de casos edge y límites
     */
    public function test_edge_cases(): void
    {
        $testCases = [
            // String vacío
            [
                'input' => '',
                'expected_range_count' => 0,
            ],

            // Solo espacios
            [
                'input' => '   ',
                'expected_range_count' => 0,
            ],

            // Horario de medianoche
            [
                'input' => '00:00-23:59',
                'expected_range_count' => 1,
            ],

            // Horario que cruza medianoche (caso especial)
            [
                'input' => '22:00-02:00',
                'description' => 'Horario que cruza medianoche',
            ],

            // Solo una hora mencionada
            [
                'input' => 'Abre a las 9',
                'description' => 'Solo hora de apertura',
            ],

            // Horarios con muchos espacios
            [
                'input' => '8    a    12',
                'expected_range_count' => 1,
            ],

            // Horarios con caracteres especiales
            [
                'input' => '8:00 a.m. - 12:00 p.m.',
                'description' => 'Formato con puntos en am/pm',
            ],

            // Horarios en formato 24h con texto
            [
                'input' => 'De 08:00 horas hasta las 20:00 horas',
                'description' => 'Formato con "horas"',
            ],
        ];

        foreach ($testCases as $testCase) {
            $result = $this->service->normalize($testCase['input']);

            $this->assertArrayHasKey('ranges', $result);
            $this->assertArrayHasKey('raw', $result);
            $this->assertArrayHasKey('normalized_at', $result);

            if (isset($testCase['expected_range_count'])) {
                $this->assertCount(
                    $testCase['expected_range_count'],
                    $result['ranges'],
                    "Failed for: {$testCase['input']}"
                );
            }

            // Validar estructura de rangos si existen
            foreach ($result['ranges'] as $range) {
                $this->assertArrayHasKey('start', $range);
                $this->assertArrayHasKey('end', $range);
                $this->assertArrayHasKey('confidence', $range);
            }
        }
    }

    /**
     * Test de formatos internacionales
     */
    public function test_international_formats(): void
    {
        $testCases = [
            // Formato europeo con punto
            [
                'input' => '8.00-12.00',
                'description' => 'Formato europeo con punto',
            ],

            // Formato con "h" (francés/alemán)
            [
                'input' => '8h00-12h00',
                'description' => 'Formato con "h"',
            ],

            // Formato con espacio entre hora y minuto
            [
                'input' => '8 00 - 12 00',
                'description' => 'Formato con espacio',
            ],
        ];

        foreach ($testCases as $testCase) {
            $result = $this->service->normalize($testCase['input']);

            $this->assertArrayHasKey('ranges', $result);
            $this->assertIsArray($result['ranges']);
        }
    }

    /**
     * Test de validación de estructura de respuesta
     */
    public function test_response_structure(): void
    {
        $result = $this->service->normalize('8 a 12 y 15 a 20');

        // Validar estructura principal
        $this->assertArrayHasKey('ranges', $result);
        $this->assertArrayHasKey('raw', $result);
        $this->assertArrayHasKey('normalized_at', $result);

        // Validar que ranges es un array
        $this->assertIsArray($result['ranges']);

        // Validar que normalized_at es un string ISO válido
        $this->assertIsString($result['normalized_at']);
        $this->assertNotEmpty($result['normalized_at']);

        // Validar estructura de cada rango
        foreach ($result['ranges'] as $range) {
            $this->assertIsArray($range);
            $this->assertArrayHasKey('start', $range);
            $this->assertArrayHasKey('end', $range);
            $this->assertArrayHasKey('confidence', $range);

            // Validar tipos
            $this->assertIsString($range['start']);
            $this->assertIsString($range['end']);
            $this->assertIsString($range['confidence']);

            // Validar formato de tiempo
            $this->assertMatchesRegularExpression('/^\d{2}:\d{2}$/', $range['start']);
            $this->assertMatchesRegularExpression('/^\d{2}:\d{2}$/', $range['end']);

            // Validar confidence
            $this->assertContains($range['confidence'], ['high', 'medium', 'low']);
        }
    }

    /**
     * Test de método closesSoon
     */
    public function test_closes_soon_method(): void
    {
        // Crear rangos que cierran en diferentes momentos
        $ranges = [
            [
                'start' => '08:00',
                'end' => '20:00',
                'confidence' => 'high',
            ],
        ];

        // Este test depende de la hora actual, así que solo validamos la estructura
        $result = $this->service->closesSoon($ranges, 15);
        $this->assertIsBool($result);

        // Test con array vacío
        $this->assertFalse($this->service->closesSoon([], 15));

        // Test con múltiples rangos
        $multipleRanges = [
            ['start' => '08:00', 'end' => '12:00', 'confidence' => 'high'],
            ['start' => '15:00', 'end' => '20:00', 'confidence' => 'high'],
        ];
        $result2 = $this->service->closesSoon($multipleRanges, 15);
        $this->assertIsBool($result2);
    }

    /**
     * Test de persistencia de datos (validar que el formato es adecuado para BD)
     */
    public function test_data_persistence_format(): void
    {
        $result = $this->service->normalize('8 a 12 y 15 a 20');

        // El resultado debe ser serializable a JSON (requisito para BD)
        $json = json_encode($result);
        $this->assertNotFalse($json, 'Result should be JSON serializable');

        // Debe poder decodificarse correctamente
        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('ranges', $decoded);
        $this->assertArrayHasKey('raw', $decoded);
        $this->assertArrayHasKey('normalized_at', $decoded);
    }
}
