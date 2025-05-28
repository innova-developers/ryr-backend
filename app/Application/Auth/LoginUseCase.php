<?php

namespace App\Application\Auth;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class LoginUseCase
{
    public function execute(array $credentials)
    {
        $validator = Validator::make($credentials, [
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            return [
                'success' => false,
                'message' => 'Datos inválidos',
                'errors' => $validator->errors(),
            ];
        }

        if (!Auth::attempt($credentials)) {
            return [
                'success' => false,
                'message' => 'Credenciales incorrectas',
            ];
        }

        $user = Auth::user();
        $token = $user->createToken('Personal Access Token')->accessToken;

        return [
            'success' => true,
            'token' => $token,
            'user' => $user,
        ];
    }
}

