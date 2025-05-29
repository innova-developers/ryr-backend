<?php

namespace Database\Seeders;

use App\Shared\Enums\UserRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('users')->insert([
            [
                'name' => 'Admin',
                'email' => 'admin@example.com',
                'password' => Hash::make('password'),
                'role' => UserRole::ADMINISTRADOR->value,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Cadete',
                'email' => 'cadete@example.com',
                'password' => Hash::make('password'),
                'role' => UserRole::CADETE->value,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Mostrador',
                'email' => 'mostrador@example.com',
                'password' => Hash::make('password'),
                'role' => UserRole::MOSTRADOR->value,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Cliente',
                'email' => 'cliente@example.com',
                'password' => Hash::make('password'),
                'role' => UserRole::CLIENTE->value,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}

