<?php

namespace App\Contexts\Destinations\Domain\Repositories;


use App\Contexts\Destinations\Application\DTO\CreateDestinationDTO;
use App\Contexts\Destinations\Application\DTO\UpdateDestinationDTO;
use App\Shared\Models\Destination;

interface DestinationRepository
{
    public function get():array;
    public function create(CreateDestinationDTO $dto):Destination;
    public function findById(int $id): Destination;
    public function update(UpdateDestinationDTO $dto):Destination;
    public function delete(int $id):void;
    public function getOrigins():array;
    public function getDestinationsByOrigin(string $origin):array;
}
