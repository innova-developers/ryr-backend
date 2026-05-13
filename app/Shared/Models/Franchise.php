<?php

namespace App\Shared\Models;

use App\Shared\Enums\FranchiseStatus;
use Database\Factories\FranchiseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Franchise extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'address',
        'phone',
        'email',
        'commission_percentage_to_matrix',
        'owner_user_id',
        'status',
        'contract_start_date',
        'contract_end_date',
        'settings',
    ];

    protected $casts = [
        'status' => FranchiseStatus::class,
        'commission_percentage_to_matrix' => 'decimal:2',
        'contract_start_date' => 'date',
        'contract_end_date' => 'date',
        'settings' => 'array',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(Commission::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function incomes(): HasMany
    {
        return $this->hasMany(Income::class);
    }

    public static function newFactory(): FranchiseFactory
    {
        return FranchiseFactory::new();
    }
}
