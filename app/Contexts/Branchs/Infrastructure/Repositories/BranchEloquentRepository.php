<?php

namespace App\Contexts\Branchs\Infrastructure\Repositories;

use App\Contexts\Branchs\Application\DTO\CreateBranchDTO;
use App\Contexts\Branchs\Application\DTO\GetBranchesFiltersDTO;
use App\Contexts\Branchs\Application\DTO\UpdateBranchDTO;
use App\Contexts\Branchs\Domain\Repositories\BranchRepository;
use App\Shared\Enums\UserRole;
use App\Shared\Models\Branch;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;

class BranchEloquentRepository implements BranchRepository
{
    public function get(?GetBranchesFiltersDTO $filters = null): array
    {
        $query = Branch::select('id', 'name', 'address', 'schedule', 'phone', 'secondary_phone');

        // Filtrar por sucursal según el rol del usuario
        $user = Auth::user();
        if ($user && $user->branch_id !== null && $user->branch_id > 0) {
            // Si el usuario tiene una sucursal asignada, solo puede ver su propia sucursal
            // (excepto administradores sin sucursal que pueden ver todas)
            $query->where('id', $user->branch_id);
        }

        // Aplicar filtro de búsqueda
        if ($filters && $filters->search) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', '%' . $filters->search . '%')
                  ->orWhere('address', 'like', '%' . $filters->search . '%')
                  ->orWhere('phone', 'like', '%' . $filters->search . '%')
                  ->orWhere('secondary_phone', 'like', '%' . $filters->search . '%');
            });
        }

        // Aplicar ordenamiento
        if ($filters && $filters->sortBy) {
            $allowedSortFields = ['name', 'address', 'phone', 'schedule', 'created_at'];
            if (in_array($filters->sortBy, $allowedSortFields)) {
                $query->orderBy($filters->sortBy, $filters->sortDirection);
            }
        } else {
            $query->orderBy('name', 'asc');
        }

        // Aplicar paginación
        if ($filters) {
            $perPage = min($filters->perPage, 100); // Limitar a máximo 100 por página
            $branches = $query->paginate($perPage, ['*'], 'page', $filters->page);

            return [
                'data' => $branches->items(),
                'pagination' => [
                    'current_page' => $branches->currentPage(),
                    'per_page' => $branches->perPage(),
                    'total' => $branches->total(),
                    'last_page' => $branches->lastPage(),
                    'from' => $branches->firstItem(),
                    'to' => $branches->lastItem(),
                ],
            ];
        }

        // Sin filtros, devolver todas las branches
        return $query->get()->toArray();
    }

    public function findById(int $id): ?Branch
    {
        try {
            return Branch::find($id);
        } catch (QueryException $e) {
            throw new \RuntimeException('Error al buscar la sucursal: ' . $e->getMessage(), 0, $e);
        } catch (Exception $e) {
            throw new \RuntimeException('Error inesperado al buscar la sucursal: ' . $e->getMessage(), 0, $e);
        }
    }

    public function create(CreateBranchDTO $dto): Branch
    {
        try {
            $branch = new Branch();
            $branch->name = $dto->name;
            $branch->address = $dto->address;
            $branch->phone = $dto->phone;
            $branch->schedule = $dto->schedule;
            $branch->secondary_phone = $dto->secondary_phone;
            $branch->save();

            return $branch;
        } catch (QueryException $e) {
            throw new \RuntimeException('Error al crear la sucursal: ' . $e->getMessage(), 0, $e);
        } catch (Exception $e) {
            throw new \RuntimeException('Error inesperado al crear la sucursal: ' . $e->getMessage(), 0, $e);
        }
    }

    public function update(UpdateBranchDTO $dto): Branch
    {
        $branch = $this->findById($dto->id);
        if (! $branch) {
            throw new \RuntimeException('Sucursal no encontrada');
        }

        try {
            $branch->name = $dto->name;
            $branch->address = $dto->address;
            $branch->phone = $dto->phone;
            $branch->schedule = $dto->schedule;
            $branch->secondary_phone = $dto->secondary_phone;
            $branch->save();

            return $branch;
        } catch (QueryException $e) {
            throw new \RuntimeException('Error al actualizar la sucursal: ' . $e->getMessage(), 0, $e);
        } catch (Exception $e) {
            throw new \RuntimeException('Error inesperado al actualizar la sucursal: ' . $e->getMessage(), 0, $e);
        }
    }

    public function delete(int $id): bool
    {
        $branch = $this->findById($id);
        if (! $branch) {
            throw new \RuntimeException('Sucursal no encontrada');
        }

        try {
            return $branch->delete();
        } catch (QueryException $e) {
            throw new \RuntimeException('Error al eliminar la sucursal: ' . $e->getMessage(), 0, $e);
        } catch (Exception $e) {
            throw new \RuntimeException('Error inesperado al eliminar la sucursal: ' . $e->getMessage(), 0, $e);
        }
    }
}
