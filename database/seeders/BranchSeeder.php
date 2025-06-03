<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Shared\Models\Branch;

class BranchSeeder extends Seeder
{
    public function run(): void
    {
        Branch::factory()->count(5)->create();
    }
}
