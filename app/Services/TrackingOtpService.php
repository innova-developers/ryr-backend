<?php

namespace App\Services;

use App\Mail\VerificationCodeMail;
use App\Shared\Models\Commission;
use App\Shared\Models\TrackingOtp;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * RC-500 — Códigos de un solo uso para destrabar el detalle de un envío.
 *
 * El seguimiento público muestra sólo el estado. Para ver el detalle (direcciones,
 * historial, notas) el titular tiene que validar su identidad con un código que
 * llega al teléfono y al mail que figuran en su ficha.
 *
 * Decisiones de seguridad:
 *  - Se guarda el HASH del código, nunca el código.
 *  - El mismo código va por WhatsApp y por mail, así el titular usa el canal que tenga.
 *  - Pedir un código nunca revela si el envío existe ni cuál es el teléfono/mail: la
 *    respuesta es siempre la misma, para no convertir el endpoint en un oráculo.
 *  - Se invalidan los códigos anteriores al emitir uno nuevo.
 */
class TrackingOtpService
{
    public const MAX_INTENTOS = 5;
    private const VIGENCIA_MINUTOS = 10;
    private const MAX_ENVIOS_POR_HORA = 3;

    public function __construct(private readonly WhatsAppService $whatsApp)
    {
    }

    /**
     * Emite y envía un código. Devuelve los canales enmascarados a los que se mandó,
     * o null si no hay a dónde mandarlo (el caller responde igual para no filtrar).
     */
    public function emitir(Commission $commission, ?string $ip = null): ?string
    {
        if ($this->superaLimite($commission)) {
            return null;
        }

        $customer = $commission->client;

        if (! $customer) {
            return null;
        }

        $telefono = $customer->mobile ?: $customer->phone;
        $email = $customer->email;

        if (empty($telefono) && empty($email)) {
            return null;
        }

        $codigo = $this->generarCodigo();

        // Un código nuevo invalida los anteriores del mismo envío.
        TrackingOtp::where('commission_id', $commission->id)
            ->whereNull('used_at')
            ->update(['used_at' => now()]);

        $destinos = array_filter([
            $telefono ? $this->enmascararTelefono($telefono) : null,
            $email ? $this->enmascararEmail($email) : null,
        ]);
        $masked = implode(' y ', $destinos);

        TrackingOtp::create([
            'commission_id' => $commission->id,
            'code_hash' => Hash::make($codigo),
            'sent_to_masked' => $masked,
            'expires_at' => now()->addMinutes(self::VIGENCIA_MINUTOS),
            'request_ip' => $ip,
        ]);

        $this->enviar($commission, $codigo, $telefono, $email);

        return $masked;
    }

    /**
     * Verifica un código. Devuelve la comisión si es válido, null si no.
     */
    public function verificar(Commission $commission, string $codigo): ?TrackingOtp
    {
        $otp = TrackingOtp::where('commission_id', $commission->id)
            ->whereNull('used_at')
            ->orderByDesc('id')
            ->first();

        if (! $otp || ! $otp->isUsable()) {
            return null;
        }

        $otp->increment('attempts');

        if (! Hash::check($codigo, $otp->code_hash)) {
            // Agotados los intentos, el código muere aunque no haya vencido.
            if ($otp->fresh()->attempts >= self::MAX_INTENTOS) {
                $otp->update(['used_at' => now()]);
            }

            return null;
        }

        $otp->update(['used_at' => now()]);

        return $otp;
    }

    /**
     * Tope de emisiones por envío y por hora, para que el endpoint no sirva para
     * bombardear a un cliente con mensajes.
     */
    private function superaLimite(Commission $commission): bool
    {
        return TrackingOtp::where('commission_id', $commission->id)
            ->where('created_at', '>=', now()->subHour())
            ->count() >= self::MAX_ENVIOS_POR_HORA;
    }

    private function generarCodigo(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    private function enviar(Commission $commission, string $codigo, ?string $telefono, ?string $email): void
    {
        $mensaje = "🔐 *R&R Comisiones*\n\n"
            . "Tu código para ver el detalle del envío #{$commission->id} es:\n\n"
            . "*{$codigo}*\n\n"
            . 'Vence en ' . self::VIGENCIA_MINUTOS . " minutos y se usa una sola vez.\n"
            . 'Si no lo pediste, ignorá este mensaje.';

        if ($telefono) {
            try {
                $this->whatsApp->sendMessage($telefono, $mensaje);
            } catch (\Throwable $e) {
                Log::warning('No se pudo enviar el OTP de tracking por WhatsApp', [
                    'commission_id' => $commission->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($email) {
            try {
                Mail::to($email)->send(new VerificationCodeMail($codigo));
            } catch (\Throwable $e) {
                Log::warning('No se pudo enviar el OTP de tracking por email', [
                    'commission_id' => $commission->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Deja ver sólo los últimos dígitos: alcanza para que el titular reconozca su
     * número, pero no para reconstruirlo.
     */
    private function enmascararTelefono(string $telefono): string
    {
        $digitos = preg_replace('/\D/', '', $telefono);

        return 'WhatsApp ****' . substr($digitos, -3);
    }

    private function enmascararEmail(string $email): string
    {
        [$usuario, $dominio] = array_pad(explode('@', $email, 2), 2, '');

        $visible = mb_substr($usuario, 0, 1);

        return 'mail ' . $visible . str_repeat('*', max(3, mb_strlen($usuario) - 1)) . '@' . $dominio;
    }
}
