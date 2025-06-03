<?php

namespace App\Contexts\Auth\Application;

use Illuminate\Support\Facades\Auth;

class LogoutUseCase
{
    /**
     * @return array<string, mixed>
     */
    public function execute(): array
    {
        $user = Auth::user();
        if ($user) {
            $user->currentAccessToken()->delete();
        }

        return [
            'success' => true,
            'message' => 'Sesión cerrada correctamente',
        ];
    }
}
