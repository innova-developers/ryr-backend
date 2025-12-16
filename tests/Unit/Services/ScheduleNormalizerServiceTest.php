<?php

namespace Tests\Unit\Services;

use App\Services\ScheduleNormalizer\ScheduleNormalizerService;
use Carbon\Carbon;
use Tests\TestCase;

class ScheduleNormalizerServiceTest extends TestCase
{
    private ScheduleNormalizerService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ScheduleNormalizerService();
    }

    public function test_normalizes_simple_range_without_minutes(): void
    {
        $result = $this->service->normalize('8 a 12');

        $this->assertArrayHasKey('ranges', $result);
        $this->assertCount(1, $result['ranges']);
        $this->assertEquals('08:00', $result['ranges'][0]['start']);
        $this->assertEquals('12:00', $result['ranges'][0]['end']);
        $this->assertEquals('high', $result['ranges'][0]['confidence']);
    }

    public function test_normalizes_range_with_minutes(): void
    {
        $result = $this->service->normalize('08:30-12:00');

        $this->assertArrayHasKey('ranges', $result);
        $this->assertCount(1, $result['ranges']);
        $this->assertEquals('08:30', $result['ranges'][0]['start']);
        $this->assertEquals('12:00', $result['ranges'][0]['end']);
    }

    public function test_normalizes_multiple_ranges(): void
    {
        $result = $this->service->normalize('8a12 y 15 a 20');

        $this->assertArrayHasKey('ranges', $result);
        $this->assertCount(2, $result['ranges']);
        $this->assertEquals('08:00', $result['ranges'][0]['start']);
        $this->assertEquals('12:00', $result['ranges'][0]['end']);
        $this->assertEquals('15:00', $result['ranges'][1]['start']);
        $this->assertEquals('20:00', $result['ranges'][1]['end']);
    }

    public function test_normalizes_with_slash_separator(): void
    {
        $result = $this->service->normalize('8 a 12 / 15 a 20');

        $this->assertArrayHasKey('ranges', $result);
        $this->assertCount(2, $result['ranges']);
    }

    public function test_closes_soon_detects_closing_time(): void
    {
        $now = Carbon::now();
        $closingTime = $now->copy()->addMinutes(10)->format('H:i');

        $ranges = [
            [
                'start' => '08:00',
                'end' => $closingTime,
                'confidence' => 'high',
            ],
        ];

        $this->assertTrue($this->service->closesSoon($ranges, 15));
    }

    public function test_closes_soon_returns_false_when_not_closing(): void
    {
        $ranges = [
            [
                'start' => '08:00',
                'end' => '20:00',
                'confidence' => 'high',
            ],
        ];

        // Solo retorna true si cierra en los próximos 15 minutos
        // Si estamos lejos del cierre, debe retornar false
        $now = Carbon::now();
        if ($now->format('H:i') < '19:45') {
            $this->assertFalse($this->service->closesSoon($ranges, 15));
        }
    }

    public function test_handles_empty_schedule(): void
    {
        $result = $this->service->normalize('');

        $this->assertArrayHasKey('ranges', $result);
        $this->assertEmpty($result['ranges']);
    }

    public function test_preserves_raw_schedule(): void
    {
        $rawSchedule = '8a12 y 15 a 20';
        $result = $this->service->normalize($rawSchedule);

        $this->assertEquals($rawSchedule, $result['raw']);
        $this->assertArrayHasKey('normalized_at', $result);
    }
}


