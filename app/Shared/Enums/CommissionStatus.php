<?php

namespace App\Shared\Enums;

enum CommissionStatus: string
{
    // Estados internos del sistema (Admin)
    case SOLICITUD_RECIBIDA = 'SOLICITUD_RECIBIDA'; // Equivalente al PRESUPUESTO actual
    case BUSCANDO_CADETE = 'BUSCANDO_CADETE';
    case CADETE_ASIGNADO = 'CADETE_ASIGNADO';
    case CADETE_EN_CAMINO_ORIGEN = 'CADETE_EN_CAMINO_ORIGEN';
    case EN_PUNTO_RETIRO = 'EN_PUNTO_RETIRO';
    case ENCOMIENDA_RETIRADA = 'ENCOMIENDA_RETIRADA';
    case EN_CAMINO_PLANTA = 'EN_CAMINO_PLANTA';
    case EN_TRANSITO_DESTINO = 'EN_TRANSITO_DESTINO';
    case EN_SUCURSAL_DESTINO = 'EN_SUCURSAL_DESTINO';
    case EN_PROCESO_ENTREGA = 'EN_PROCESO_ENTREGA';
    case ENTREGADO = 'ENTREGADO';
    case RETIRADO_SUCURSAL = 'RETIRADO_SUCURSAL';
    
    // Estados de incidencia
    case INTENTO_ENTREGA_FALLIDO = 'INTENTO_ENTREGA_FALLIDO';
    case INTENTO_RETIRO_FALLIDO = 'INTENTO_RETIRO_FALLIDO';
    case REPROGRAMANDO_ENTREGA = 'REPROGRAMANDO_ENTREGA';
    case DISPONIBLE_RETIRO = 'DISPONIBLE_RETIRO';
    case EN_DEVOLUCION = 'EN_DEVOLUCION';
    case DEVUELTO_REMITENTE = 'DEVUELTO_REMITENTE';
    case CANCELADO = 'CANCELADO';
    case EN_ANALISIS = 'EN_ANALISIS';
    
    // Estados de pago (para landing de usuario)
    case PENDIENTE_PAGO = 'PENDIENTE_PAGO';
    case PAGO_VALIDACION = 'PAGO_VALIDACION';
    case PAGO_CONFIRMADO = 'PAGO_CONFIRMADO';

    /**
     * Obtiene el estado para mostrar al cadete
     */
    public function getCadeteStatus(): string
    {
        return match($this) {
            // Estados operativos del cadete
            self::CADETE_ASIGNADO => 'Nueva comisión asignada',
            self::CADETE_EN_CAMINO_ORIGEN => 'En camino al origen',
            self::EN_PUNTO_RETIRO => 'En punto de retiro',
            self::ENCOMIENDA_RETIRADA => 'Encomienda retirada',
            self::EN_CAMINO_PLANTA => 'En camino a planta/sucursal',
            self::EN_TRANSITO_DESTINO => 'En tránsito a destino',
            self::EN_PROCESO_ENTREGA => 'En proceso de entrega',
            self::ENTREGADO => 'Entregado',
            self::RETIRADO_SUCURSAL => 'Retirado en sucursal',
            
            // Estados de incidencia que puede reportar el cadete
            self::INTENTO_ENTREGA_FALLIDO => 'Intento de entrega fallido',
            self::INTENTO_RETIRO_FALLIDO => 'Intento de retiro fallido',
            self::REPROGRAMANDO_ENTREGA => 'Reprogramando entrega',
            self::DISPONIBLE_RETIRO => 'Disponible para retiro en sucursal',
            self::EN_DEVOLUCION => 'En devolución al remitente',
            self::DEVUELTO_REMITENTE => 'Devuelto al remitente',
            
            // Estados administrativos (ocultos para cadete)
            self::SOLICITUD_RECIBIDA, self::BUSCANDO_CADETE, 
            self::PENDIENTE_PAGO, self::PAGO_VALIDACION, self::PAGO_CONFIRMADO,
            self::EN_SUCURSAL_DESTINO, self::CANCELADO, self::EN_ANALISIS => 'En proceso',
        };
    }

