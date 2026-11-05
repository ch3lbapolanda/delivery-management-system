<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            [
                'name' => 'Administrator',
                'slug' => 'admin',
                'description' => 'Manages the entire system',
            ],
            [
                'name' => 'Dispatcher',
                'slug' => 'dispatcher',
                'description' => 'Manages deliveries and courier assignments',
            ],
            [
                'name' => 'Courier',
                'slug' => 'courier',
                'description' => 'Handles assigned deliveries',
            ],
            [
                'name' => 'Customer',
                'slug' => 'customer',
                'description' => 'Creates and tracks delivery orders',
            ],
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(
                ['slug' => $role['slug']],
                $role
            );
        }
    }
}
