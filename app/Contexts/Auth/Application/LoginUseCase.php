<?php

namespace App\Contexts\Auth\Application;

use App\Contexts\Auth\Application\DTOs\LoginDTO;
use App\Contexts\Auth\Application\Mappers\LoginMapper;
use App\Contexts\Auth\Domain\Exceptions\InvalidScopeException;
use App\Shared\Enums\UserRole;
use Illuminate\Support\Facades\Auth;

class LoginUseCase
{
    /**
     * @param LoginDTO $loginDTO
     * @return array<string, mixed>
     * @throws InvalidScopeException
     */
    public function __invoke(LoginDTO $loginDTO): array
    {
        if (! Auth::attempt($loginDTO->getCredentials())) {
            throw new \InvalidArgumentException('Credenciales inválidas');
        }
        
        $user = Auth::user();
        if (! $user) {
            throw new \RuntimeException('No se pudo obtener el usuario autenticado');
        }

        // Validar scope según el rol del usuario
        $this->validateUserScope($user->role, $loginDTO->scope);
        
        $token = $user->createToken('Personal Access Token')->plainTextToken;

        return LoginMapper::map($user, $token);
    }

    /**
     * Valida si el usuario tiene permisos para el scope solicitado
     */
    private function validateUserScope(UserRole $userRole, string $scope): void
    {
        switch ($scope) {
            case 'web':
                if ($userRole !== UserRole::ADMINISTRADOR && $userRole !== UserRole::MOSTRADOR) {
                    throw new InvalidScopeException($userRole->value, $scope);
                }
                break;
                
            case 'app':
                if (!in_array($userRole, [UserRole::CADETE, UserRole::CADETE_EXTERNO])) {
                    throw new InvalidScopeException($userRole->value, $scope);
                }
                break;
                
            default:
                throw new InvalidScopeException($userRole->value, $scope);
        }
    }
}