    /**
     * Obtiene el estado para mostrar al cliente
     */
    public function getClienteStatus(): string
    {
        return match($this) {
            // Estados principales del flujo
            self::SOLICITUD_RECIBIDA => 'Solicitud recibida',
            self::PENDIENTE_PAGO => 'Pendiente de pago',
            self::PAGO_VALIDACION => 'Pago en validación',
            self::PAGO_CONFIRMADO => 'Pago confirmado',
            self::BUSCANDO_CADETE => 'Buscando cadete disponible',
            self::CADETE_ASIGNADO => 'Cadete asignado',
            self::CADETE_EN_CAMINO_ORIGEN => 'Cadete en camino al origen',
            self::EN_PUNTO_RETIRO => 'En punto de retiro',
            self::ENCOMIENDA_RETIRADA => 'Encomienda retirada',
            self::EN_CAMINO_PLANTA => 'En camino a planta/sucursal',
            self::EN_TRANSITO_DESTINO => 'En tránsito a destino',
            self::EN_SUCURSAL_DESTINO => 'En sucursal de destino',
            self::EN_PROCESO_ENTREGA => 'En proceso de entrega',
            self::ENTREGADO => 'Entregado',
            self::RETIRADO_SUCURSAL => 'Retirado en sucursal',
            
            // Estados de incidencia
            self::INTENTO_ENTREGA_FALLIDO => 'Intento de entrega fallido',
            self::INTENTO_RETIRO_FALLIDO => 'Intento de retiro fallido',
            self::REPROGRAMANDO_ENTREGA => 'Reprogramando entrega',
            self::DISPONIBLE_RETIRO => 'Disponible para retiro',
            self::EN_DEVOLUCION => 'En devolución',
            self::DEVUELTO_REMITENTE => 'Devuelto al remitente',
            self::CANCELADO => 'Cancelado',
            self::EN_ANALISIS => 'En análisis',
        };
    }

    /**
     * Obtiene el estado para mostrar al admin
     */
    public function getAdminStatus(): string
    {
        return match($this) {
            // Estados secuenciales del workflow
            self::SOLICITUD_RECIBIDA => 'Solicitud recibida',
            self::PENDIENTE_PAGO => 'Pendiente de pago',
            self::PAGO_VALIDACION => 'Pago en validación',
            self::PAGO_CONFIRMADO => 'Pago confirmado',
            self::BUSCANDO_CADETE => 'Buscando cadete disponible',
            self::CADETE_ASIGNADO => 'Cadete asignado',
            self::CADETE_EN_CAMINO_ORIGEN => 'Cadete en camino al origen',
            self::EN_PUNTO_RETIRO => 'En punto de retiro',
            self::ENCOMIENDA_RETIRADA => 'Encomienda retirada',
            self::EN_CAMINO_PLANTA => 'En camino a planta/sucursal',
            self::EN_TRANSITO_DESTINO => 'En tránsito a destino',
            self::EN_SUCURSAL_DESTINO => 'En sucursal de destino',
            self::EN_PROCESO_ENTREGA => 'En proceso de entrega',
            self::ENTREGADO => 'Entregado',
            self::RETIRADO_SUCURSAL => 'Retirado en sucursal',
            
            // Estados de incidencia
            self::INTENTO_ENTREGA_FALLIDO => 'Intento de entrega fallido',
            self::INTENTO_RETIRO_FALLIDO => 'Intento de retiro fallido',
            self::REPROGRAMANDO_ENTREGA => 'Reprogramando entrega',
            self::DISPONIBLE_RETIRO => 'Disponible para retiro',
            self::EN_DEVOLUCION => 'En devolución',
            self::DEVUELTO_REMITENTE => 'Devuelto al remitente',
            self::CANCELADO => 'Cancelado',
            self::EN_ANALISIS => 'En análisis',
        };
    }

