<?php

namespace App\Console\Commands;

use App\Franchise;
use App\Services\FranchiseDatabaseService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateDataToFranchise extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'franchise:migrate-data {franchise_id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrar datos existentes a una franquicia específica';

    private FranchiseDatabaseService $franchiseDatabaseService;

    public function __construct(FranchiseDatabaseService $franchiseDatabaseService)
    {
        parent::__construct();
        $this->franchiseDatabaseService = $franchiseDatabaseService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $franchiseId = $this->argument('franchise_id');
        
        $franchise = Franchise::find($franchiseId);
        
        if (!$franchise) {
            $this->error("Franquicia con ID {$franchiseId} no encontrada.");
            return 1;
        }

        $this->info("Iniciando migración de datos a la franquicia: {$franchise->name}");

        try {
            // Para desarrollo, usar la misma base de datos pero con prefijo de tabla
            $this->info("Configurando base de datos de la franquicia...");
            
            // Configurar conexión de la franquicia para usar la misma DB con prefijo
            config([
                "database.connections.franchise_{$franchise->code}" => [
                    'driver' => 'mysql',
                    'host' => env('DB_HOST', '127.0.0.1'),
                    'port' => env('DB_PORT', '3306'),
                    'database' => env('DB_DATABASE'),
                    'username' => env('DB_USERNAME'),
                    'password' => env('DB_PASSWORD'),
                    'charset' => 'utf8mb4',
                    'collation' => 'utf8mb4_unicode_ci',
                    'prefix' => "franchise_{$franchise->code}_",
                    'strict' => true,
                    'engine' => null,
                ]
            ]);

            // Migrar datos de la base de datos principal a la franquicia
            $this->migrateDataToFranchise($franchise);

            $this->info("✅ Migración completada exitosamente!");
            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Error durante la migración: " . $e->getMessage());
            return 1;
        }
    }

    private function migrateDataToFranchise(Franchise $franchise)
    {
        $this->info("Migrando datos a la franquicia...");

        // Lista de tablas a migrar (excluyendo las tablas del sistema de franquicias)
        $tablesToMigrate = [
            'users',
            'customers', 
            'commissions',
            'commission_items',
            'branches',
            'destinations',
            'locations',
            'transports',
            'expense_categories',
            'expenses',
            'income_categories',
            'incomes',
            'current_accounts',
            'extraordinary_commissions',
            'cadete_payments',
            'delivery_signatures',
            'shipment_locations',
            'notifications',
        ];

        foreach ($tablesToMigrate as $table) {
            $this->migrateTable($franchise, $table);
        }
    }

    private function migrateTable(Franchise $franchise, string $tableName)
    {
        try {
            // Verificar si la tabla existe en la base de datos principal
            if (!DB::getSchemaBuilder()->hasTable($tableName)) {
                $this->warn("Tabla {$tableName} no existe en la base de datos principal, saltando...");
                return;
            }

            $this->info("Migrando tabla: {$tableName}");

            // Obtener datos de la tabla principal
            $data = DB::table($tableName)->get();

            if ($data->isEmpty()) {
                $this->warn("Tabla {$tableName} está vacía, saltando...");
                return;
            }

            // Configurar conexión de la franquicia
            $franchiseConnection = "franchise_{$franchise->code}";
            
            // Limpiar tabla de la franquicia si existe
            if (DB::connection($franchiseConnection)->getSchemaBuilder()->hasTable($tableName)) {
                DB::connection($franchiseConnection)->table($tableName)->truncate();
            }
            
            // Insertar datos en la base de datos de la franquicia
            $chunks = $data->chunk(1000);
            foreach ($chunks as $chunk) {
                DB::connection($franchiseConnection)->table($tableName)->insert($chunk->toArray());
            }

            $this->info("✅ Tabla {$tableName} migrada: {$data->count()} registros");

        } catch (\Exception $e) {
            $this->error("❌ Error migrando tabla {$tableName}: " . $e->getMessage());
        }
    }
}
