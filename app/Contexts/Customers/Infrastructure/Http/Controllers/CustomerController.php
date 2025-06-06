<?php

namespace App\Contexts\Customers\Infrastructure\Http\Controllers;

use App\Contexts\Customers\Application\CreateCustomerUseCase;
use App\Contexts\Customers\Application\DeleteCustomerUseCase;
use App\Contexts\Customers\Application\DTO\CreateCustomerDTO;
use App\Contexts\Customers\Application\DTO\UpdateCustomerDTO;
use App\Contexts\Customers\Application\GetCustomersUseCase;
use App\Contexts\Customers\Application\GetCustomerUseCase;
use App\Contexts\Customers\Application\UpdateCustomerUseCase;
use App\Contexts\Customers\Domain\Repositories\CustomerRepository;
use App\Contexts\Customers\Infrastructure\Http\Requests\CreateCustomerRequest;
use App\Contexts\Customers\Infrastructure\Http\Requests\UpdateCustomerRequest;
use App\Contexts\Users\Application\CreateUserUseCase;
use App\Contexts\Users\Application\DTO\CreateUserDTO;
use App\Contexts\Users\Domain\Repositories\UserRepository;
use App\Shared\Models\Branch;
use App\Shared\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class CustomerController extends Controller
{
    private UserRepository $userRepository;
    private CustomerRepository $repository;

    public function __construct()
    {
        $this->userRepository = app(UserRepository::class);
        $this->repository = app(CustomerRepository::class);
    }

    public function index(): JsonResponse
    {
        $useCase = new GetCustomersUseCase($this->repository);
        $customers = $useCase();
        return response()->json($customers);
    }

    public function store(CreateCustomerRequest $request): JsonResponse
    {
        $useCaseCreateUser = new CreateUserUseCase($this->userRepository);
        $dtoCreateUser = new CreateUserDTO(
            $request->input('name'),
            $request->input('email'),
            $request->input('dni'),
            'customer',
            $request->input('branch_id', Auth::user()->branch_id)
        );
        $userCreated = $useCaseCreateUser($dtoCreateUser);
        $useCase = new CreateCustomerUseCase($this->repository);
        $dto = new CreateCustomerDTO(
            $request->input('dni'),
            $request->input('name'),
            $request->input('last_name'),
            $request->input('mobile'),
            $request->input('email'),
            $request->input('address'),
            $request->input('city'),
            $request->input('phone'),
            $request->input('maps_url'),
            $request->input('business_hours'),
            $request->input('observations'),
            $request->boolean('is_premium', false),
            $userCreated->id,
            Auth::user()->branch_id ?? Branch::first()->id
        );
        $customer = $useCase($dto);

        return response()->json($customer, 201);
    }

    public function show(int $id): JsonResponse
    {
        try {
            $useCase = new GetCustomerUseCase($this->repository);
            $customer = $useCase($id);

            return response()->json($customer);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Customer not found'], 404);
        }
    }

    public function update(UpdateCustomerRequest $request, int $id): JsonResponse
    {
        try {
            $useCase = new UpdateCustomerUseCase($this->repository);
            $dto = new UpdateCustomerDTO(
                $id,
                $request->input('dni'),
                $request->input('name'),
                $request->input('last_name'),
                $request->input('mobile'),
                $request->input('email'),
                $request->input('address'),
                $request->input('city'),
                $request->input('phone'),
                $request->input('maps_url'),
                $request->input('business_hours'),
                $request->input('observations'),
                $request->boolean('is_premium', false),
                $request->input('user_id', null),
                Auth::user()->branch_id ?? Branch::first()->id
            );
            $customer = $useCase($dto);

            return response()->json($customer);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Customer not found'], 404);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            $useCase = new DeleteCustomerUseCase($this->repository);
            $useCase($id);
            return response()->json(null, 200);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Customer not found'], 404);
        }
    }
}
