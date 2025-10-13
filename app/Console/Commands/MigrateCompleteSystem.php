<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use App\Shared\Enums\UserRole;
use App\Shared\Models\Branch;
use App\Shared\Models\Customer;
use App\Shared\Models\User;
use App\Shared\Models\Location;
use App\Shared\Models\Transport;
use App\Shared\Models\Commission;
use App\Shared\Models\CommissionItem;
use App\Shared\Models\CommissionLog;
use App\Shared\Models\Destination;
use App\Shared\Models\ExpenseCategory;
use App\Shared\Models\IncomeCategory;
use App\Shared\Models\Expense;
use App\Shared\Models\Income;
use App\Shared\Models\CurrentAccount;
use App\Shared\Enums\CommissionStatus;
use App\Shared\Enums\CommissionItemType;
use App\Shared\Enums\CommissionItemSize;
use App\Services\GoogleMapsService;

class MigrateCompleteSystem extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:complete-system 
                            {--dry-run : Ejecutar en modo de prueba sin hacer cambios}
                            {--force : Forzar la migración sin confirmación}
                            {--skip-clear : Omitir limpieza de base de datos}
                            {--commission-from=1 : ID de comisión inicial a migrar}
                            {--commission-to=0 : ID de comisión final a migrar (0 = todas)}
                            {--batch-size=100 : Tamaño del lote para procesamiento}
                            {--locations-batch=10 : Tamaño del lote para ubicaciones (con coordenadas)}
                            {--skip-coordinates : Omitir cálculo de coordenadas (migrar sin lat/lng)}
                            {--coordinates-only : Solo calcular coordenadas para ubicaciones existentes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrar todo el sistema desde la base de datos vieja';
    
    // Variables de configuración para procesamiento por lotes
    private int $commissionFrom = 1;
    private int $commissionTo = 0;
    private int $batchSize = 100;
    private int $locationsBatchSize = 10;
    private bool $skipCoordinates = false;
    private bool $coordinatesOnly = false;

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 Iniciando migración completa del sistema...');
        
        $dryRun   = (bool) $this->option('dry-run');
        $force    = (bool) $this->option('force');
        $skipClear= (bool) $this->option('skip-clear');
        
        // Configurar variables de procesamiento por lotes
        $this->commissionFrom = (int) $this->option('commission-from');
        $this->commissionTo = (int) $this->option('commission-to');
        $this->batchSize = (int) $this->option('batch-size');
        $this->locationsBatchSize = (int) $this->option('locations-batch');
        $this->skipCoordinates = (bool) $this->option('skip-coordinates');
        $this->coordinatesOnly = (bool) $this->option('coordinates-only');
        
        $this->info("📊 Configuración de lotes:");
        $this->info("   - Comisiones: {$this->commissionFrom} a " . ($this->commissionTo > 0 ? $this->commissionTo : 'todas'));
        $this->info("   - Tamaño de lote: {$this->batchSize}");
        $this->info("   - Lote de ubicaciones: {$this->locationsBatchSize}");
        $this->info("   - Coordenadas: " . ($this->skipCoordinates ? 'OMITIDAS' : ($this->coordinatesOnly ? 'SOLO COORDENADAS' : 'INCLUIDAS')));
        
        if ($dryRun) {
            $this->warn('⚠️  MODO DE PRUEBA ACTIVADO - No se realizarán cambios reales');
        }

        // Confirmar migración si no está en modo force
        if (!$force && !$dryRun) {
            if (!$this->confirm('¿Estás seguro de que quieres proceder con la migración completa? Esto puede tomar varios minutos.')) {
                $this->info('Migración cancelada.');
                return 0;
            }
        }

        try {
            // Verificar conexión a base de datos vieja
            if (!$this->checkOldDatabaseConnection()) {
                $this->error('❌ No se puede conectar a la base de datos vieja (mysql_old)');
                return 1;
            }

            $this->info('✅ Conexión a base de datos vieja establecida');

            // Limpiar base de datos si no se omite
            if (!$skipClear && !$dryRun) {
                $this->clearDatabase();
            }

            // Ejecutar migración en orden correcto
            $this->executeMigrationSteps($dryRun);
            
            $this->info('🎉 ¡Migración completa del sistema finalizada exitosamente!');
            
            if ($dryRun) {
                $this->warn('⚠️  Recuerda que esto fue solo una prueba. Ejecuta sin --dry-run para hacer la migración real.');
            }

        } catch (\Throwable $e) {
            $this->error('❌ Error durante la migración: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }

    /**
     * Verificar conexión a la base de datos vieja
     */
    private function checkOldDatabaseConnection(): bool
    {
        try {
            DB::connection('mysql_old')->getPdo();
            return true;
        } catch (\Throwable $e) {
            $this->error('Error de conexión: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Limpiar base de datos
     */
    private function clearDatabase(): void
    {
        $this->info('🧹 Limpiando base de datos...');
        
        try {
            // Desactivar foreign key checks temporalmente (MySQL)
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            
            // Limpiar tablas en orden inverso de dependencias
            DB::table('commission_logs')->truncate();
            DB::table('commission_items')->truncate();
            DB::table('commissions')->truncate();
            DB::table('current_accounts')->truncate();
            DB::table('expenses')->truncate();
            DB::table('incomes')->truncate();
            DB::table('destinations')->truncate();
            DB::table('transports')->truncate();
            DB::table('locations')->truncate();
            DB::table('customers')->truncate();
            DB::table('users')->truncate();
            DB::table('branches')->truncate();
            
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
            
            $this->info('   ✅ Base de datos limpiada');
        } catch (\Throwable $e) {
            // Intentar reactivar por seguridad
            try { DB::statement('SET FOREIGN_KEY_CHECKS=1'); } catch (\Throwable $ignored) {}
            $this->warn("   ⚠️  Error limpiando base de datos: {$e->getMessage()}");
        }
    }

    /**
     * Ejecutar pasos de migración en orden correcto
     */
    private function executeMigrationSteps(bool $dryRun): void
    {
        // Si es solo coordenadas, solo ejecutar ese paso
        if ($this->coordinatesOnly) {
            $this->info("📋 Solo calculando coordenadas para ubicaciones existentes...");
            try {
                $this->migrateLocationCoordinatesOnly($dryRun);
                $this->info("   ✅ Cálculo de coordenadas completado");
            } catch (\Throwable $e) {
                $this->warn("   ⚠️  Error calculando coordenadas: {$e->getMessage()}");
            }
            return;
        }

        $steps = [
            '1. Migrar sucursales' => [$this, 'migrateBranches'],
            '2. Crear usuario por defecto' => [$this, 'createDefaultUser'],
            '3. Migrar categorías' => [$this, 'migrateCategories'],
            '4. Migrar destinos' => [$this, 'migrateDestinations'],
            '5. Migrar usuarios' => [$this, 'migrateUsers'],
            '6. Migrar clientes' => [$this, 'migrateCustomers'],
            '7. Migrar ubicaciones' => [$this, 'migrateLocations'],
            '8. Migrar transportes' => [$this, 'migrateTransports'],
            '9. Migrar comisiones' => [$this, 'migrateCommissions'],
            '10. Migrar gastos' => [$this, 'migrateExpenses'],
            '11. Migrar ingresos' => [$this, 'migrateIncomes'],
            '12. Migrar cuentas corrientes' => [$this, 'migrateCurrentAccounts'],
            '13. Actualizar destinos de comisiones' => [$this, 'updateCommissionDestinations'],
            '14. Corregir campos de comisiones' => [$this, 'fixCommissionFields'],
            '15. Actualizar totales de comisiones' => [$this, 'updateCommissionTotals'],
            '16. Corregir campos de clientes' => [$this, 'fixCustomersFields'],
        ];

        foreach ($steps as $stepName => $stepMethod) {
            $this->info("📋 {$stepName}...");
            try {
                $stepMethod($dryRun);
                $this->info("   ✅ {$stepName} completado");
            } catch (\Throwable $e) {
                $this->warn("   ⚠️  Error en {$stepName}: {$e->getMessage()}");
            }
        }
    }

    /**
     * Crear usuario por defecto
     */
    private function createDefaultUser(bool $dryRun): void
    {
        if ($dryRun) { return; }

        DB::transaction(function () {
            $existingUser = DB::table('users')->where('id', 1)->first();
            
            if (!$existingUser) {
                DB::table('users')->insert([
                    'id' => 1,
                    'name' => 'Administrador Sistema',
                    'email' => 'admin@ryrcomisiones.com',
                    'password' => Hash::make('password123'),
                    'role' => UserRole::ADMINISTRADOR->value,
                    'branch_id' => 1,
                    'base_salary' => 0,
                    'income_percentage' => 0,
                    'commission_percentage' => 0,
                    'contract_type' => 'fixed_salary',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }

    /**
     * Migrar sucursales
     */
    private function migrateBranches(bool $dryRun): void
    {
        if ($dryRun) { return; }

        DB::transaction(function () {
             DB::table('branches')->updateOrInsert(
                 ['id' => 1],
                 [
                     'name' => 'Sucursal Principal',
                     'address' => 'Dirección principal',
                     'phone' => '000-000-0000',
                     'created_at' => now(),
                     'updated_at' => now(),
                 ]
             );
        });
    }

    /**
     * Migrar categorías
     */
    private function migrateCategories(bool $dryRun): void
    {
        if ($dryRun) { return; }

        DB::transaction(function () {
            // Categorías de gastos
            $expenseCategories = [
                ['id' => 9, 'name' => 'Oficina'],
                ['id' => 10, 'name' => 'Servicios profesionales'],
                ['id' => 11, 'name' => 'Servicios de Telecomunicaciones'],
                ['id' => 12, 'name' => 'Inmueble'],
                ['id' => 13, 'name' => 'Impuestos'],
                ['id' => 14, 'name' => 'Servicios básicos'],
                ['id' => 15, 'name' => 'Retiros personales'],
                ['id' => 18, 'name' => 'combustible'],
                ['id' => 19, 'name' => 'seguros'],
                ['id' => 22, 'name' => 'VIATICOS'],
                ['id' => 23, 'name' => 'FLUIDOS PARA VEHICULOS'],
                ['id' => 24, 'name' => 'GALASSI COMPRAS'],
                ['id' => 25, 'name' => 'PUBLICIDAD'],
                ['id' => 27, 'name' => 'REPUESTO'],
                ['id' => 28, 'name' => 'HABILITACIÓN'],
                ['id' => 29, 'name' => 'GOMERIA'],
                ['id' => 30, 'name' => 'ARREGLOS GENERALES'],
                ['id' => 33, 'name' => 'SERVICIOS TRANSP. TERCERO'],
                ['id' => 34, 'name' => 'DARIO SARRU BOMBISTA'],
                ['id' => 35, 'name' => 'ROTURAS/gastos'],
                ['id' => 36, 'name' => 'SERVIS SINERAY'],
                ['id' => 37, 'name' => 'SERVICIO DE CARGA/DESCARGA GELMINI'],
                ['id' => 38, 'name' => 'test'],
            ];

            foreach ($expenseCategories as $category) {
                DB::table('expense_categories')->updateOrInsert(
                    ['id' => $category['id']],
                    [
                        'name' => $category['name'],
                        'description' => 'Categoría migrada del sistema viejo',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }

            // Categorías de ingresos
            DB::table('income_categories')->updateOrInsert(
                ['id' => 16],
                [
                    'name' => 'ventas',
                    'description' => 'Categoría de ingresos por ventas migrada del sistema viejo',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        });
    }

    /**
     * Migrar destinos (cartesiano de ciudades únicas de locaciones)
     */
    private function migrateDestinations(bool $dryRun): void
    {
        if ($dryRun) { return; }

        DB::transaction(function () {
            // Obtener ciudades únicas sin cargar todo en memoria
            $cities = DB::connection('mysql_old')
                ->table('locaciones')
                ->select('localidad')
                ->whereNotNull('localidad')
                ->distinct()
                ->pluck('localidad')
                ->map(fn ($c) => $this->normalizeCityName($c))
                ->unique()
                ->values()
                ->all();

            $destinations = [];
            foreach ($cities as $origin) {
                foreach ($cities as $destination) {
                    if ($origin !== $destination) {
                        $destinations[] = [
                            'origin' => $origin,
                            'destination' => $destination,
                            'fixed_price' => 0.00,
                            'small_bulk_price' => 0.00,
                            'large_bulk_price' => 0.00,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                }
            }

            // Inserción por lotes
            foreach (array_chunk($destinations, 1000) as $chunk) {
                DB::table('destinations')->insert($chunk);
            }
        });
    }

    /**
     * Migrar usuarios
     */
    private function migrateUsers(bool $dryRun): void
    {
        if ($dryRun) { return; }

        DB::connection('mysql_old')
            ->table('usuario')
            ->orderBy('idusuario')
            ->chunk(1000, function ($oldEmployees) {
                DB::transaction(function () use ($oldEmployees) {
                    foreach ($oldEmployees as $oldEmployee) {
                        DB::table('users')->updateOrInsert(
                            ['id' => (int)$oldEmployee->idusuario + 1],
                            [
                                'name' => $oldEmployee->usuario,
                                'email' => $oldEmployee->usuario."@ryrcomisiones.com",
                                'password' => Hash::make($oldEmployee->clave),
                                'role' => $this->mapUserRole($oldEmployee->rol ?? 'empleado'),
                                'branch_id' => 1,
                                'base_salary' => (float)($oldEmployee->sueldo ?? 0),
                                'income_percentage' => 0,
                                'commission_percentage' => 0,
                                'contract_type' => 'fixed_salary',
                                'created_at' =>  now(),
                                'updated_at' => now(),
                            ]
                        );
                    }
                });
            });
    }

    /**
     * Migrar clientes
     */
    private function migrateCustomers(bool $dryRun): void
    {
        if ($dryRun) { return; }

         DB::connection('mysql_old')
             ->table('clientes')
             ->orderBy('idclientes')
             ->chunk(1000, function ($oldCustomers) {
                 DB::transaction(function () use ($oldCustomers) {
                     foreach ($oldCustomers as $oldCustomer) {
                         // Obtener la ciudad y dirección desde locaciones usando el campo direccion como ID
                         $city = 'CIUDAD'; // Valor por defecto
                         $address = 'Dirección'; // Valor por defecto
                         
                         if ($oldCustomer->direccion) {
                             $location = DB::connection('mysql_old')
                                 ->table('locaciones')
                                 ->where('idlocaciones', $oldCustomer->direccion)
                                 ->first();
                             
                             if ($location) {
                                 if ($location->localidad) {
                                     $city = $this->normalizeCityName($location->localidad);
                                 }
                                 if ($location->direccion) {
                                     $address = $location->direccion;
                                 }
                             }
                         }
                         
                         DB::table('customers')->updateOrInsert(
                             ['id' => (int)$oldCustomer->idclientes],
                             [
                                 'dni' => (string)$oldCustomer->idclientes,
                                 'name' => $oldCustomer->nombre,
                                 'last_name' => $oldCustomer->apellido,
                                 'mobile' => $oldCustomer->telefono,
                                 'email' => $oldCustomer->correo ?: "cliente-{$oldCustomer->idclientes}@ryrcomisiones.com",
                                 'address' => $address,
                                 'city' => $city,
                                 'phone' => $oldCustomer->telefono,
                                 'maps_url' => null,
                                 'business_hours' => null,
                                 'observations' => null,
                                 'is_premium' => 0,
                                 'user_id' => 1,
                                 'branch_id' => 1,
                                 'created_at' => $oldCustomer->fechacreacion ?? now(),
                                 'updated_at' => now(),
                             ]
                         );
                     }
                 });
             });
    }

    /**
     * Migrar ubicaciones
     */
    private function migrateLocations(bool $dryRun): void
    {
        if ($dryRun) { return; }

        $this->info("   📍 Procesando ubicaciones en lotes de {$this->locationsBatchSize}...");
        
        $totalProcessed = 0;
        $googleMapsService = null;
        
        // Solo inicializar GoogleMapsService si no se omiten coordenadas
        if (!$this->skipCoordinates) {
            $googleMapsService = new GoogleMapsService();
        }
        
        DB::connection('mysql_old')
            ->table('locaciones')
            ->orderBy('idlocaciones')
            ->chunk($this->locationsBatchSize, function ($oldLocations) use (&$totalProcessed, $googleMapsService) {
                DB::transaction(function () use ($oldLocations, &$totalProcessed, $googleMapsService) {
                    foreach ($oldLocations as $oldLocation) {
                        $totalProcessed++;
                        
                        if ($totalProcessed % 10 == 0) {
                            $this->info("   📍 Procesadas: {$totalProcessed} ubicaciones...");
                        }
                        
                        $address = $oldLocation->direccion ?? 'Dirección';
                        $city = $this->normalizeCityName($oldLocation->localidad ?? 'CIUDAD');
                        
                        $locationData = [
                            'name' => $oldLocation->nombre ?? 'Ubicación',
                            'origin' => $city,
                            'address' => $address,
                            'phone' => $oldLocation->telefono ?? null,
                            'map' => substr((string)($oldLocation->mapa ?? ''), 0, 255),
                            'schedule' => $oldLocation->mapa ?? '',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                        
                        // Calcular coordenadas solo si no se omiten
                        if (!$this->skipCoordinates && $googleMapsService) {
                            try {
                                $coordinates = $googleMapsService->getCoordinates($address, $city);
                                $locationData['latitude'] = $coordinates['latitude'] ?? null;
                                $locationData['longitude'] = $coordinates['longitude'] ?? null;
                                
                                // Pausa para evitar límites de API
                                usleep(500000); // 0.5 segundos - más conservador
                            } catch (\Throwable $e) {
                                $this->warn("   ⚠️  Error obteniendo coordenadas para {$address}, {$city}: " . $e->getMessage());
                                $locationData['latitude'] = null;
                                $locationData['longitude'] = null;
                            }
                        } else {
                            $locationData['latitude'] = null;
                            $locationData['longitude'] = null;
                        }
                        
                        DB::table('locations')->updateOrInsert(
                            ['id' => (int)($oldLocation->idlocaciones ?? 0)],
                            $locationData
                        );
                    }
                });
                
                // Pausa entre lotes para evitar sobrecarga
                if (!$this->skipCoordinates) {
                    sleep(2); // Pausa más larga para API
                }
            });
            
        $this->info("   ✅ Total ubicaciones procesadas: {$totalProcessed}");
    }

    /**
     * Migrar solo coordenadas para ubicaciones existentes
     */
    private function migrateLocationCoordinatesOnly(bool $dryRun): void
    {
        if ($dryRun) { 
            $this->info("   [DRY-RUN] Calculando coordenadas para ubicaciones existentes...");
            return; 
        }

        $this->info("   📍 Calculando coordenadas para ubicaciones existentes...");
        
        $totalProcessed = 0;
        $googleMapsService = new GoogleMapsService();
        
        // Obtener ubicaciones que no tienen coordenadas
        $locationsWithoutCoordinates = DB::table('locations')
            ->whereNull('latitude')
            ->orWhereNull('longitude')
            ->orderBy('id')
            ->get();
            
        $this->info("   📍 Ubicaciones sin coordenadas encontradas: " . $locationsWithoutCoordinates->count());
        
        if ($locationsWithoutCoordinates->isEmpty()) {
            $this->info("   ✅ Todas las ubicaciones ya tienen coordenadas");
            return;
        }
        
        foreach ($locationsWithoutCoordinates->chunk($this->locationsBatchSize) as $locationChunk) {
            DB::transaction(function () use ($locationChunk, &$totalProcessed, $googleMapsService) {
                foreach ($locationChunk as $location) {
                    $totalProcessed++;
                    
                    if ($totalProcessed % 5 == 0) {
                        $this->info("   📍 Procesadas: {$totalProcessed} coordenadas...");
                    }
                    
                    try {
                        $coordinates = $googleMapsService->getCoordinates($location->address, $location->origin);
                        
                        DB::table('locations')
                            ->where('id', $location->id)
                            ->update([
                                'latitude' => $coordinates['latitude'] ?? null,
                                'longitude' => $coordinates['longitude'] ?? null,
                                'updated_at' => now(),
                            ]);
                            
                        // Pausa para evitar límites de API
                        usleep(500000); // 0.5 segundos
                        
                    } catch (\Throwable $e) {
                        $this->warn("   ⚠️  Error obteniendo coordenadas para {$location->address}, {$location->origin}: " . $e->getMessage());
                    }
                }
            });
            
            // Pausa entre lotes
            sleep(2);
        }
        
        $this->info("   ✅ Total coordenadas calculadas: {$totalProcessed}");
    }

    /**
     * Migrar transportes
     */
    private function migrateTransports(bool $dryRun): void
    {
        if ($dryRun) { return; }

         DB::connection('mysql_old')
             ->table('transportes')
             ->orderBy('idtransportes')
             ->chunk(1000, function ($oldTransports) {
                DB::transaction(function () use ($oldTransports) {
                    $i = 0;
                    foreach ($oldTransports as $oldTransport) {
                        $i++;
                        DB::table('transports')->updateOrInsert(
                            ['id' => (int)($oldTransport->idtransportes ?? 0)],
                            [
                                'description' => $oldTransport->descripcion ?? 'Descripción',
                                'plate' => $oldTransport->matricula ?? 'ABC-' . str_pad((string)$i, 3, '0', STR_PAD_LEFT),
                                'phone' => $oldTransport->telefono ?? '000-000-0000',
                                'insurance' => $oldTransport->seguro ?? 'Seguro por defecto',
                                'usage' => $oldTransport->uso ?? 'Uso general',
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]
                        );
                    }
                });
            });
    }

    /**
     * Migrar comisiones
     */
    private function migrateCommissions(bool $dryRun): void
    {
        if ($dryRun) { return; }

        // Procesar comisiones según el rango configurado
        $query = DB::connection('mysql_old')
            ->table('historial')
            ->orderBy('idhistorial', 'desc');
        
        // Aplicar filtros de rango
        if ($this->commissionFrom > 1) {
            $query->where('idhistorial', '>=', $this->commissionFrom);
        }
        
        if ($this->commissionTo > 0) {
            $query->where('idhistorial', '<=', $this->commissionTo);
        }
        
        $oldHistory = $query->get();
        
        $this->info("   💼 Procesando " . count($oldHistory) . " comisiones (rango: {$this->commissionFrom} a " . ($this->commissionTo > 0 ? $this->commissionTo : 'todas') . ")...");
        
        $processed = 0;
        
        foreach (array_chunk($oldHistory->toArray(), $this->batchSize) as $batch) {
            DB::transaction(function () use ($batch, &$processed) {
                foreach ($batch as $oldRecord) {
                    $processed++;
                    if ($processed % 50 == 0) {
                        $this->info("   💼 Procesadas: {$processed} comisiones...");
                    }
                    
                    $oldCommission = DB::connection('mysql_old')
                        ->table('comisiones')
                        ->where('idcomision', $oldRecord->idcomision)
                        ->first();
                    
                    if (!$oldCommission) continue;

                    $customer = DB::table('customers')->where('id', $oldRecord->idcliente)->first();
                    if (!$customer) continue;

                    $transport = DB::table('transports')->where('id', $oldRecord->idtransporte)->first();

                    // Obtener el total de la comisión desde fullcontrol
                    $total = 0.00;
                    $fullcontrolRecord = DB::connection('mysql_old')
                        ->table('fullcontrol')
                        ->where('idhistorial', $oldRecord->idhistorial)
                        ->whereNotNull('precio')
                        ->orderByDesc('idfullcontrol')
                        ->first();
                    
                    if ($fullcontrolRecord && is_numeric($fullcontrolRecord->precio)) {
                        $total = (float)$fullcontrolRecord->precio;
                    }

                    // Determinar destination_id desde el origen y destino de la comisión vieja
                    $destinationId = 1; // Valor por defecto
                    if (isset($oldCommission->origen, $oldCommission->destino)) {
                        $originLocation = DB::connection('mysql_old')
                            ->table('locaciones')
                            ->where('idlocaciones', $oldCommission->origen)
                            ->first();
                        $destinationLocation = DB::connection('mysql_old')
                            ->table('locaciones')
                            ->where('idlocaciones', $oldCommission->destino)
                            ->first();
                        
                        if ($originLocation && $destinationLocation) {
                            $originCity = $this->normalizeCityName($originLocation->localidad ?? 'CIUDAD');
                            $destinationCity = $this->normalizeCityName($destinationLocation->localidad ?? 'CIUDAD');
                            
                            $destination = DB::table('destinations')
                                ->where('origin', $originCity)
                                ->where('destination', $destinationCity)
                                ->first();
                            
                            if ($destination) {
                                $destinationId = $destination->id;
                            }
                        }
                    }

                    // Crear comisión
                    DB::table('commissions')->updateOrInsert(
                        ['id' => (int)$oldRecord->idhistorial],
                        [
                            'client_id' => $customer->id,
                            'transport_id' => $transport->id ?? null,
                            'status' => $this->mapCommissionStatus((int)$oldRecord->estado),
                            'total' => $total,
                            'user_id' => 1,
                            'branch_id' => 1,
                            'date' => $oldRecord->fecha,
                            'origin_location_id' => null,
                            'destination_location_id' => null,
                            'destination_id' => $destinationId,
                            'notes' => $oldCommission->observacion ?? null,
                            'created_at' => $oldRecord->fecha,
                            'updated_at' => now(),
                        ]
                    );

                    // Crear log de comisión
                    DB::table('commission_logs')->updateOrInsert(
                        ['commission_id' => (int)$oldRecord->idhistorial],
                        [
                            'previous_status' => CommissionStatus::SOLICITUD_RECIBIDA->value,
                            'new_status' => $this->mapCommissionStatus((int)$oldRecord->estado),
                            'user_id' => 1,
                            'created_at' => $oldRecord->fecha,
                            'updated_at' => now(),
                        ]
                    );

                    // Crear item de comisión por defecto
                    DB::table('commission_items')->updateOrInsert(
                        ['commission_id' => (int)$oldRecord->idhistorial],
                        [
                            'type' => CommissionItemType::ORDINARIA->value,
                            'size' => CommissionItemSize::SMALL->value,
                            'quantity' => 1,
                            'unit_price' => 0.00,
                            'subtotal' => 0.00,
                            'detail' => 'Bulto chico',
                            'created_at' => $oldRecord->fecha,
                            'updated_at' => now(),
                        ]
                    );
                }
            });
        }
    }

    /**
     * Migrar gastos
     */
    private function migrateExpenses(bool $dryRun): void
    {
        if ($dryRun) { return; }

        DB::connection('mysql_old')
            ->table('reporteegresos')
            ->orderBy('fecha')
            ->chunk(1000, function ($reportes) {
                DB::transaction(function () use ($reportes) {
                    foreach ($reportes as $reporte) {
                        $expenseCategory = DB::table('expense_categories')->where('id', $reporte->idegresos)->first();
                        if (!$expenseCategory) { continue; }
                        $amount = (float) $reporte->monto;
                        if ($amount <= 0) { continue; }
                        DB::table('expenses')->insert([
                            'expense_category_id' => (int)$reporte->idegresos,
                            'date' => $reporte->fecha,
                            'detail' => "Migrado desde reporteegresos - {$expenseCategory->name}",
                            'amount' => $amount,
                            'user_id' => 1,
                            'created_at' => $reporte->fecha,
                            'updated_at' => now(),
                        ]);
                    }
                });
            });
    }

    /**
     * Migrar ingresos
     */
    private function migrateIncomes(bool $dryRun): void
    {
        if ($dryRun) { return; }

        DB::connection('mysql_old')
            ->table('reporteingresos')
            ->orderBy('fecha')
            ->chunk(1000, function ($reportes) {
                DB::transaction(function () use ($reportes) {
                    foreach ($reportes as $reporte) {
                        $incomeCategory = DB::table('income_categories')->where('id', $reporte->idingresos)->first();
                        if (!$incomeCategory) { continue; }
                        $amount = (float) $reporte->monto;
                        if ($amount <= 0) { continue; }
                        DB::table('incomes')->insert([
                            'income_category_id' => (int)$reporte->idingresos,
                            'date' => $reporte->fecha,
                            'detail' => "Migrado desde reporteingresos - {$incomeCategory->name}",
                            'amount' => $amount,
                            'user_id' => 1,
                            'created_at' => $reporte->fecha,
                            'updated_at' => now(),
                        ]);
                    }
                });
            });
    }

    /**
     * Migrar cuentas corrientes
     */
    private function migrateCurrentAccounts(bool $dryRun): void
    {
        if ($dryRun) { return; }

        $customerBalances = [];
        DB::connection('mysql_old')
            ->table('reporteclientes')
            ->orderBy('idclientes')
            ->orderBy('fecha')
            ->chunk(1000, function ($reportes) use (&$customerBalances) {
                DB::transaction(function () use ($reportes, &$customerBalances) {
                    foreach ($reportes as $reporte) {
                        $newCustomer = DB::table('customers')->where('dni', (string)$reporte->idclientes)->first();
                        if (!$newCustomer) { continue; }

                        $type = $this->mapActionToType((string)$reporte->accion);
                        if (!$type) { continue; }

                        $amount = (float) $reporte->monto;
                        if ($amount <= 0) { continue; }

                        $customerId = $newCustomer->id;
                        if (!isset($customerBalances[$customerId])) {
                            $customerBalances[$customerId] = 0.0;
                        }

                        if ($type === 'credit') {
                            $customerBalances[$customerId] += $amount;
                        } else {
                            $customerBalances[$customerId] -= $amount;
                        }

                        DB::table('current_accounts')->insert([
                            'customer_id' => $customerId,
                            'type' => $type,
                            'amount' => $amount,
                            'balance' => $customerBalances[$customerId],
                            'description' => "Migrado desde reporteclientes - {$reporte->accion}",
                            'transaction_date' => $reporte->fecha,
                            'user_id' => 1,
                            'created_at' => $reporte->fecha,
                            'updated_at' => now(),
                        ]);
                    }
                });
            });
    }

    /**
     * Actualizar destinos de comisiones
     */
    private function updateCommissionDestinations(bool $dryRun): void
    {
        if ($dryRun) { return; }

        DB::table('commissions')->whereNull('destination_id')
            ->orderBy('id')
            ->chunk(1000, function ($commissions) {
                DB::transaction(function () use ($commissions) {
                    foreach ($commissions as $commission) {
                        $oldHistory = DB::connection('mysql_old')
                            ->table('historial')
                            ->where('idhistorial', $commission->id)
                            ->first();
                        if (!$oldHistory) { continue; }

                        $oldCommission = DB::connection('mysql_old')
                            ->table('comisiones')
                            ->where('idcomision', $oldHistory->idcomision)
                            ->first();
                        if (!$oldCommission) { continue; }

                        $originLocation = DB::connection('mysql_old')
                            ->table('locaciones')
                            ->where('idlocaciones', $oldCommission->origen)
                            ->first();
                        $destinationLocation = DB::connection('mysql_old')
                            ->table('locaciones')
                            ->where('idlocaciones', $oldCommission->destino)
                            ->first();

                        if ($originLocation && $destinationLocation) {
                            $originCity = $this->normalizeCityName($originLocation->localidad ?? 'CIUDAD');
                            $destinationCity = $this->normalizeCityName($destinationLocation->localidad ?? 'CIUDAD');

                            $destination = DB::table('destinations')
                                ->where('origin', $originCity)
                                ->where('destination', $destinationCity)
                                ->first();

                            if ($destination) {
                                DB::table('commissions')
                                    ->where('id', $commission->id)
                                    ->update(['destination_id' => $destination->id]);
                            }
                        }
                    }
                });
            });
    }

    /**
     * Corregir campos de comisiones
     */
    private function fixCommissionFields(bool $dryRun): void
    {
        if ($dryRun) { return; }

        // Solo procesar las comisiones según el rango configurado (las mismas que se migraron)
        $query = DB::connection('mysql_old')
            ->table('historial')
            ->orderBy('idhistorial', 'desc');
        
        // Aplicar filtros de rango
        if ($this->commissionFrom > 1) {
            $query->where('idhistorial', '>=', $this->commissionFrom);
        }
        
        if ($this->commissionTo > 0) {
            $query->where('idhistorial', '<=', $this->commissionTo);
        }
        
        $lastHistoryIds = $query->pluck('idhistorial')->toArray();

        DB::table('commissions')
            ->whereIn('id', $lastHistoryIds)
            ->orderBy('id')
            ->chunk(1000, function ($commissions) {
                DB::transaction(function () use ($commissions) {
                    foreach ($commissions as $commission) {
                        $updates = ['user_id' => 1];

                        // Rellenar origin/destination location ids desde destination
                        if ($commission->destination_id) {
                            $destination = DB::table('destinations')->where('id', $commission->destination_id)->first();
                            if ($destination) {
                                $originLocation = DB::table('locations')->where('origin', $destination->origin)->first();
                                $destinationLocation = DB::table('locations')->where('origin', $destination->destination)->first();
                                if ($originLocation) { $updates['origin_location_id'] = $originLocation->id; }
                                if ($destinationLocation) { $updates['destination_location_id'] = $destinationLocation->id; }
                            }
                        }

                        if (!empty($updates)) {
                            DB::table('commissions')->where('id', $commission->id)->update($updates);
                        }
                    }
                });
            });
    }

    /**
     * Actualizar totales de comisiones (desde items)
     */
    private function updateCommissionTotals(bool $dryRun): void
    {
        if ($dryRun) { return; }

        DB::table('commissions')->orderBy('id')->chunk(1000, function ($commissions) {
            DB::transaction(function () use ($commissions) {
                foreach ($commissions as $commission) {
                    $total = (float) DB::table('commission_items')
                        ->where('commission_id', $commission->id)
                        ->sum('subtotal');
                    if ($total > 0) {
                        DB::table('commissions')->where('id', $commission->id)->update(['total' => $total]);
                    }
                }
            });
        });
    }

    /**
     * Corregir campos de clientes
     */
    private function fixCustomersFields(bool $dryRun): void
    {
        if ($dryRun) { return; }

        DB::transaction(function () {
            DB::table('customers')->where('is_premium', 1)->update(['is_premium' => 0]);
            DB::table('customers')->whereNull('user_id')->update(['user_id' => 1]);
        });
    }

    /**
     * Mapear rol de usuario
     */
    private function mapUserRole(string $role): string
    {
        return match (strtolower(trim($role))) {
            'admin', 'administrador' => UserRole::ADMINISTRADOR->value,
            'cadete' => UserRole::CADETE->value,
            'empleado' => UserRole::MOSTRADOR->value,
            default => UserRole::MOSTRADOR->value,
        };
    }

    /**
     * Mapear estado de comisión
     */
    private function mapCommissionStatus(int $status): string
    {
        return match ($status) {
            1 => CommissionStatus::SOLICITUD_RECIBIDA->value,
            2 => CommissionStatus::CADETE_ASIGNADO->value,
            3, 4 => CommissionStatus::EN_TRANSITO_DESTINO->value,
            5 => CommissionStatus::ENTREGADO->value,
            6 => CommissionStatus::CANCELADO->value,
            7 => CommissionStatus::INTENTO_ENTREGA_FALLIDO->value,
            default => CommissionStatus::SOLICITUD_RECIBIDA->value,
        };
    }

    /**
     * Mapear acción a tipo de transacción
     */
    private function mapActionToType(string $accion): ?string
    {
        return match (strtolower(trim($accion))) {
            'ingreso' => 'credit',
            'egreso' => 'debit',
            'pendiente' => 'credit',
            default => null,
        };
    }

    /**
     * Normalizar nombre de ciudad
     */
    private function normalizeCityName(string $city): string
    {
        $city = trim($city);
        $city = strtoupper($city);
        $normalizations = [
            'ROSAROI' => 'ROSARIO',
            'CORDOBA' => 'CÓRDOBA',
        ];
        return $normalizations[$city] ?? $city;
    }

    /**
     * Obtener dirección desde ID (helper no usado por ahora, se deja por referencia)
     */
    private function getAddressFromId(?int $addressId): ?string
    {
        if (!$addressId) { return null; }
        $address = DB::connection('mysql_old')
            ->table('direcciones')
            ->where('iddireccion', $addressId)
            ->first();
        return $address->direccion ?? null;
    }
}
