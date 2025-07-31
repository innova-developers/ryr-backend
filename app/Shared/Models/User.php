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
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'branch_id',
        'base_salary',
        'income_percentage',
        'commission_percentage',
        'contract_type',
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

    public function isAdmin(): bool
    {
        return $this->role === UserRole::ADMINISTRADOR;
    }


}