    /**
     * Convierte el estado del cadete al estado administrativo correspondiente
     * Acepta tanto valores del enum como labels del cadete
     */
    public static function fromCadeteStatus(string $cadeteStatus): self
    {
        // Primero intentar con el valor directo del enum
        try {
            return self::from($cadeteStatus);
        } catch (\ValueError $e) {
            // Si no es un valor directo, intentar con el label del cadete
        }

        // Mapeo por label del cadete
        return match($cadeteStatus) {
            // Estados operativos del cadete
            'Nueva comisión asignada' => self::CADETE_ASIGNADO,
            'En camino al origen' => self::CADETE_EN_CAMINO_ORIGEN,
            'En punto de retiro' => self::EN_PUNTO_RETIRO,
            'Encomienda retirada' => self::ENCOMIENDA_RETIRADA,
            'En camino a planta/sucursal' => self::EN_CAMINO_PLANTA,
            'En tránsito a destino' => self::EN_TRANSITO_DESTINO,
            'En proceso de entrega' => self::EN_PROCESO_ENTREGA,
            'Entregado' => self::ENTREGADO,
            'Retirado en sucursal' => self::RETIRADO_SUCURSAL,
            
            // Estados de incidencia que puede reportar el cadete
            'Intento de entrega fallido' => self::INTENTO_ENTREGA_FALLIDO,
            'Intento de retiro fallido' => self::INTENTO_RETIRO_FALLIDO,
            'Reprogramando entrega' => self::REPROGRAMANDO_ENTREGA,
            'Disponible para retiro en sucursal' => self::DISPONIBLE_RETIRO,
            'En devolución al remitente' => self::EN_DEVOLUCION,
            'Devuelto al remitente' => self::DEVUELTO_REMITENTE,
            
            default => throw new \InvalidArgumentException("Estado del cadete no válido: {$cadeteStatus}")
        };
    }

    /**
     * Obtiene todos los estados válidos para el cadete
     * Incluye tanto valores del enum como labels del cadete
     */
    public static function getValidCadeteStatuses(): array
    {
        $enumValues = [
            self::CADETE_ASIGNADO->value,
            self::CADETE_EN_CAMINO_ORIGEN->value,
            self::EN_PUNTO_RETIRO->value,
            self::ENCOMIENDA_RETIRADA->value,
            self::EN_CAMINO_PLANTA->value,
            self::EN_TRANSITO_DESTINO->value,
            self::EN_PROCESO_ENTREGA->value,
            self::ENTREGADO->value,
            self::RETIRADO_SUCURSAL->value,
            self::INTENTO_ENTREGA_FALLIDO->value,
            self::INTENTO_RETIRO_FALLIDO->value,
            self::REPROGRAMANDO_ENTREGA->value,
            self::DISPONIBLE_RETIRO->value,
            self::EN_DEVOLUCION->value,
            self::DEVUELTO_REMITENTE->value,
        ];

        $labels = [
            'Nueva comisión asignada',
            'En camino al origen',
            'En punto de retiro',
            'Encomienda retirada',
            'En camino a planta/sucursal',
            'En tránsito a destino',
            'En proceso de entrega',
            'Entregado',
            'Retirado en sucursal',
            'Intento de entrega fallido',
            'Intento de retiro fallido',
            'Reprogramando entrega',
            'Disponible para retiro en sucursal',
            'En devolución al remitente',
            'Devuelto al remitente'
        ];

        return array_merge($enumValues, $labels);
    }

    /**
     * Verifica si el estado es final (no puede cambiar)
     */
    public function isFinal(): bool
    {
        return in_array($this, [
            self::ENTREGADO,
            self::RETIRADO_SUCURSAL,
            self::DEVUELTO_REMITENTE,
            self::CANCELADO
        ]);
    }

    /**
     * Verifica si el estado permite asignación de cadete
     * Nota: Ahora se permite en cualquier estado de la comisión
     */
    public function allowsCadeteAssignment(): bool
    {
        // Se permite asignar/desasignar/cambiar cadete en cualquier estado
        return true;
    }
}
