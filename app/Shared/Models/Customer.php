<?php

namespace App\Shared\Models;

use App\Shared\Enums\CurrentAccountStatus;
use App\Shared\Enums\CustomerType;
use App\Shared\Enums\IvaStatus;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'dni',
        'cuit',
        'type',
        'name',
        'last_name',
        'razon_social',
        'mobile',
        'email',
        'address',
        'city',
        'phone',
        'maps_url',
        'business_hours',
        'observations',
        'is_premium',
        'auto_calculate_iva',
        'iva_status',
        'user_id',
        'branch_id',
        'internal_user_id',
        'franchise_id',
    ];

    protected $casts = [
        'is_premium' => 'boolean',
        'auto_calculate_iva' => 'boolean',
        'iva_status' => IvaStatus::class,
        'type' => CustomerType::class,
        'dni' => 'integer',
    ];

    public function isCompany(): bool
    {
        return $this->type === CustomerType::COMPANY;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function internalUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'internal_user_id');
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->name} {$this->last_name}";
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function franchise(): BelongsTo
    {
        return $this->belongsTo(Franchise::class);
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(Commission::class, 'client_id');
    }

    public function currentAccounts(): HasMany
    {
        return $this->hasMany(CurrentAccount::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function getCurrentBalanceAttribute(): float
    {
        // Solo considerar transacciones con estado OK para el cálculo del saldo
        $lastTransaction = $this->currentAccounts()
            ->where('status', CurrentAccountStatus::OK->value)
            ->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        return $lastTransaction ? $lastTransaction->balance : 0;
    }

    public static function newFactory(): CustomerFactory
    {
        return CustomerFactory::new();
    }
}
