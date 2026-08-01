<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@retroshop.test',
            'password' => Hash::make('password'),
            'is_admin' => true,
        ]);

        $this->call(CatalogSeeder::class);
    }
}
