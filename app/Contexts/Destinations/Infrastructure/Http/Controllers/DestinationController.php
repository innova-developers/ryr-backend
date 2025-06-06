<?php

namespace App\Contexts\Destinations\Infrastructure\Http\Controllers;

use App\Contexts\Customers\Application\GetCustomersUseCase;
use App\Contexts\Destinations\Application\CreateDestinationUseCase;
use App\Contexts\Destinations\Application\DeleteDestinationUseCase;
use App\Contexts\Destinations\Application\DTO\CreateDestinationDTO;
use App\Contexts\Destinations\Application\DTO\UpdateDestinationDTO;
use App\Contexts\Destinations\Application\GetDestinationsByOriginUseCase;
use App\Contexts\Destinations\Application\GetDestinationsUseCase;
use App\Contexts\Destinations\Application\GetDestinationUseCase;
use App\Contexts\Destinations\Application\GetOriginsUseCase;
use App\Contexts\Destinations\Application\UpdateDestinationUseCase;
use App\Contexts\Destinations\Domain\Repositories\DestinationRepository;
use App\Contexts\Destinations\Infrastructure\Http\Requests\CreateDestinationRequest;
use App\Contexts\Destinations\Infrastructure\Http\Requests\UpdateDestinationRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class DestinationController extends Controller
{
    public function __construct(
        private readonly DestinationRepository $repository
    ) {
    }

    public function index(): JsonResponse
    {
        $useCase = new GetDestinationsUseCase($this->repository);
        $destinations = $useCase();
        return response()->json($destinations);
    }

    public function store(CreateDestinationRequest $request): JsonResponse
    {
        $useCase = new CreateDestinationUseCase($this->repository);
        $dto = new CreateDestinationDTO(
            $request->input('origin'),
            $request->input('destination'),
            $request->input('fixed_price'),
            $request->input('small_bulk_price'),
            $request->input('large_bulk_price'),
        );
        $destination = $useCase($dto);
        return response()->json($destination, 201);
    }

    public function show(int $id): JsonResponse
    {
        $useCase = new GetDestinationUseCase($this->repository);
        $destination = $useCase($id);
        return response()->json($destination);
    }

    public function update(UpdateDestinationRequest $request, int $id): JsonResponse
    {
        $useCase = new UpdateDestinationUseCase($this->repository);
        $dto = new UpdateDestinationDTO(
            $id,
            $request->input('origin'),
            $request->input('destination'),
            $request->input('fixed_price'),
            $request->input('small_bulk_price'),
            $request->input('large_bulk_price'),
        );
        $updatedDestination = $useCase($dto);
        return response()->json($updatedDestination);
    }

    public function destroy(int $id): JsonResponse
    {
        $useCase = new DeleteDestinationUseCase($this->repository);
        $useCase($id);
        return response()->json(null);
    }

    public function origins(): JsonResponse
    {
        $useCase = new GetOriginsUseCase($this->repository);
        $origins = $useCase();
        return response()->json($origins);
    }

    public function destinations(string $origin): JsonResponse
    {
        $useCase = new GetDestinationsByOriginUseCase($this->repository);
        $destinations = $useCase($origin);
        return response()->json($destinations);
    }
}
