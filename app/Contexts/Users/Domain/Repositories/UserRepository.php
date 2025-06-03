<?php

namespace App\Contexts\Users\Domain\Repositories;

use App\Contexts\Users\Application\DTO\CreateUserDTO;
use App\Contexts\Users\Application\DTO\UpdateUserDTO;
use App\Shared\Models\User;

interface UserRepository
{
    public function create(CreateUserDTO $dto): User;
    /**
     * @return User[]
     */
    public function get(): array;
    /**
     * @return User[]
     */
    public function delete(int $id): array;
    public function update(UpdateUserDTO $dto): User;
    /**
     * @return User[]
     */
}
