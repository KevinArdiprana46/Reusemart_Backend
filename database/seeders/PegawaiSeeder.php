<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class PegawaiSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('user')->insert([
            'name' => 'Dewi Lestari',
            'email' => 'dewi.lestari@example.com',
            'password' => Hash::make('csdewi123'),
            'id_role' => 1, 
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
