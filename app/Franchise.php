<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Franchise extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'database_name',
        'subdomain',
        'description',
        'address',
        'phone',
        'email',
        'contact_person',
        'logo_path',
        'is_active',
        'settings',
        'activated_at',
        'deactivated_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'settings' => 'array',
        'activated_at' => 'datetime',
        'deactivated_at' => 'datetime',
    ];

    /**
     * Generar subdominio automáticamente basado en el nombre
     */
    public function generateSubdomain(): string
    {
        $subdomain = strtolower($this->name);
        $subdomain = preg_replace('/[^a-z0-9\-]/', '', $subdomain);
        $subdomain = preg_replace('/\-+/', '-', $subdomain);
        $subdomain = trim($subdomain, '-');
        
        // Asegurar que no esté vacío
        if (empty($subdomain)) {
            $subdomain = 'franchise-' . $this->id;
        }
        
        return $subdomain;
    }

    /**
     * Generar nombre de base de datos automáticamente
     */
    public function generateDatabaseName(): string
    {
        return 'franchise_' . $this->generateSubdomain();
    }

    /**
     * Obtener la conexión de base de datos específica para esta franquicia
     */
    public function getDatabaseConnection(): string
    {
        return "franchise_{$this->code}";
    }

    /**
     * Verificar si la franquicia está activa
     */
    public function isActive(): bool
    {
        return $this->is_active && $this->activated_at !== null;
    }

    /**
     * Activar la franquicia
     */
    public function activate(): void
    {
        $this->update([
            'is_active' => true,
            'activated_at' => now(),
            'deactivated_at' => null,
        ]);
    }

    /**
     * Desactivar la franquicia
     */
    public function deactivate(): void
    {
        $this->update([
            'is_active' => false,
            'deactivated_at' => now(),
        ]);
    }

    /**
     * Obtener la URL completa de la franquicia
     */
    public function getFullUrl(): string
    {
        $subdomain = $this->subdomain ?: $this->generateSubdomain();
        return "https://{$subdomain}.ryrcomisiones.com";
    }
}
