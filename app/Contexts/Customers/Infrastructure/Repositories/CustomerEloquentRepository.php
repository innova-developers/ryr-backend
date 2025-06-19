<?php

namespace App\Contexts\Customers\Infrastructure\Repositories;

use App\Contexts\Customers\Application\DTO\CreateCustomerDTO;
use App\Contexts\Customers\Application\DTO\UpdateCustomerDTO;
use App\Contexts\Customers\Domain\Repositories\CustomerRepository;
use App\Shared\Models\Customer;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;

class CustomerEloquentRepository implements CustomerRepository
{
    public function get(): array
    {
        return Customer::select('id', 'dni', 'name', 'email', 'last_name', 'address', 'city', 'phone', 'is_premium', 'user_id', 'created_at')
            ->with(['user:id,name'])
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
                    'user' => optional($customer->user),
                    'branch' => optional($customer->branch),
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
            $customer->branch_id = Auth::user()->branch_id;
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
        } catch (ModelNotFoundException $exception) {
            throw $exception;
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
        return Customer::select('id', 'dni', 'name', 'email', 'last_name', 'address', 'city', 'phone', 'is_premium', 'user_id', 'created_at')
            ->with(['user:id,name'])
            ->where(function ($q) use ($query) {
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
                    'user' => optional($customer->user),
                    'branch' => optional($customer->branch),
                    'created_at' => $customer->created_at,
                ];
            })
            ->toArray();
    }
}
