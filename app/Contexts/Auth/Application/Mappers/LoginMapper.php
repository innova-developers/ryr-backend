<?php

namespace App\Contexts\Auth\Application\Mappers;

use App\Shared\Models\User;

class LoginMapper
{
    /**
     * @return array<string, mixed>
     */
    public static function map(User $user, string $token): array
    {
        return [
            'success' => true,
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
        ];
    }
}
