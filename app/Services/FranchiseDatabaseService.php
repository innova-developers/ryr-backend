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
        DB::purge($connectionName);
    }

    /**
     * Crear la base de datos para una nueva franquicia
     */
    public function createFranchiseDatabase(Franchise $franchise): array
    {
        try {
            // Conectar a la base de datos principal para crear la nueva DB
            $mainConnection = DB::connection();
            
            // Crear la base de datos
            $mainConnection->statement("CREATE DATABASE IF NOT EXISTS `{$franchise->database_name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            
            // Configurar la conexión a la nueva base de datos
            $this->setFranchiseConnection($franchise);
            
            // Ejecutar las migraciones en la nueva base de datos
            $this->runMigrationsForFranchise($franchise);
            
            // Crear usuario administrador para la franquicia
            $adminCredentials = $this->createFranchiseAdmin($franchise);
            
            return [
                'success' => true,
                'admin_credentials' => $adminCredentials
            ];
        } catch (\Exception $e) {
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
        
        // Ejecutar migraciones específicas para franquicias
        \Artisan::call('migrate', [
            '--database' => $franchise->getDatabaseConnection(),
            '--path' => 'database/migrations/franchise',
            '--force' => true,
        ]);
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
        Config::set('database.default', 'mysql');
        DB::purge('mysql');
    }

    /**
     * Crear usuario administrador para la franquicia
     */
    public function createFranchiseAdmin(Franchise $franchise): array
    {
        $this->setFranchiseConnection($franchise);
        
        // Generar credenciales únicas
        $adminEmail = "admin@{$franchise->generateSubdomain()}.ryrcomisiones.com";
        $adminPassword = $this->generateSecurePassword();
        
        // Crear el usuario administrador
        $adminUser = DB::table('users')->insertGetId([
            'name' => "Administrador {$franchise->name}",
            'email' => $adminEmail,
            'password' => bcrypt($adminPassword),
            'role' => 'admin',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        return [
            'email' => $adminEmail,
            'password' => $adminPassword,
            'user_id' => $adminUser,
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
