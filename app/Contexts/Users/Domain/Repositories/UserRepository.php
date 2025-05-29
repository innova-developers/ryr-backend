<?php

namespace App\Contexts\Users\Domain\Repositories;

use App\Contexts\Users\Application\DTO\CreateUserDTO;
use GuzzleHttp\Promise\Create;

interface UserRepository
{
    public function create(CreateUserDTO $dto);
}
