<?php

namespace App\Contexts\Auth\Application\Mappers;

class LoginMapper
{
    public static function map($user, string $token): array
    {
        return [
            'success' => true,
            'token' => $token,
            'user' => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
                'role'  => $user->role,
            ],
        ];
    }
}
