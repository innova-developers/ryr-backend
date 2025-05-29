<?php

namespace App\Contexts\Auth\Application;

use App\Contexts\Auth\Application\Mappers\LoginMapper;
use Illuminate\Support\Facades\Auth;

class LoginUseCase
{
    public function __invoke(array $credentials):array
    {
        if (!Auth::attempt($credentials)) {
            throw new \InvalidArgumentException('Credenciales inválidas');
        }
        $user = Auth::user();
        $token = $user->createToken('Personal Access Token')->plainTextToken;
        return LoginMapper::map($user, $token);
    }
}

