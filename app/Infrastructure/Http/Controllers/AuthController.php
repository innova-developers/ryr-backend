<?php

namespace App\Infrastructure\Http\Controllers;

use App\Application\Auth\LoginUseCase;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class AuthController extends Controller
{
    public function login(Request $request, LoginUseCase $loginUseCase)
    {
        $result = $loginUseCase->execute($request->only(['email', 'password']));
        if (!$result['success']) {
            return response()->json($result, 401);
        }
        return response()->json($result);
    }
}

