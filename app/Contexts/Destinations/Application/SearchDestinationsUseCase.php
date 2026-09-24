<?php

namespace App\Contexts\Destinations\Application;

use App\Contexts\Destinations\Domain\Repositories\DestinationRepository;

class SearchDestinationsUseCase
{
    public const DEFAULT_LIMIT = 50;
    public const MAX_LIMIT = 100;

    private DestinationRepository $repository;

    public function __construct(DestinationRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @return array<int, array{id: int, origin: string, destination: string}>
     */
    public function __invoke(?string $term, ?int $limit = null): array
    {
        $limit = ($limit === null || $limit < 1) ? self::DEFAULT_LIMIT : min($limit, self::MAX_LIMIT);

        return $this->repository->search((string) $term, $limit);
    }
}
