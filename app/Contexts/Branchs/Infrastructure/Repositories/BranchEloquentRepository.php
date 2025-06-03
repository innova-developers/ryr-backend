<?php

namespace App\Contexts\Branchs\Infrastructure\Repositories;

use App\Contexts\Branchs\Application\DTO\CreateBranchDTO;
use App\Contexts\Branchs\Application\DTO\UpdateBranchDTO;
use App\Contexts\Branchs\Domain\Repositories\BranchRepository;
use App\Shared\Models\Branch;
use Exception;
use Illuminate\Database\QueryException;

class BranchEloquentRepository implements BranchRepository
{
    public function get(): array
    {
        return Branch::select('id', 'name', 'address', 'schedule', 'phone')->get()->toArray();
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
