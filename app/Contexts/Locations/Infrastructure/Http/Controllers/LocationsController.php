<?php

namespace App\Contexts\Locations\Infrastructure\Http\Controllers;

use App\Contexts\Locations\Application\CreateLocationUseCase;
use App\Contexts\Locations\Application\DeleteLocationUseCase;
use App\Contexts\Locations\Application\DTOs\CreateLocationDTO;
use App\Contexts\Locations\Application\DTOs\UpdateLocationDTO;
use App\Contexts\Locations\Application\ListLocationsUseCase;
use App\Contexts\Locations\Application\UpdateLocationUseCase;
use App\Contexts\Locations\Domain\Repositories\LocationsRepository;
use App\Contexts\Locations\Infrastructure\Http\Requests\CreateLocationRequest;
use App\Contexts\Locations\Infrastructure\Http\Requests\UpdateLocationRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

class LocationsController extends Controller
{
    public function __construct(
        private readonly LocationsRepository $repository
    ) {}

    public function index(): JsonResponse
    {
        $useCase = new ListLocationsUseCase($this->repository);
        $locations = $useCase();
        return response()->json($locations);
    }

    public function store(CreateLocationRequest $request): JsonResponse
    {
        $dto = new CreateLocationDTO(
            name: $request->input('name'),
            address: $request->input('address'),
            origin: $request->input('origin'),
            phone: $request->input('phone'),
            map: $request->input('map'),
            schedule: $request->input('schedule'),
            observation: $request->input('observation')
        );
        $useCase = new CreateLocationUseCase($this->repository);
        $location = $useCase($dto);
        return response()->json($location, 201);
    }

    public function update(UpdateLocationRequest $request, int $id): JsonResponse
    {
        $useCase = new UpdateLocationUseCase($this->repository);
        $dto = new UpdateLocationDTO(
            $id,
            name: $request->input('name'),
            address: $request->input('address'),
            origin: $request->input('origin'),
            phone: $request->input('phone'),
            map: $request->input('map'),
            schedule: $request->input('schedule'),
            observation: $request->input('observation')
        );
        $location = $useCase($dto);
        return response()->json($location);
    }

    public function destroy(int $id): JsonResponse
    {
        $useCase = new DeleteLocationUseCase($this->repository);
        $useCase($id);
        return response()->json(null, 204);
    }
}
