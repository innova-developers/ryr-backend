<?php

namespace App\Services;

use App\Franchise;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;

class FranchiseDatabaseService
{
    /**
     * Configurar la conexión de base de datos para una franquicia específica
     */
    public function setFranchiseConnection(Franchise $franchise): void
    {
        $connectionName = $franchise->getDatabaseConnection();
        
        // Configurar la nueva conexión
        Config::set("database.connections.{$connectionName}", [
            'driver' => 'mysql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => $franchise->database_name,
            'username' => env('DB_USERNAME'),
            'password' => env('DB_PASSWORD'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => null,
        ]);

        // Establecer como conexión por defecto
        Config::set('database.default', $connectionName);
        
        // IMPORTANTE: Purgar todas las conexiones para forzar la recreación
        // Esto asegura que Eloquent y Sanctum usen la nueva conexión
        DB::purge($connectionName);
        DB::purge(); // Purgar todas las conexiones
        
        // Forzar la recreación de la conexión
        DB::reconnect($connectionName);
    }

    /**
     * Crear la base de datos para una nueva franquicia
     */
    public function createFranchiseDatabase(Franchise $franchise): array
    {
        try {
            // Guardar conexión principal
            $originalConnection = config('database.default');
            
            // Conectar a la base de datos principal para crear la nueva DB
            $mainConnection = DB::connection();
            
            // Crear la base de datos vacía
            $mainConnection->statement("CREATE DATABASE IF NOT EXISTS `{$franchise->database_name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            
            // Configurar la conexión a la nueva base de datos
            $this->setFranchiseConnection($franchise);
            
            // Ejecutar las migraciones en la nueva base de datos (crea todas las tablas vacías)
            $this->runMigrationsForFranchise($franchise);
            
            // Crear usuario administrador para la franquicia
            $adminCredentials = $this->createFranchiseAdmin($franchise);
            
            // Restaurar conexión principal
            $this->restoreMainConnection();
            
            return [
                'success' => true,
                'admin_credentials' => $adminCredentials
            ];
        } catch (\Exception $e) {
            // Asegurar que se restaure la conexión principal en caso de error
            $this->restoreMainConnection();
            \Log::error("Error creating franchise database: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Ejecutar migraciones en la base de datos de la franquicia
     */
    public function runMigrationsForFranchise(Franchise $franchise): void
    {
        $this->setFranchiseConnection($franchise);
        
        // Migraciones que NO deben ejecutarse en la DB de franquicia (solo en DB principal)
        $excludedPatterns = [
            'create_franchises_table',
            'create_super_admins_table',
            'add_logo_path_to_franchises_table',
            'remove_domain_from_franchises_table',
            'add_franchise_id_to_users_table', // Esta columna solo existe en la DB principal
        ];
        
        // Obtener todas las migraciones ordenadas por fecha
        $migrationPath = database_path('migrations');
        $allMigrations = glob($migrationPath . '/*.php');
        usort($allMigrations, function($a, $b) {
            return strcmp(basename($a), basename($b));
        });
        
        // Ejecutar migraciones una por una, excluyendo las problemáticas
        foreach ($allMigrations as $migrationFile) {
            $fileName = basename($migrationFile);
            $shouldExclude = false;
            
            foreach ($excludedPatterns as $pattern) {
                if (strpos($fileName, $pattern) !== false) {
                    $shouldExclude = true;
                    break;
                }
            }
            
            if (!$shouldExclude) {
                try {
                    // Ejecutar migración individual
                    \Artisan::call('migrate', [
                        '--database' => $franchise->getDatabaseConnection(),
                        '--path' => 'database/migrations/' . $fileName,
                        '--force' => true,
                    ]);
                } catch (\Exception $e) {
                    \Log::warning("Error running migration {$fileName} for franchise {$franchise->id}: " . $e->getMessage());
                    // Continuar con las siguientes migraciones
                }
            }
        }
    }

    /**
     * Verificar si la base de datos de la franquicia existe
     */
    public function franchiseDatabaseExists(Franchise $franchise): bool
    {
        try {
            $mainConnection = DB::connection();
            $result = $mainConnection->select("SHOW DATABASES LIKE '{$franchise->database_name}'");
            return count($result) > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Eliminar la base de datos de una franquicia
     */
    public function dropFranchiseDatabase(Franchise $franchise): bool
    {
        try {
            $mainConnection = DB::connection();
            $mainConnection->statement("DROP DATABASE IF EXISTS `{$franchise->database_name}`");
            return true;
        } catch (\Exception $e) {
            \Log::error("Error dropping franchise database: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtener estadísticas de la base de datos de una franquicia
     */
    public function getFranchiseDatabaseStats(Franchise $franchise): array
    {
        if (!$this->franchiseDatabaseExists($franchise)) {
            return [];
        }

        $this->setFranchiseConnection($franchise);
        
        try {
            $stats = [
                'commissions_count' => DB::table('commissions')->count(),
                'customers_count' => DB::table('customers')->count(),
                'users_count' => DB::table('users')->count(),
                'branches_count' => DB::table('branches')->count(),
                'database_size' => $this->getDatabaseSize($franchise),
            ];

            return $stats;
        } catch (\Exception $e) {
            \Log::error("Error getting franchise database stats: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener el tamaño de la base de datos
     */
    private function getDatabaseSize(Franchise $franchise): string
    {
        try {
            $mainConnection = DB::connection();
            $result = $mainConnection->select("
                SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb
                FROM information_schema.tables 
                WHERE table_schema = '{$franchise->database_name}'
            ");
            
            return $result[0]->size_mb . ' MB';
        } catch (\Exception $e) {
            return 'N/A';
        }
    }

    /**
     * Restaurar conexión a la base de datos principal
     */
    public function restoreMainConnection(): void
    {
        $mainConnectionName = env('DB_CONNECTION', 'mysql');
        Config::set('database.default', $mainConnectionName);
        DB::purge($mainConnectionName);
        DB::purge(); // Limpiar todas las conexiones
    }

    /**
     * Crear usuario administrador para la franquicia
     */
    public function createFranchiseAdmin(Franchise $franchise): array
    {
        // Generar credenciales únicas
        $adminEmail = "admin@{$franchise->generateSubdomain()}.ryrcomisiones.com";
        $adminPassword = $this->generateSecurePassword();
        
        // Primero crear el usuario en la DB principal (con franchise_id)
        // Nota: La tabla users en la DB principal no tiene is_active, solo en las DBs de franquicia
        $this->restoreMainConnection();
        $mainConnection = DB::connection();
        $mainUserId = $mainConnection->table('users')->insertGetId([
            'name' => "Administrador {$franchise->name}",
            'email' => $adminEmail,
            'password' => bcrypt('password'),
            'role' => \App\Shared\Enums\UserRole::ADMINISTRADOR_FRANQUICIA->value,
            'franchise_id' => $franchise->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        // Luego crear el usuario en la DB de la franquicia (sin franchise_id, ya que todos los usuarios de esa DB pertenecen a esa franquicia)
        $this->setFranchiseConnection($franchise);
        $franchiseUserId = DB::table('users')->insertGetId([
            'name' => "Administrador {$franchise->name}",
            'email' => $adminEmail,
            'password' => bcrypt('password'),
            'role' => \App\Shared\Enums\UserRole::ADMINISTRADOR_FRANQUICIA->value,
            // No incluir franchise_id aquí porque la tabla users en la DB de franquicia no tiene esa columna
            // No incluir is_active porque la tabla users no tiene esa columna en ninguna DB
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        // Restaurar conexión principal
        $this->restoreMainConnection();
        
        return [
            'email' => $adminEmail,
            'password' => $adminPassword,
            'user_id' => $mainUserId, // ID del usuario en la DB principal
            'franchise_user_id' => $franchiseUserId, // ID del usuario en la DB de la franquicia
            'franchise_url' => $franchise->getFullUrl()
        ];
    }

    /**
     * Generar contraseña segura
     */
    private function generateSecurePassword(): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
        $password = '';
        
        for ($i = 0; $i < 12; $i++) {
            $password .= $chars[random_int(0, strlen($chars) - 1)];
        }
        
        return $password;
    }

    /**
     * Ejecutar comando en la base de datos de la franquicia
     */
    public function executeInFranchiseDatabase(Franchise $franchise, callable $callback)
    {
        $this->setFranchiseConnection($franchise);
        
        try {
            return $callback();
        } finally {
            $this->restoreMainConnection();
        }
    }
}
