<?php

namespace App\Contexts\Commissions\Infrastructure\Http\Controllers;

use App\Contexts\Commissions\Application\CreateCommissionUseCase;
use App\Contexts\Commissions\Application\DeleteCommissionUseCase;
use App\Contexts\Commissions\Application\DTOs\CreateCommissionDTO;
use App\Contexts\Commissions\Application\DTOs\CreateCommissionLogDTO;
use App\Contexts\Commissions\Application\DTOs\ListCommissionsFiltersDTO;
use App\Contexts\Commissions\Application\DTOs\UpdateCommissionDTO;
use App\Contexts\Commissions\Application\GetCommissionUseCase;
use App\Contexts\Commissions\Application\ListCommissionsUseCase;
use App\Contexts\Commissions\Application\UpdateCommissionStatusUseCase;
use App\Contexts\Commissions\Application\UpdateCommissionUseCase;
use App\Contexts\Commissions\Domain\Repositories\CommissionsRepository;
use App\Contexts\Commissions\Infrastructure\Http\Requests\CreateCommissionRequest;
use App\Contexts\Commissions\Infrastructure\Http\Requests\UpdateCommissionRequest;
use App\Contexts\CurrentAccount\Domain\Repositories\CurrentAccountRepository;
use App\Contexts\Customers\Domain\Repositories\CustomerRepository;
use App\Contexts\Destinations\Domain\Repositories\DestinationRepository;
use App\Services\FcmNotificationService;
use App\Services\WhatsAppService;
use App\Shared\Enums\CommissionStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class CommissionController extends Controller
{
    private CommissionsRepository $repository;
    private CustomerRepository $customerRepository;
    private DestinationRepository $destinationRepository;
    private CurrentAccountRepository $currentAccountRepository;
    private FcmNotificationService $fcmNotificationService;
    private WhatsAppService $whatsAppService;

    public function __construct()
    {
        $this->repository = app(CommissionsRepository::class);
        $this->customerRepository = app(CustomerRepository::class);
        $this->destinationRepository = app(DestinationRepository::class);
        $this->currentAccountRepository = app(CurrentAccountRepository::class);
        $this->fcmNotificationService = app(FcmNotificationService::class);
        $this->whatsAppService = app(WhatsAppService::class);
    }

    /**
     * Conteo de comisiones por cadete (para el filtro "Cadete" del listado).
     */
    public function cadeteStats(Request $request): JsonResponse
    {
        $user = Auth::user();

        // Vinculación al empleado = el cadete que LEVANTÓ la comisión (pickup_cadete_id),
        // no el que la entregó. El alias se mantiene como cadete_id para el filtro del front.
        $query = \App\Shared\Models\Commission::query()
            ->whereNotNull('pickup_cadete_id')
            ->leftJoin('users', 'users.id', '=', 'commissions.pickup_cadete_id')
            ->selectRaw('commissions.pickup_cadete_id as cadete_id, users.name as cadete_name, COUNT(*) as commission_count')
            ->groupBy('commissions.pickup_cadete_id', 'users.name')
            ->orderByDesc('commission_count');

        // Respetar sucursal del usuario si aplica
        if ($user && $user->branch_id) {
            $query->where('commissions.branch_id', $user->branch_id);
        } elseif ($request->filled('branch_id')) {
            $query->where('commissions.branch_id', $request->integer('branch_id'));
        }

        return response()->json([
            'success' => true,
            'data' => $query->get(),
        ]);
    }

    public function store(CreateCommissionRequest $request): JsonResponse
    {
        try {
            $dto = CreateCommissionDTO::fromArray($request->validated());
            $useCase = new CreateCommissionUseCase(
                $this->repository,
                $this->customerRepository,
                $this->destinationRepository,
                $this->currentAccountRepository,
                $this->fcmNotificationService,
                $this->whatsAppService
            );
            $commission = $useCase($dto);

            return response()->json($commission, 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al crear la comisión',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    public function update(UpdateCommissionRequest $request, int $id): JsonResponse
    {
        try {
            // Obtener la comisión existente
            $existingCommission = $this->repository->findById($id);

            // Obtener datos validados del request
            $validatedData = $request->validated();

            // Combinar datos existentes con los nuevos datos
            $data = [
                'id' => $id,
                'client_id' => $validatedData['client_id'] ?? $existingCommission->client_id,
                'date' => $validatedData['date'] ?? $existingCommission->date->format('Y-m-d'),
                'origin' => $validatedData['origin'] ?? $existingCommission->destination->origin,
                'destination' => $validatedData['destination'] ?? $existingCommission->destination->destination,
                'status' => $validatedData['status'] ?? $existingCommission->status->value,
                'origin_location_id' => $validatedData['origin_location_id'] ?? $existingCommission->origin_location_id,
                'destination_location_id' => $validatedData['destination_location_id'] ?? $existingCommission->destination_location_id,
                'items' => $validatedData['items'] ?? null,
                'total' => $validatedData['total'] ?? $existingCommission->total,
                'notes' => $validatedData['notes'] ?? $existingCommission->notes,
                'a_cuenta' => $validatedData['a_cuenta'] ?? false,
                'type' => $validatedData['type'] ?? $existingCommission->type->value,
            ];

            $dto = UpdateCommissionDTO::fromArray($data);
            $useCase = new UpdateCommissionUseCase(
                $this->repository,
                $this->customerRepository,
                $this->destinationRepository,
                $this->currentAccountRepository
            );
            $commission = $useCase($dto);

            return response()->json($commission, 200);
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'Comisión no encontrada')) {
                return response()->json([
                    'message' => 'Comisión no encontrada',
                    'error' => $e->getMessage(),
                ], 404);
            }

            return response()->json([
                'message' => 'Error al actualizar la comisión',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    public function getStatuses(): JsonResponse
    {
        $statuses = array_map(fn ($status) => [
            'value' => $status->value,
            'label' => $status->getAdminStatus(),
        ], CommissionStatus::cases());

        return response()->json($statuses, 200);
    }

    public function getClientStatuses(): JsonResponse
    {
        $statuses = array_map(fn ($status) => [
            'value' => $status->value,
            'label' => $status->getClienteStatus(),
        ], CommissionStatus::cases());

        return response()->json($statuses, 200);
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $filters = ListCommissionsFiltersDTO::fromArray($request->all());
            $useCase = new ListCommissionsUseCase($this->repository);
            $result = $useCase($filters);

            return response()->json($result, 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener las comisiones',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function show(int $id): JsonResponse
    {
        try {
            $useCase = new GetCommissionUseCase($this->repository);
            $commission = $useCase($id);

            return response()->json([
                'data' => $commission,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 404);
        }
    }

    public function showPublic(int $id): JsonResponse
    {
        try {
            $useCase = new GetCommissionUseCase($this->repository);
            $commission = $useCase($id);

            // Información pública limitada para tracking
            $publicData = [
                'tracking_id' => $commission['id'],
                'tracking_number' => $commission['id'],
                'status' => $commission['status'],
                'status_label' => $commission['status_label'] ?? null,
                'origin' => [
                    'name' => $commission['origin_location']['name'] ?? null,
                    'address' => $commission['origin_location']['address'] ?? null,
                    'city' => $commission['origin_location']['origin'] ?? null,
                    'phone' => $commission['origin_location']['phone'] ?? null,
                    'schedule' => $commission['origin_location']['schedule'] ?? null,
                ],
                'destination' => [
                    'name' => $commission['destination_location']['name'] ?? null,
                    'address' => $commission['destination_location']['address'] ?? null,
                    'city' => $commission['destination_location']['origin'] ?? null,
                    'phone' => $commission['destination_location']['phone'] ?? null,
                    'schedule' => $commission['destination_location']['schedule'] ?? null,
                ],
                'date' => $commission['date'],
                'created_at' => $commission['created_at'],
                'updated_at' => $commission['updated_at'],
                'items_count' => array_sum(array_column($commission['items'] ?? [], 'quantity')),
                'notes' => $commission['notes'] ?? null,
                'logs' => $commission['logs'] ?? [], // Incluir logs para mostrar historial
                'message' => 'Para más información, inicia sesión en tu cuenta de cliente.',
            ];

            return response()->json([
                'success' => true,
                'data' => $publicData,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Comisión no encontrada',
                'message' => 'No se encontró la comisión con el ID proporcionado',
            ], 404);
        }
    }
    public function destroy(int $id): JsonResponse
    {
        try {
            $useCase = new DeleteCommissionUseCase($this->repository);
            $useCase($id);

            return response()->json(['message' => 'Comisión eliminada correctamente']);
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'Comisión no encontrada')) {
                return response()->json(['error' => $e->getMessage()], 404);
            }

            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function updateStatus(int $id, Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'status' => ['required', 'string', Rule::enum(CommissionStatus::class)],
                'details' => ['nullable', 'string'],
                'a_cuenta' => ['nullable', 'boolean'],
            ]);

            $useCase = app(UpdateCommissionStatusUseCase::class);
            $useCase(
                $id,
                CommissionStatus::from($validated['status']),
                $validated['details'] ?? null,
                $validated['a_cuenta'] ?? false
            );

            $commission = $this->repository->findById($id);
            $user = Auth::user();
            $branch = $user->branch;

            return response()->json([
                'message' => 'Estado de la comisión actualizado correctamente',
                'commission' => [
                    'id' => $commission->id,
                    'status' => $commission->status,
                    'branch' => $branch ? [
                        'id' => $branch->id,
                        'name' => $branch->name,
                    ] : null,
                ],
            ]);
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'Comisión no encontrada')) {
                return response()->json(['error' => $e->getMessage()], 404);
            }

            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function updateBranch(int $id, Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'branch_id' => ['required', 'integer', 'exists:branches,id'],
            ]);

            $commission = $this->repository->findById($id);
            if (! $commission) {
                return response()->json([
                    'message' => 'Comisión no encontrada',
                ], 404);
            }

            $oldBranchId = $commission->branch_id;
            $commission->branch_id = $validated['branch_id'];
            $commission->save();

            // Obtener nombre de la sucursal para el log
            $branch = \App\Shared\Models\Branch::find($validated['branch_id']);
            $branchName = $branch ? $branch->name : "ID {$validated['branch_id']}";

            // Crear log de la actualización
            $logDTO = new CreateCommissionLogDTO(
                commissionId: $id,
                userId: Auth::id() ?? 1,
                previousStatus: $commission->status->value,
                newStatus: $commission->status->value,
                details: "Sucursal actualizada de ID {$oldBranchId} a: {$branchName} (ID: {$validated['branch_id']})"
            );
            $this->repository->createLog($logDTO);

            return response()->json([
                'message' => 'Sucursal de la comisión actualizada correctamente',
                'commission' => [
                    'id' => $commission->id,
                    'branch_id' => $commission->branch_id,
                ],
            ]);
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'Comisión no encontrada')) {
                return response()->json(['error' => $e->getMessage()], 404);
            }

            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
