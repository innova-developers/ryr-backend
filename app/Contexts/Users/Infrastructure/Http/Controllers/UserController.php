<?php

namespace App\Contexts\Users\Infrastructure\Http\Controllers;

use App\Contexts\Users\Application\CreateUserUseCase;
use App\Contexts\Users\Application\DTO\CreateUserDTO;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use App\Contexts\Users\Domain\Repositories\UserRepository;
use App\Contexts\Users\Infrastructure\Http\Requests\CreateUserRequest;


class UserController extends Controller
{
    private UserRepository $repository;

    public function __construct()
    {
        $this->repository = app(UserRepository::class);
    }

    public function store(CreateUserRequest $request)
    {
        $useCase = new CreateUserUseCase(
            $this->repository
        );
        $dto = new CreateUserDTO(
            $request->input('name'),
            $request->input('email'),
            $request->input('password'),
            $request->input('role')
        );
        $newUser =  $useCase($dto);
        return response()->json($newUser, 201);
    }
}
