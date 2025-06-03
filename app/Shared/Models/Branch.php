<?php

namespace App\Shared\Models;

use Database\Factories\BranchFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'address','schedule','phone'];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public static function newFactory(): BranchFactory
    {
        return BranchFactory::new();
    }
}
