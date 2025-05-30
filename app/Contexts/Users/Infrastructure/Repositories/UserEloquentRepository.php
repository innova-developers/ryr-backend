<?php

namespace App\Contexts\Users\Infrastructure\Repositories;

use App\Contexts\Users\Application\DTO\CreateUserDTO;
use App\Contexts\Users\Domain\Repositories\UserRepository;
use App\Shared\Models\User;

class UserEloquentRepository implements UserRepository
{
    public function create(CreateUserDTO $dto):User
    {
        return User::create([
            'name' => $dto->name,
            'email' => $dto->email,
            'password' => bcrypt($dto->password),
            'role' => $dto->role,
        ]);
    }
    public function get():array
    {
        return User::all()->toArray();
    }
    public function delete(int $id):array
    {
        $user = User::findOrFail($id);
        $user->delete();
        return User::all()->toArray();
    }
}
