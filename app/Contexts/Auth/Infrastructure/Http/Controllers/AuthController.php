<?php

namespace App\Contexts\Auth\Infrastructure\Http\Controllers;

use App\Contexts\Auth\Application\DTOs\LoginDTO;
use App\Contexts\Auth\Application\LoginUseCase;
use App\Contexts\Auth\Application\LogoutUseCase;
use App\Contexts\Auth\Domain\Exceptions\InvalidScopeException;
use App\Contexts\Auth\Infrastructure\Http\Requests\AuthRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class AuthController extends Controller
{
    public function login(AuthRequest $request, LoginUseCase $loginUseCase): JsonResponse
    {
        try {
            $loginDTO = LoginDTO::fromArray($request->validated());
            $useCase = new LoginUseCase();

            return response()->json($useCase($loginDTO));
        } catch (InvalidScopeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'error' => 'scope_validation_failed',
            ], 403);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'error' => 'invalid_credentials',
            ], 401);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error interno del servidor',
                'error' => 'internal_error',
            ], 500);
        }
    }

    public function logout(Request $request, LogoutUseCase $logoutUseCase): JsonResponse
    {
        $result = $logoutUseCase->execute();

        return response()->json($result);
    }
}
