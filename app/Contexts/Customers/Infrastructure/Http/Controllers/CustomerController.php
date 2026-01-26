<?php

namespace App\Contexts\Customers\Infrastructure\Http\Controllers;

use App\Contexts\Customers\Application\CreateCustomerUseCase;
use App\Contexts\Customers\Application\DeleteCustomerUseCase;
use App\Contexts\Customers\Application\DTO\CreateCustomerDTO;
use App\Contexts\Customers\Application\DTO\GetCustomersFiltersDTO;
use App\Contexts\Customers\Application\DTO\UpdateCustomerDTO;
use App\Contexts\Customers\Application\GetCustomersUseCase;
use App\Contexts\Customers\Application\GetCustomerUseCase;
use App\Contexts\Customers\Application\SearchCustomersUseCase;
use App\Contexts\Customers\Application\UpdateCustomerUseCase;
use App\Contexts\Customers\Domain\Repositories\CustomerRepository;
use App\Contexts\Customers\Infrastructure\Http\Requests\CreateCustomerRequest;
use App\Contexts\Customers\Infrastructure\Http\Requests\UpdateCustomerRequest;
use App\Contexts\Destinations\Domain\Repositories\DestinationRepository;
use App\Contexts\Locations\Domain\Repositories\LocationsRepository;
use App\Contexts\Users\Application\CreateUserUseCase;
use App\Contexts\Users\Application\DTO\CreateUserDTO;
use App\Contexts\Users\Domain\Repositories\UserRepository;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class CustomerController extends Controller
{
    private UserRepository $userRepository;
    private CustomerRepository $repository;
    private LocationsRepository $locationsRepository;
    private DestinationRepository $destinationRepository;

    public function __construct()
    {
        $this->userRepository = app(UserRepository::class);
        $this->repository = app(CustomerRepository::class);
        $this->locationsRepository = app(LocationsRepository::class);
        $this->destinationRepository = app(DestinationRepository::class);
    }

    public function index(Request $request): JsonResponse
    {
        $filters = GetCustomersFiltersDTO::fromArray($request->all());
        $useCase = new GetCustomersUseCase($this->repository);
        $customers = $useCase($filters);

        return response()->json($customers);
    }

    public function store(CreateCustomerRequest $request): JsonResponse
    {
        try {
            $user = Auth::user();

            if (! $user) {
                return response()->json(['message' => 'Usuario no autenticado'], 401);
            }

            // Si el usuario no tiene branch_id (super admin), establecer en 1
            $branchId = $user->branch_id ?? 1;
            
            $useCaseCreateUser = new CreateUserUseCase($this->userRepository);
            $dtoCreateUser = new CreateUserDTO(
                $request->input('name'),
                $request->input('email'),
                $request->input('dni'),
                'cliente',
                $branchId
            );
            $userCreated = $useCaseCreateUser($dtoCreateUser);

            $useCase = new CreateCustomerUseCase($this->repository, $this->locationsRepository, $this->destinationRepository);
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
                $request->boolean('auto_calculate_iva', true),
                $userCreated->id,
                $branchId
            );
            $customer = $useCase($dto);

            return response()->json($customer, 201);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al crear el cliente: ' . $e->getMessage()], 500);
        }
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
            $user = Auth::user();

            if (! $user) {
                return response()->json(['message' => 'Usuario no autenticado'], 401);
            }

            // Obtener el customer actual para preservar user_id si no se proporciona
            $currentCustomer = $this->repository->findById($id);
            if (!$currentCustomer) {
                return response()->json(['message' => 'Customer not found'], 404);
            }

            $useCase = new UpdateCustomerUseCase($this->repository, $this->locationsRepository, $this->destinationRepository);
            // Si el usuario no tiene branch_id (super admin), establecer en 1
            $branchId = $user->branch_id ?? 1;
            
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
                $request->boolean('auto_calculate_iva', true),
                $request->input('user_id', $currentCustomer->user_id), // Preservar user_id existente si no se proporciona
                $branchId,
                $request->input('internal_user_id', $currentCustomer->internal_user_id) // Preservar internal_user_id existente si no se proporciona
            );
            $customer = $useCase($dto);

            return response()->json($customer);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Customer not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al actualizar el cliente: ' . $e->getMessage()], 500);
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

    public function search(Request $request): JsonResponse
    {
        $query = $request->input('q', '');
        $useCase = new SearchCustomersUseCase($this->repository);
        $customers = $useCase($query);

        return response()->json($customers);
    }

    public function updateAutoCalculateIva(Request $request, int $id): JsonResponse
    {
        try {
            $user = Auth::user();

            if (! $user) {
                return response()->json(['message' => 'Usuario no autenticado'], 401);
            }

            $validated = $request->validate([
                'auto_calculate_iva' => 'required|boolean',
            ]);

            $customer = $this->repository->findById($id);
            if (!$customer) {
                return response()->json(['message' => 'Customer not found'], 404);
            }

            $customer->auto_calculate_iva = $validated['auto_calculate_iva'];
            $customer->save();

            return response()->json([
                'message' => 'Campo auto_calculate_iva actualizado exitosamente',
                'customer' => [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'last_name' => $customer->last_name,
                    'auto_calculate_iva' => $customer->auto_calculate_iva,
                ]
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $errors = $e->errors();
            $errorMessages = [];
            foreach ($errors as $field => $messages) {
                $errorMessages[] = $field . ': ' . implode(', ', $messages);
            }
            
            return response()->json([
                'message' => 'Datos inválidos: ' . implode(', ', $errorMessages),
                'errors' => $errors
            ], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al actualizar el campo: ' . $e->getMessage()], 500);
        }
    }
}
