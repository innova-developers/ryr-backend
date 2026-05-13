<?php

namespace App\Contexts\Users\Infrastructure\Http\Controllers;

use App\Contexts\Users\Application\CalculateSalaryUseCase;
use App\Contexts\Users\Application\CreateUserUseCase;
use App\Contexts\Users\Application\DeleteUserUseCase;
use App\Contexts\Users\Application\DTO\CreateUserDTO;
use App\Contexts\Users\Application\DTO\GetUsersFiltersDTO;
use App\Contexts\Users\Application\DTO\UpdateUserDTO;
use App\Contexts\Users\Application\GetUsersUseCase;
use App\Contexts\Users\Application\UpdateUserUseCase;
use App\Contexts\Users\Domain\Repositories\UserRepository;
use App\Contexts\Users\Infrastructure\Http\Requests\CreateUserRequest;
use App\Contexts\Users\Infrastructure\Http\Requests\EditUserRequest;
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
            $request->input('branch_id'),
            $request->input('base_salary'),
            $request->input('income_percentage'),
            $request->input('commission_percentage'),
            $request->input('contract_type')
        );
        $newUser = $useCase($dto);

        return response()->json($newUser, 201);
    }

    public function index(Request $request): JsonResponse
    {
        $filters = GetUsersFiltersDTO::fromArray($request->all());
        $useCase = new GetUsersUseCase($this->repository);
        $users = $useCase($filters);

        return response()->json($users);
    }

    public function destroy(int $id): JsonResponse
    {
        $useCase = new DeleteUserUseCase($this->repository);
        $users = $useCase($id);

        return response()->json($users);
    }

    public function update(EditUserRequest $request, int $id): JsonResponse
    {
        try {
            $dto = new UpdateUserDTO(
                $id,
                $request->input('name'),
                $request->input('email'),
                $request->input('password'),
                $request->input('role'),
                $request->input('branch_id'),
                $request->input('base_salary'),
                $request->input('income_percentage'),
                $request->input('commission_percentage'),
                $request->input('contract_type')
            );
            $useCase = new UpdateUserUseCase($this->repository);
            $editedUser = $useCase($dto);

            return response()->json($editedUser);
        } catch (\Exception $e) {
            if ($e->getCode() === 404) {
                return response()->json(['message' => $e->getMessage()], 404);
            }

            return response()->json(['message' => 'Error interno del servidor'], 500);
        }
    }

    public function calculateSalary(int $userId): JsonResponse
    {
        try {
            $month = request()->query('month');
            $useCase = new CalculateSalaryUseCase($this->repository);
            $salaryData = $useCase->execute($userId, $month);

            return response()->json($salaryData);
        } catch (\Exception $e) {
            if ($e->getMessage() === 'Usuario no encontrado') {
                return response()->json(['message' => $e->getMessage()], 404);
            }

            return response()->json(['message' => 'Error al calcular el salario: ' . $e->getMessage()], 500);
        }
    }

    public function show(int $id): JsonResponse
    {
        $user = $this->repository->findById($id);

        return response()->json($user);
    }
}
