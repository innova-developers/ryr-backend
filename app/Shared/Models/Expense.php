<?php

namespace App\Shared\Models;

use Database\Factories\ExpenseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'transport_id',
        'expense_category_id',
        'user_id',
        'date',
        'detail',
        'amount',
        'franchise_id',
    ];

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function transport(): BelongsTo
    {
        return $this->belongsTo(Transport::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function franchise(): BelongsTo
    {
        return $this->belongsTo(Franchise::class);
    }

    public function toArray(): array
    {
        $array = parent::toArray();

        // Asegurar que expense_category_id esté presente
        if (! isset($array['expense_category_id'])) {
            $array['expense_category_id'] = $this->expense_category_id;
        }

        // Formatear la fecha como Y-m-d para mantener compatibilidad con los tests
        if (isset($array['date']) && $this->date) {
            $array['date'] = $this->date->format('Y-m-d');
        }

        return $array;
    }

    public static function newFactory(): ExpenseFactory
    {
        return new ExpenseFactory();
    }
}
