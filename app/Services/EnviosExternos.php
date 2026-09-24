<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

use function Illuminate\Support\defer;

/**
 * RC-551 — Envíos a proveedores externos (WhatsApp por GreenAPI, mail por SMTP, push por
 * FCM) fuera de la transacción y después de responderle al cliente.
 *
 * Antes salían dentro del DB::transaction del cambio de estado y del alta de comisión. En
 * producción un ENTREGADO tardaba p50 3 s, p90 4 s y hasta 23 s, casi todo esperando a
 * GreenAPI (Http::timeout 30), al SMTP y a FCM (de a un token); la parte de base es menor
 * a 0,1 s. Mientras tanto la transacción seguía abierta y el cadete esperaba con la
 * pantalla trabada.
 *
 * Cómo funciona, sin cola ni cron (en prod QUEUE_CONNECTION=sync y el crontab está vacío):
 *
 *  1. DB::afterCommit: si hay una transacción abierta, el envío espera a que se confirme; si
 *     se revierte, no se manda nada. Sin transacción pasa directo al paso 2.
 *  2. defer(): Laravel lo ejecuta al terminar el request (InvokeDeferredCallbacks, en el
 *     terminate del kernel), que en public/index.php viene DESPUÉS de Response::send().
 *     send() llama a litespeed_finish_request() —producción corre PHP 8.2.17 con mod_lsapi,
 *     "Server API => LiteSpeed"— o a fastcgi_finish_request() con PHP-FPM: el cliente ya
 *     recibió la respuesta completa cuando arranca el envío. En un comando de artisan corre
 *     al terminar el comando. Con always() se manda aunque la respuesta termine en error,
 *     como antes: lo que decide es que la transacción se haya confirmado.
 *  3. Si el envío falla ya no hay a quién devolverle el error: se captura y se loguea sin
 *     credenciales (el token de GreenAPI viaja en la URL y puede venir en el mensaje).
 *
 * El worker de PHP sigue ocupado mientras dura el envío: lo que cambia es que el usuario
 * no lo espera y que la transacción, con sus locks, dura sólo la parte de base.
 */
final class EnviosExternos
{
    /**
     * @param  string  $envio  qué se manda, para el log si falla
     * @param  callable  $enviar  la llamada al proveedor; no debe escribir nada que tenga que
     *                            quedar en la misma transacción que el cambio
     * @param  array<string, mixed>  $contexto  ids para ubicar el caso en el log
     */
    public static function despuesDeResponder(string $envio, callable $enviar, array $contexto = []): void
    {
        DB::afterCommit(function () use ($envio, $enviar, $contexto) {
            defer(function () use ($envio, $enviar, $contexto) {
                try {
                    $enviar();
                } catch (\Throwable $e) {
                    Log::error("Falló un envío externo después de responder: {$envio}", $contexto + [
                        'exception' => get_class($e),
                        'error' => self::sinCredenciales($e->getMessage()),
                    ]);
                }
            })->always();
        });
    }

    private static function sinCredenciales(string $texto): string
    {
        $token = (string) config('services.whatsapp.token');

        return $token === '' ? $texto : str_replace($token, '***', $texto);
    }
}
