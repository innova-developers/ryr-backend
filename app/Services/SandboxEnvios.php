<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Fuera de producción, los envíos reales (WhatsApp, push, mail) sólo salen a una lista de
 * destinatarios de prueba. Todo lo demás se bloquea y queda en el log.
 *
 * El 24/09/2026 una simulación contra una copia de producción en local mandó 104 pushes
 * reales de "Comercio por cerrar" a dos cadetes: el .env local tenía las credenciales de
 * verdad y la copia trae los tokens y teléfonos reales. Dev tiene el mismo riesgo: usa la
 * instancia de WhatsApp de producción sobre una copia de los clientes. Registrar un fake en
 * el contenedor no alcanza (los servicios ya construidos siguen usando el real), así que la
 * protección vive en el punto de envío y no depende de que alguien se acuerde de vaciar
 * credenciales.
 *
 * Activo por defecto en cualquier entorno que no sea production. SANDBOX_ENVIOS=false lo
 * apaga (lo usan los tests, que ya trabajan con fakes) y SANDBOX_ENVIOS=true lo fuerza.
 */
final class SandboxEnvios
{
    public static function activo(): bool
    {
        $valor = config('services.sandbox.activo');

        if ($valor === null || $valor === '') {
            return ! app()->environment('production');
        }

        return filter_var($valor, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Se compara por los últimos 10 dígitos: el mismo celular llega cargado como
     * 03416811147, +54 9 341 681-1147 o 5493416811147.
     */
    public static function permiteTelefono(string $telefono): bool
    {
        if (! self::activo()) {
            return true;
        }

        $numero = self::ultimosDiez($telefono);

        if ($numero === '') {
            return false;
        }

        foreach (self::lista('telefonos') as $permitido) {
            if (self::ultimosDiez($permitido) === $numero) {
                return true;
            }
        }

        return false;
    }

    public static function permiteUsuario(int $userId): bool
    {
        if (! self::activo()) {
            return true;
        }

        return in_array((string) $userId, self::lista('usuarios_push'), true);
    }

    public static function permiteMail(string $mail): bool
    {
        if (! self::activo()) {
            return true;
        }

        $mail = mb_strtolower(trim($mail));

        foreach (self::lista('mails') as $permitido) {
            if (mb_strtolower($permitido) === $mail) {
                return true;
            }
        }

        return false;
    }

    public static function bloquear(string $canal, string $destino, array $contexto = []): void
    {
        Log::info('Envío bloqueado por sandbox', $contexto + [
            'canal' => $canal,
            'destino' => self::enmascarar($destino),
            'app_env' => app()->environment(),
        ]);
    }

    /**
     * @return array<int, string>
     */
    private static function lista(string $clave): array
    {
        $valor = config("services.sandbox.{$clave}", '');
        $items = is_array($valor) ? $valor : explode(',', (string) $valor);

        return array_values(array_filter(array_map(fn ($item) => trim((string) $item), $items), fn ($item) => $item !== ''));
    }

    private static function ultimosDiez(string $telefono): string
    {
        return substr(preg_replace('/\D/', '', $telefono) ?? '', -10);
    }

    /**
     * El log no guarda el dato completo de una persona que no pidió estar en la prueba.
     */
    private static function enmascarar(string $destino): string
    {
        if (str_contains($destino, '@')) {
            [$usuario, $dominio] = explode('@', $destino, 2);

            return mb_substr($usuario, 0, 1) . '***@' . $dominio;
        }

        $digitos = preg_replace('/\D/', '', $destino) ?? '';

        return strlen($digitos) > 4 ? '***' . substr($digitos, -4) : $destino;
    }
}
