<?php

namespace App\Contexts\Transports\Infrastructure\Http\Controllers;

use App\Contexts\Transports\Application\CreateTransportUseCase;
use App\Contexts\Transports\Application\DeleteTransportUseCase;
use App\Contexts\Transports\Application\DTOs\CreateTransportDTO;
use App\Contexts\Transports\Application\DTOs\UpdateTransportDTO;
use App\Contexts\Transports\Application\ListTransportsUseCase;
use App\Contexts\Transports\Application\UpdateTransportUseCase;
use App\Contexts\Transports\Domain\Repositories\TransportRepository;
use App\Contexts\Transports\Infrastructure\Http\Requests\CreateTransportRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use InvalidArgumentException;

class TransportController extends Controller
{
    public function __construct(
        private readonly TransportRepository $repository,
    ) {
    }

    public function index(): JsonResponse
    {
        try {
            $useCase = new ListTransportsUseCase($this->repository);
            $transports = $useCase();

            return response()->json($transports, 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function store(CreateTransportRequest $request): JsonResponse
    {
        try {
            $dto = new CreateTransportDTO(
                plate: $request->input('plate'),
                description: $request->input('description'),
                phone: $request->input('phone'),
                insurance: $request->input('insurance'),
                usage: $request->input('usage'),
                observation: $request->input('observation')
            );
            $useCase = new CreateTransportUseCase($this->repository);
            $transport = $useCase($dto);

            return response()->json($transport, 201);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function update(CreateTransportRequest $request, int $id): JsonResponse
    {
        try {
            $dto = new UpdateTransportDTO(
                id: $id,
                plate: $request->input('plate'),
                description: $request->input('description'),
                phone: $request->input('phone'),
                insurance: $request->input('insurance'),
                usage: $request->input('usage'),
                observation: $request->input('observation')
            );
            $useCase = new UpdateTransportUseCase($this->repository);
            $transport = $useCase($dto);

            return response()->json($transport);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            $useCase = new DeleteTransportUseCase($this->repository);
            $useCase($id);

            return response()->json(null, 204);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
