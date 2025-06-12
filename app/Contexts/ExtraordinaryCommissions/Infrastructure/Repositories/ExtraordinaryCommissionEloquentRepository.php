<?php

namespace App\Contexts\ExtraordinaryCommissions\Infrastructure\Repositories;


use App\Contexts\ExtraordinaryCommissions\Application\DTO\CreateExtraordinaryCommissionDTO;
use App\Contexts\ExtraordinaryCommissions\Application\DTO\GetExtraordinaryCommissionByOriginAndDestinationDTO;
use App\Contexts\ExtraordinaryCommissions\Application\DTO\UpdateExtraordinaryCommissionDTO;
use App\Contexts\ExtraordinaryCommissions\Domain\Repositories\ExtraordinaryCommissionRepository;
use App\Shared\Models\ExtraordinaryCommission;

class ExtraordinaryCommissionEloquentRepository implements ExtraordinaryCommissionRepository
{

    public function get(): array
    {
        return ExtraordinaryCommission::all()->toArray();
    }

    /**
     * @throws \Exception
     */
    public function create(CreateExtraordinaryCommissionDTO $dto): ExtraordinaryCommission
    {
        try {
            $ExtraordinaryCommission = new ExtraordinaryCommission();
            $ExtraordinaryCommission->origin = $dto->origin;
            $ExtraordinaryCommission->destination = $dto->destination;
            $ExtraordinaryCommission->detail  = $dto->detail;
            $ExtraordinaryCommission->price  = $dto->price;
            $ExtraordinaryCommission->observations  = $dto->observations;
            $ExtraordinaryCommission->save();
            return $ExtraordinaryCommission;
        }catch (\Exception $exception){
            throw new \Exception($exception->getMessage());
        }
    }

    /**
     * @throws \Exception
     */
    public function findById(int $id): ExtraordinaryCommission
    {
        try {
            return ExtraordinaryCommission::find($id);
        }catch (\Exception $exception){
            throw new \Exception($exception->getMessage());
        }
    }

    /**
     * @throws \Exception
     */
    public function update(UpdateExtraordinaryCommissionDTO $dto): ExtraordinaryCommission
    {
        try {
            $ExtraordinaryCommission = ExtraordinaryCommission::find($dto->id);
            $ExtraordinaryCommission->origin = $dto->origin;
            $ExtraordinaryCommission->destination = $dto->destination;
            $ExtraordinaryCommission->detail  = $dto->detail;
            $ExtraordinaryCommission->price  = $dto->price;
            $ExtraordinaryCommission->observations  = $dto->observations;
            $ExtraordinaryCommission->save();
            return $ExtraordinaryCommission;
        }catch (\Exception $exception){
            throw new \Exception($exception->getMessage());
        }
    }

    public function delete(int $id): void
    {
        try {
            $commission = ExtraordinaryCommission::findOrFail($id);
            $commission->delete();
        } catch (\Exception $exception) {
            throw new \RuntimeException('Error al eliminar Comision: ' . $exception->getMessage(), 0, $exception);
        }
    }
    public function findByOriginAndDestination(GetExtraordinaryCommissionByOriginAndDestinationDTO $dto): array
    {
        try {
            return ExtraordinaryCommission::where('origin', $dto->origin)
                ->where('destination', $dto->destination)
                ->get()
                ->toArray();
        } catch (\Exception $exception) {
            throw new \RuntimeException('Error al buscar Comision por origen y destino: ' . $exception->getMessage(), 0, $exception);
        }
    }
}
