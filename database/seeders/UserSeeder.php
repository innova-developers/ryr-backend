<?php

namespace Database\Seeders;

use App\Shared\Enums\UserRole;
use App\Shared\Models\Branch;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $branches = Branch::all();

        DB::table('users')->insert([
            [
                'name' => 'Juan Sepúlveda',
                'email' => 'juan@innovadevelopers.com',
                'password' => Hash::make('password'),
                'role' => UserRole::ADMINISTRADOR->value,
                'branch_id' => $branches->random()->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Cadete',
                'email' => 'cadete@example.com',
                'password' => Hash::make('password'),
                'role' => UserRole::CADETE->value,
                'branch_id' => $branches->random()->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Mostrador',
                'email' => 'mostrador@example.com',
                'password' => Hash::make('password'),
                'role' => UserRole::MOSTRADOR->value,
                'branch_id' => $branches->random()->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Cliente',
                'email' => 'cliente@example.com',
                'password' => Hash::make('password'),
                'role' => UserRole::CLIENTE->value,
                'branch_id' => $branches->random()->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Cadete Externo',
                'email' => 'cadeteexterno@example.com',
                'password' => Hash::make('password'),
                'role' => UserRole::CADETE_EXTERNO->value,
                'branch_id' => $branches->random()->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
