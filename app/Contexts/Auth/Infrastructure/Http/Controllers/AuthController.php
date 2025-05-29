<?php

namespace App\Contexts\Auth\Infrastructure\Http\Controllers;

use App\Contexts\Auth\Application\LoginUseCase;
use App\Contexts\Auth\Application\LogoutUseCase;
use App\Contexts\Auth\Infrastructure\Http\Requests\AuthRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class AuthController extends Controller
{
    public function login(AuthRequest $request, LoginUseCase $loginUseCase):JsonResponse
    {
        $credentials = $request->only(['email', 'password']);
        $useCase = new LoginUseCase();
        return response()->json($useCase($credentials));
    }

    public function logout(Request $request, LogoutUseCase $logoutUseCase):JsonResponse
    {
        $result = $logoutUseCase->execute();
        return response()->json($result);
    }
}

