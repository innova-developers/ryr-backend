<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Franchise;
use App\Services\FranchiseDatabaseService;

class FranchiseMiddleware
{
    protected $franchiseDatabaseService;

    public function __construct(FranchiseDatabaseService $franchiseDatabaseService)
    {
        $this->franchiseDatabaseService = $franchiseDatabaseService;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Detectar franquicia por diferentes métodos
        $franchise = $this->detectFranchise($request);

        if ($franchise && $franchise->isActive()) {
            // Configurar la conexión de base de datos para esta franquicia
            $this->franchiseDatabaseService->setFranchiseConnection($franchise);
            
            // Agregar la franquicia al request para uso posterior
            $request->merge(['current_franchise' => $franchise]);
        }

        return $next($request);
    }

    /**
     * Detectar la franquicia basándose en diferentes criterios
     */
    private function detectFranchise(Request $request): ?Franchise
    {
        // 1. Por subdominio
        $subdomain = $this->getSubdomain($request);
        if ($subdomain) {
            $franchise = Franchise::where('subdomain', $subdomain)
                ->where('is_active', true)
                ->first();
            if ($franchise) {
                return $franchise;
            }
        }

        // 2. Por dominio completo
        $host = $request->getHost();
        $franchise = Franchise::where('domain', $host)
            ->where('is_active', true)
            ->first();
        if ($franchise) {
            return $franchise;
        }

        // 3. Por parámetro en la URL
        $franchiseCode = $request->get('franchise') ?? $request->header('X-Franchise-Code');
        if ($franchiseCode) {
            $franchise = Franchise::where('code', $franchiseCode)
                ->where('is_active', true)
                ->first();
            if ($franchise) {
                return $franchise;
            }
        }

        // 4. Por sesión (si el usuario ya está logueado en una franquicia)
        $franchiseId = session('current_franchise_id');
        if ($franchiseId) {
            $franchise = Franchise::where('id', $franchiseId)
                ->where('is_active', true)
                ->first();
            if ($franchise) {
                return $franchise;
            }
        }

        return null;
    }

    /**
     * Extraer subdominio de la URL
     */
    private function getSubdomain(Request $request): ?string
    {
        $host = $request->getHost();
        $parts = explode('.', $host);
        
        // Si hay más de 2 partes, el primero es el subdominio
        if (count($parts) > 2) {
            return $parts[0];
        }
        
        return null;
    }
}
