<?php

namespace App\Contexts\Users\Infrastructure\Http\Controllers;

use App\Contexts\Users\Application\CreateUserUseCase;
use App\Contexts\Users\Application\DeleteUserUseCase;
use App\Contexts\Users\Application\DTO\CreateUserDTO;
use App\Contexts\Users\Application\DTO\UpdateUserDTO;
use App\Contexts\Users\Application\GetUsersUseCase;
use App\Contexts\Users\Application\UpdateUserUseCase;
use App\Contexts\Users\Domain\Repositories\UserRepository;
use App\Contexts\Users\Infrastructure\Http\Requests\CreateUserRequest;
use App\Shared\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class UserController extends Controller
{
    private UserRepository $repository;

    public function __construct()
    {
        $this->repository = app(UserRepository::class);
    }

    public function store(CreateUserRequest $request): JsonResponse
    {
        $useCase = new CreateUserUseCase(
            $this->repository
        );
        $dto = new CreateUserDTO(
            $request->input('name'),
            $request->input('email'),
            $request->input('password'),
            $request->input('role'),
            $request->input('branch_id')
        );
        $newUser = $useCase($dto);

        return response()->json($newUser, 201);
    }

    public function index(): JsonResponse
    {
        $useCase = new GetUsersUseCase($this->repository);
        $users = $useCase();

        return response()->json($users);
    }

    public function destroy(int $id): JsonResponse
    {
        $useCase = new DeleteUserUseCase($this->repository);
        $users = $useCase($id);

        return response()->json($users);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $user = User::find($id);
        if (! $user) {
            return response()->json(['message' => 'Usuario no encontrado'], 404);
        }
        $dto = new UpdateUserDTO(
            $id,
            $request->input('name'),
            $request->input('email'),
            $request->input('password'),
            $request->input('role'),
            $request->input('branch_id')
        );
        $useCase = new UpdateUserUseCase($this->repository);
        $editedUser = $useCase($dto);

        return response()->json($editedUser);
    }
}
