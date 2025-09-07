<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@teste.com'],
            [
                'name' => 'Admin',
                'password' => bcrypt('secret123'),
                'is_admin' => true,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );

        $this->call([
            SongSeeder::class,
        ]);
    }
}
