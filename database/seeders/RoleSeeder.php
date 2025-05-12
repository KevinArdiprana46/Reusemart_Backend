<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('role')->insert([
            ['id_role' => 1, 'nama_role' => 'pegawai'],
            ['id_role' => 2, 'nama_role' => 'pembeli'],
            ['id_role' => 3, 'nama_role' => 'penitip'],
            ['id_role' => 4, 'nama_role' => 'organisasi'],
            ['id_role' => 5, 'nama_role' => 'owner'],
            ['id_role' => 6, 'nama_role' => 'admin'],
        ]);
    }
}
