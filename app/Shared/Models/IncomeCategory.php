<?php

namespace App\Shared\Models;

use Database\Factories\IncomeCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IncomeCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
    ];

    public function incomes(): HasMany
    {
        return $this->hasMany(Income::class, 'income_category_id');
    }

    public static function newFactory()
    {
        return IncomeCategoryFactory::new();
    }
}
