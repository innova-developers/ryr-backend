<?php

namespace App\Shared\Models;

use Database\Factories\BranchFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @use \Illuminate\Database\Eloquent\Factories\HasFactory<\Database\Factories\BranchFactory>
 */
class Branch extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'address', 'schedule', 'phone'];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Shared\Models\User, \App\Shared\Models\Branch>
     */
    public function users(): HasMany
    {
        /** @var \Illuminate\Database\Eloquent\Relations\HasMany<\App\Shared\Models\User, \App\Shared\Models\Branch> $relation */
        $relation = $this->hasMany(User::class);

        return $relation;
    }

    public static function newFactory(): BranchFactory
    {
        return BranchFactory::new();
    }
}
