<?php

namespace Tests\Unit\Destinations;

use App\Contexts\Destinations\Application\SearchDestinationsUseCase;
use App\Contexts\Destinations\Domain\Repositories\DestinationRepository;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * RC-550 — El modo liviano de GET /destinations nunca debe pedir al repositorio más de
 * 100 filas: el listado completo son 25.899 filas y 6 MB en prod.
 */
class SearchDestinationsUseCaseTest extends TestCase
{
    /**
     * Pasa el texto tal cual y aplica el tope por defecto (50) o el pedido, acotado a 100.
     */
    #[DataProvider('limites')]
    public function test_acota_el_limite_antes_de_ir_al_repositorio(?int $pedido, int $esperado): void
    {
        $repository = Mockery::mock(DestinationRepository::class);
        $repository->shouldReceive('search')->once()->with('rosario', $esperado)->andReturn([]);
        $repository->shouldNotReceive('get');

        $resultado = (new SearchDestinationsUseCase($repository))('rosario', $pedido);

        $this->assertSame([], $resultado);
    }

    public static function limites(): array
    {
        return [
            'sin limite usa 50' => [null, 50],
            'cero usa 50' => [0, 50],
            'negativo usa 50' => [-5, 50],
            'pedido dentro del tope' => [10, 10],
            'justo el tope' => [100, 100],
            'arriba del tope se acota a 100' => [500, 100],
        ];
    }

    /**
     * Un texto nulo se busca como vacío (el repositorio devuelve las primeras filas acotadas).
     */
    public function test_texto_nulo_se_busca_como_vacio(): void
    {
        $repository = Mockery::mock(DestinationRepository::class);
        $repository->shouldReceive('search')->once()->with('', 50)->andReturn([]);

        (new SearchDestinationsUseCase($repository))(null);

        $this->addToAssertionCount(1);
    }
}
