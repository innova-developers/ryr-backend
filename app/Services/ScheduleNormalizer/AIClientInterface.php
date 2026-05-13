<?php

namespace App\Services\ScheduleNormalizer;

/**
 * Interfaz para clientes de IA que procesan horarios comerciales
 */
interface AIClientInterface
{
    /**
     * Parsea un horario comercial usando IA
     *
     * @param string $rawSchedule Horario en formato libre
     * @return array Array con estructura ['ranges' => [...], 'confidence' => 'medium|low']
     */
    public function parseSchedule(string $rawSchedule): array;
}
