<?php

namespace App\Shared\Models;

use App\Shared\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use Notifiable;
    use SoftDeletes;
    
    /**
     * Obtener el nombre de la conexión de base de datos para el modelo.
     * Esto permite que el modelo use la conexión configurada dinámicamente por el middleware.
     * 
     * IMPORTANTE: Este método se llama cuando Eloquent necesita determinar qué conexión usar.
     * Sanctum también usará esta conexión para buscar tokens en personal_access_tokens.
     */
    public function getConnectionName()
    {
        // Si hay una conexión configurada dinámicamente (por el FranchiseMiddleware),
        // usar esa conexión. De lo contrario, usar null para usar la conexión por defecto.
        $defaultConnection = config('database.default');
        
        // Si la conexión por defecto no es 'mysql', significa que el middleware
        // configuró una conexión de franquicia, así que usarla
        if ($defaultConnection !== 'mysql' && strpos($defaultConnection, 'franchise_') === 0) {
            return $defaultConnection;
        }
        
        return null; // Usar conexión por defecto
    }
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'branch_id',
        'franchise_id',
        'base_salary',
        'income_percentage',
        'commission_percentage',
        'contract_type',
        'payment_per_pickup',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'deleted_at' => 'datetime',
        'base_salary' => 'decimal:2',
        'income_percentage' => 'decimal:2',
        'commission_percentage' => 'decimal:2',
        'payment_per_pickup' => 'decimal:2',
        'role' => UserRole::class,
    ];


    public static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    /**
     * @return BelongsTo<\App\Shared\Models\Branch, \App\Shared\Models\User>
     */
    public function branch(): BelongsTo
    {
        /** @var BelongsTo<\App\Shared\Models\Branch, \App\Shared\Models\User> $relation */
        $relation = $this->belongsTo(Branch::class);

        return $relation;
    }

    public function commissions(): BelongsTo
    {
        /** @var BelongsTo<\App\Shared\Models\Commission, \App\Shared\Models\User> $relation */
        $relation = $this->belongsTo(Commission::class);

        return $relation;
    }
    public function incomes(): HasMany
    {
        return $this->hasMany(Income::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function fcmTokens(): HasMany
    {
        return $this->hasMany(FcmToken::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::ADMINISTRADOR;
    }

    public function isFranchiseAdmin(): bool
    {
        return $this->role === UserRole::ADMINISTRADOR_FRANQUICIA;
    }

    /**
     * @return BelongsTo<\App\Franchise, \App\Shared\Models\User>
     */
    public function franchise(): BelongsTo
    {
        /** @var BelongsTo<\App\Franchise, \App\Shared\Models\User> $relation */
        $relation = $this->belongsTo(\App\Franchise::class);

        return $relation;
    }
}
