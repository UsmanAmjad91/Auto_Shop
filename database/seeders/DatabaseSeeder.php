<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seeds ONLY system reference data (roles, permissions, statuses, lookups).
     * No users are created: run `php artisan autoshop:create-admin`.
     * Optional demo data: `php artisan db:seed --class=DemoDataSeeder`.
     */
    public function run(): void
    {
        $this->call(SystemSeeder::class);
    }
}
