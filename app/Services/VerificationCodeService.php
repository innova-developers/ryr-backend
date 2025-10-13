<?php

namespace App\Services;

use App\Mail\VerificationCodeMail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

class VerificationCodeService
{
    /**
     * Generar código de verificación de 6 dígitos
     */
    public function generateCode(): string
    {
        return str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Guardar código en cache con expiración de 10 minutos
     */
    public function storeCode(string $identifier, string $type, string $code): void
    {
        $key = "verification_code_{$type}_{$identifier}";
        Cache::put($key, $code, now()->addMinutes(10));
    }

    /**
     * Verificar código
     */
    public function verifyCode(string $identifier, string $type, string $code): bool
    {
        $key = "verification_code_{$type}_{$identifier}";
        $storedCode = Cache::get($key);

        if ($storedCode && $storedCode === $code) {
            Cache::forget($key); // Eliminar código después de verificar

            return true;
        }

        return false;
    }

    /**
     * Enviar código por email
     */
    public function sendCodeByEmail(string $email, string $code): bool
    {
        try {
            Mail::to($email)->send(new VerificationCodeMail($code));

            return true;
        } catch (\Exception $e) {
            \Log::error('Error enviando código por email: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Enviar código por WhatsApp
     */
    public function sendCodeByWhatsApp(string $phone, string $code): bool
    {
        try {
            $whatsappService = new WhatsAppService();
            $message = "Tu código de verificación es: {$code}\n\nEste código expira en 10 minutos.";

            return $whatsappService->sendMessage($phone, $message) !== false;
        } catch (\Exception $e) {
            \Log::error('Error enviando código por WhatsApp: ' . $e->getMessage());

            return false;
        }
    }
}
