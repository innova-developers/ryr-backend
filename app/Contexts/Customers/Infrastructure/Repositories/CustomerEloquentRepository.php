<?php

namespace App\Contexts\Customers\Infrastructure\Repositories;

use App\Contexts\Customers\Application\DTO\CreateCustomerDTO;
use App\Contexts\Customers\Application\DTO\GetCustomersFiltersDTO;
use App\Contexts\Customers\Application\DTO\UpdateCustomerDTO;
use App\Contexts\Customers\Domain\Repositories\CustomerRepository;
use App\Shared\Enums\CurrentAccountStatus;
use App\Shared\Enums\UserRole;
use App\Shared\Models\Customer;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CustomerEloquentRepository implements CustomerRepository
{
    public function get(?GetCustomersFiltersDTO $filters = null): array
    {
        $query = Customer::select('id', 'dni', 'name', 'email', 'last_name', 'address', 'city', 'phone', 'is_premium', 'user_id', 'observations', 'created_at')
            ->with(['user:id,name']);

        // Filtrar por sucursal según el rol del usuario
        $user = Auth::user();
        if ($user && $user->branch_id) {
            // Cadetes, mostradores y administradores con sucursal solo ven clientes de su sucursal
            if (in_array($user->role, [UserRole::CADETE, UserRole::CADETE_EXTERNO, UserRole::MOSTRADOR, UserRole::ADMINISTRADOR])) {
                $query->where('branch_id', $user->branch_id);
            }
        }

        // Aplicar filtro de búsqueda
        if ($filters && $filters->search) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', '%' . $filters->search . '%')
                  ->orWhere('last_name', 'like', '%' . $filters->search . '%')
                  ->orWhere('email', 'like', '%' . $filters->search . '%')
                  ->orWhere('dni', 'like', '%' . $filters->search . '%')
                  ->orWhere('city', 'like', '%' . $filters->search . '%');
            });
        }

        // Si resume=true, filtrar solo clientes con saldo != 0 en el rango de fechas
        if ($filters && $filters->resume) {
            // Construir parámetros para la subconsulta
            $dateConditions = '';
            $bindings = [CurrentAccountStatus::OK->value];
            
            if ($filters->dateFrom) {
                $dateConditions .= ' AND ca2.transaction_date >= ?';
                $bindings[] = $filters->dateFrom;
            }
            if ($filters->dateTo) {
                $dateConditions .= ' AND ca2.transaction_date <= ?';
                $bindings[] = $filters->dateTo;
            }
            
            // Filtrar clientes que tengan un balance != 0 en el último registro del rango de fechas
            $query->whereIn('customers.id', function ($subquery) use ($filters, $dateConditions, $bindings) {
                $subquery->select('ca1.customer_id')
                    ->from('current_accounts as ca1')
                    ->where('ca1.status', CurrentAccountStatus::OK->value)
                    ->where('ca1.balance', '!=', 0);
                
                // Aplicar filtro de rango de fechas si se proporciona
                if ($filters->dateFrom) {
                    $subquery->where('ca1.transaction_date', '>=', $filters->dateFrom);
                }
                if ($filters->dateTo) {
                    $subquery->where('ca1.transaction_date', '<=', $filters->dateTo);
                }
                
                // Filtrar solo el último registro por cliente en el rango de fechas
                $subquery->whereRaw("ca1.id = (
                    SELECT ca2.id 
                    FROM current_accounts ca2 
                    WHERE ca2.customer_id = ca1.customer_id 
                    AND ca2.status = ?
                    {$dateConditions}
                    ORDER BY ca2.transaction_date DESC, ca2.id DESC 
                    LIMIT 1
                )", $bindings);
            });
        }

        // Aplicar ordenamiento
        if ($filters && $filters->sortBy) {
            $allowedSortFields = ['name', 'last_name', 'email', 'dni', 'city', 'created_at', 'is_premium'];
            if (in_array($filters->sortBy, $allowedSortFields)) {
                $query->orderBy($filters->sortBy, $filters->sortDirection);
            }
        } else {
            $query->orderBy('name', 'asc');
        }

        // Aplicar paginación
        if ($filters) {
            $perPage = $filters->perPage;
            $customers = $query->paginate($perPage, ['*'], 'page', $filters->page);

            return [
                'data' => $customers->map(function (Customer $customer) {
                    return [
                        'id' => $customer->id,
                        'dni' => $customer->dni,
                        'name' => $customer->name,
                        'email' => $customer->email,
                        'last_name' => $customer->last_name,
                        'address' => $customer->address,
                        'city' => $customer->city,
                        'phone' => $customer->phone,
                        'is_premium' => $customer->is_premium,
                        'observations' => $customer->observations,
                        'user' => optional($customer->user),
                        'branch' => optional($customer->branch),
                        'balance' => $customer->current_balance,
                        'created_at' => $customer->created_at,
                    ];
                })->toArray(),
                'pagination' => [
                    'current_page' => $customers->currentPage(),
                    'per_page' => $customers->perPage(),
                    'total' => $customers->total(),
                    'last_page' => $customers->lastPage(),
                    'from' => $customers->firstItem(),
                    'to' => $customers->lastItem(),
                ],
            ];
        }

        // Sin filtros, devolver todos los customers
        return $query->get()
            ->map(function (Customer $customer) {
                return [
                    'id' => $customer->id,
                    'dni' => $customer->dni,
                    'name' => $customer->name,
                    'email' => $customer->email,
                    'last_name' => $customer->last_name,
                    'address' => $customer->address,
                    'city' => $customer->city,
                    'phone' => $customer->phone,
                    'is_premium' => $customer->is_premium,
                    'observations' => $customer->observations,
                    'user' => optional($customer->user),
                    'branch' => optional($customer->branch),
                    'balance' => $customer->current_balance,
                    'created_at' => $customer->created_at,
                ];
            })
            ->toArray();
    }

    public function create(CreateCustomerDTO $dto): Customer
    {
        try {
            $customer = new Customer();
            $customer->dni = $dto->dni;
            $customer->name = $dto->name;
            $customer->last_name = $dto->lastName;
            $customer->mobile = $dto->mobile;
            $customer->email = $dto->email;
            $customer->address = $dto->address;
            $customer->city = $dto->city;
            $customer->phone = $dto->phone;
            $customer->maps_url = $dto->mapsUrl;
            $customer->business_hours = $dto->businessHours;
            $customer->observations = $dto->observations;
            $customer->is_premium = $dto->isPremium;
            $customer->user_id = $dto->userId;
            $customer->branch_id = $dto->branchId;
            $customer->save();

            return $customer;
        } catch (\Exception $exception) {
            throw new \RuntimeException('Error al crear Cliente: ' . $exception->getMessage(), 0, $exception);
        }
    }

    public function findById(int $id): ?Customer
    {
        try {
            return Customer::find($id);
        } catch (\Exception $exception) {
            throw new \RuntimeException('Error al buscar Cliente por ID: ' . $exception->getMessage(), 0, $exception);
        }
    }

    public function update(UpdateCustomerDTO $dto): Customer
    {
        try {
            $customer = Customer::findOrFail($dto->id);
            
            // Guardar el email anterior para comparar
            $oldEmail = $customer->email;
            
            $customer->dni = $dto->dni;
            $customer->name = $dto->name;
            $customer->last_name = $dto->lastName;
            $customer->mobile = $dto->mobile;
            $customer->email = $dto->email;
            $customer->address = $dto->address;
            $customer->city = $dto->city;
            $customer->phone = $dto->phone;
            $customer->maps_url = $dto->mapsUrl;
            $customer->business_hours = $dto->businessHours;
            $customer->observations = $dto->observations;
            $customer->is_premium = $dto->isPremium;
            $customer->user_id = $dto->userId;
            $customer->branch_id = $dto->branchId;
            $customer->internal_user_id = $dto->internalUserId;
            $customer->save();

            // Si el email cambió, actualizar también el usuario asociado
            if ($oldEmail !== $dto->email && $customer->user_id) {
                $user = \App\Shared\Models\User::find($customer->user_id);
                if ($user && $user->role === \App\Shared\Enums\UserRole::CLIENTE) {
                    $user->email = $dto->email;
                    $user->save();
                }
            }

            return $customer;
        } catch (ModelNotFoundException $exception) {
            throw $exception;
        } catch (QueryException $exception) {
            if ($exception->errorInfo[1] === 1062) {
                if (str_contains($exception->getMessage(), 'customers_dni_unique')) {
                    throw new \RuntimeException('El DNI ingresado ya está registrado en otro cliente.');
                }
                if (str_contains($exception->getMessage(), 'customers_email_unique')) {
                    throw new \RuntimeException('El email ingresado ya está registrado en otro cliente.');
                }
                throw new \RuntimeException('Ya existe un cliente con esos datos.');
            }
            throw new \RuntimeException('Error al actualizar Cliente: ' . $exception->getMessage());
        } catch (\Exception $exception) {
            throw new \RuntimeException('Error al actualizar Cliente: ' . $exception->getMessage());
        }
    }

    public function delete(int $id): void
    {
        try {
            $customer = Customer::findOrFail($id);
            $customer->delete();
        } catch (\Exception $exception) {
            throw new \RuntimeException('Error al eliminar Cliente: ' . $exception->getMessage(), 0, $exception);
        }
    }

    public function search(string $query): array
    {
        $searchQuery = Customer::select('id', 'dni', 'name', 'email', 'last_name', 'address', 'city', 'phone', 'is_premium', 'user_id', 'observations', 'created_at')
            ->with(['user:id,name']);

        // Filtrar por sucursal según el rol del usuario
        $user = Auth::user();
        if ($user && $user->branch_id) {
            // Cadetes, mostradores y administradores con sucursal solo ven clientes de su sucursal
            if (in_array($user->role, [UserRole::CADETE, UserRole::CADETE_EXTERNO, UserRole::MOSTRADOR, UserRole::ADMINISTRADOR])) {
                $searchQuery->where('branch_id', $user->branch_id);
            }
        }

        return $searchQuery->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('last_name', 'like', "%{$query}%")
                    ->orWhere('email', 'like', "%{$query}%")
                    ->orWhere('dni', 'like', "%{$query}%");
            })
            ->get()
            ->map(function (Customer $customer) {
                return [
                    'id' => $customer->id,
                    'dni' => $customer->dni,
                    'name' => $customer->name,
                    'email' => $customer->email,
                    'last_name' => $customer->last_name,
                    'address' => $customer->address,
                    'city' => $customer->city,
                    'phone' => $customer->phone,
                    'is_premium' => $customer->is_premium,
                    'observations' => $customer->observations,
                    'user' => optional($customer->user),
                    'branch' => optional($customer->branch),
                    'balance' => $customer->current_balance,
                    'created_at' => $customer->created_at,
                ];
            })
            ->toArray();
    }
}
