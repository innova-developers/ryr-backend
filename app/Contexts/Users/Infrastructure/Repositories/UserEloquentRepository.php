<?php

namespace App\Contexts\Users\Infrastructure\Repositories;

use App\Contexts\Users\Application\DTO\CreateUserDTO;
use App\Contexts\Users\Application\DTO\UpdateUserDTO;
use App\Contexts\Users\Domain\Repositories\UserRepository;
use App\Shared\Models\User;

class UserEloquentRepository implements UserRepository
{
    public function create(CreateUserDTO $dto): User
    {
        return User::create([
            'name' => $dto->name,
            'email' => $dto->email,
            'password' => bcrypt($dto->password),
            'role' => $dto->role,
            'branch_id' => $dto->branch_id,
            'base_salary' => $dto->base_salary,
            'income_percentage' => $dto->income_percentage,
            'commission_percentage' => $dto->commission_percentage,
            'contract_type' => $dto->contract_type,
        ]);
    }
    public function get(): array
    {
        return User::select('id', 'name', 'email', 'role', 'created_at', 'branch_id', 'base_salary', 'income_percentage', 'commission_percentage', 'contract_type')
            ->with(['branch:id,name'])
            ->where('role', '!=', 'cliente')
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'created_at' => $user->created_at,
                    'branch_id' => $user->branch ? $user->branch->id : null,
                    'branch_name' => $user->branch ? $user->branch->name : null,
                    'base_salary' => $user->base_salary,
                    'income_percentage' => $user->income_percentage,
                    'commission_percentage' => $user->commission_percentage,
                    'contract_type' => $user->contract_type,
                ];
            })
            ->toArray();
    }
    public function delete(int $id): array
    {
        $user = User::findOrFail($id);
        $user->delete();

        return User::all()->toArray();
    }

    public function update(UpdateUserDTO $dto): User
    {
        try {
            $user = User::find($dto->id);

            if (! $user) {
                throw new \Exception('Usuario no encontrado', 404);
            }

            $user->name = $dto->name;
            $user->email = $dto->email;
            $user->password = $dto->password ? bcrypt($dto->password) : $user->password;
            $user->role = $dto->role;
            $user->branch_id = $dto->branch_id;
            $user->base_salary = $dto->base_salary;
            $user->income_percentage = $dto->income_percentage;
            $user->commission_percentage = $dto->commission_percentage;
            $user->contract_type = $dto->contract_type;
            $user->save();

            return $user;
        } catch (\Exception $e) {
            if ($e->getCode() === 404) {
                throw new \Exception($e->getMessage(), 404);
            }

            throw new \Exception('Error al actualizar el usuario: ' . $e->getMessage());
        }
    }

    public function findById(int $id): ?User
    {
        return User::find($id);
    }
}
