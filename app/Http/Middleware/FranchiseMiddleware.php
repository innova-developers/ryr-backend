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
        // NO cambiar la conexión si es una ruta del Super Admin
        // Las rutas del Super Admin siempre deben usar la base de datos principal
        if ($request->is('api/super-admin/*')) {
            return $next($request);
        }

        // IMPORTANTE: Intentar detectar la franquicia ANTES de la autenticación
        // para poder configurar la conexión de base de datos antes de que Sanctum
        // intente autenticar al usuario (que puede estar en la DB de la franquicia)
        
        // Primero intentar por parámetro de URL o header (para cuando se abre desde Super Admin)
        $franchiseIdFromRequest = $request->get('franchise_id') ?? $request->header('X-Franchise-Id');
        if ($franchiseIdFromRequest) {
            $franchise = Franchise::on('mysql')->where('id', $franchiseIdFromRequest)
                ->where('is_active', true)
                ->first();
            if ($franchise) {
                // Configurar la conexión ANTES de continuar
                // Esto es CRÍTICO: debe hacerse antes de que Sanctum intente autenticar
                $this->franchiseDatabaseService->setFranchiseConnection($franchise);
                session(['current_franchise_id' => $franchise->id]);
                $request->merge(['current_franchise' => $franchise]);
                
                // Log para debugging
                \Log::info("FranchiseMiddleware: Configurando conexión para franquicia {$franchise->id} antes de autenticación", [
                    'franchise_id' => $franchise->id,
                    'database' => $franchise->database_name,
                    'connection' => config('database.default')
                ]);
                
                return $next($request);
            }
        }
        
        // También intentar por sesión ANTES de la autenticación
        $franchiseIdFromSession = session('current_franchise_id');
        if ($franchiseIdFromSession) {
            $franchise = Franchise::on('mysql')->where('id', $franchiseIdFromSession)
                ->where('is_active', true)
                ->first();
            if ($franchise) {
                // Configurar la conexión ANTES de continuar
                $this->franchiseDatabaseService->setFranchiseConnection($franchise);
                $request->merge(['current_franchise' => $franchise]);
                
                \Log::info("FranchiseMiddleware: Configurando conexión para franquicia {$franchise->id} desde sesión antes de autenticación", [
                    'franchise_id' => $franchise->id,
                    'database' => $franchise->database_name,
                    'connection' => config('database.default')
                ]);
                
                return $next($request);
            }
        }

        // Detectar franquicia por diferentes métodos
        // NOTA: detectFranchise puede usar $request->user() pero solo si ya está autenticado
        // Si no hay franquicia detectada aún, intentar por sesión
        $franchise = $this->detectFranchise($request);

        // Si el usuario autenticado es ADMINISTRADOR_FRANQUICIA, usar su franquicia
        // NOTA: Esto solo funciona si auth:sanctum ya se ejecutó, pero ahora franchise se ejecuta primero
        // así que $request->user() puede estar vacío aquí. Esto se manejará después de la autenticación.
        if (!$franchise && $request->user()) {
            $user = $request->user();
            if ($user->isFranchiseAdmin() && $user->franchise_id) {
                // Usar la conexión principal para buscar la franquicia
                $franchise = \App\Franchise::on('mysql')->find($user->franchise_id);
            }
        }

        if ($franchise && $franchise->isActive()) {
            // Configurar la conexión de base de datos para esta franquicia
            // IMPORTANTE: Esto debe hacerse ANTES de que auth:sanctum intente autenticar
            $this->franchiseDatabaseService->setFranchiseConnection($franchise);
            
            // Guardar en la sesión para futuras peticiones
            session(['current_franchise_id' => $franchise->id]);
            
            // Agregar la franquicia al request para uso posterior
            $request->merge(['current_franchise' => $franchise]);
        }

        // Continuar con el siguiente middleware (que será auth:sanctum)
        // Ahora la conexión de DB está configurada, así que Sanctum buscará el token y usuario en la DB correcta
        $response = $next($request);
        
        // Después de la autenticación, si el usuario tiene franchise_id, asegurar que la conexión esté configurada
        if (!$franchise && $request->user()) {
            $user = $request->user();
            if ($user->isFranchiseAdmin() && $user->franchise_id) {
                $franchise = \App\Franchise::on('mysql')->find($user->franchise_id);
                if ($franchise && $franchise->isActive()) {
                    $this->franchiseDatabaseService->setFranchiseConnection($franchise);
                    session(['current_franchise_id' => $franchise->id]);
                }
            }
        }
        
        return $response;
    }

    /**
     * Detectar la franquicia basándose en diferentes criterios
     */
    private function detectFranchise(Request $request): ?Franchise
    {
        // Siempre usar la conexión principal para buscar franquicias
        // ya que la tabla franchises solo existe en la DB principal
        
        // 1. Por subdominio
        $subdomain = $this->getSubdomain($request);
        if ($subdomain) {
            $franchise = Franchise::on('mysql')->where('subdomain', $subdomain)
                ->where('is_active', true)
                ->first();
            if ($franchise) {
                return $franchise;
            }
        }

        // 2. Por parámetro en la URL
        $franchiseCode = $request->get('franchise') ?? $request->header('X-Franchise-Code');
        if ($franchiseCode) {
            $franchise = Franchise::on('mysql')->where('code', $franchiseCode)
                ->where('is_active', true)
                ->first();
            if ($franchise) {
                return $franchise;
            }
        }

        // 4. Por sesión (si el usuario ya está logueado en una franquicia)
        $franchiseId = session('current_franchise_id');
        if ($franchiseId) {
            $franchise = Franchise::on('mysql')->where('id', $franchiseId)
                ->where('is_active', true)
                ->first();
            if ($franchise) {
                return $franchise;
            }
        }

        // 5. Por el usuario autenticado (si tiene franchise_id en el token)
        if ($request->user() && isset($request->user()->franchise_id) && $request->user()->franchise_id) {
            $franchise = Franchise::on('mysql')->where('id', $request->user()->franchise_id)
                ->where('is_active', true)
                ->first();
            if ($franchise) {
                // Guardar en la sesión para futuras peticiones
                session(['current_franchise_id' => $franchise->id]);
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
