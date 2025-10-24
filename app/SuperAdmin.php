<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class SuperAdmin extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'is_active',
        'permissions',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'permissions' => 'array',
        'last_login_at' => 'datetime',
        'password' => 'hashed',
    ];

    /**
     * Verificar si el super admin está activo
     */
    public function isActive(): bool
    {
        return $this->is_active;
    }

    /**
     * Verificar si tiene un permiso específico
     */
    public function hasPermission(string $permission): bool
    {
        if (!$this->permissions) {
            return true; // Si no hay permisos específicos, tiene acceso total
        }

        return in_array($permission, $this->permissions);
    }

    /**
     * Obtener todas las franquicias que puede gestionar
     */
    public function getAccessibleFranchises()
    {
        return Franchise::where('is_active', true)->get();
    }

    /**
     * Actualizar último login
     */
    public function updateLastLogin(): void
    {
        $this->update(['last_login_at' => now()]);
    }
}
