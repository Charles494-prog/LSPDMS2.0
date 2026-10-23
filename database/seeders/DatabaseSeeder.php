<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Intentionally seeds nothing. Accounts are created through registration
     * (approved by the Head Secretary) and the IT recovery account through
     * "php artisan lspdms:it-recovery:provision"; no account with a default
     * password may ever be created by seeding.
     */
    public function run(): void
    {
        //
    }
}
