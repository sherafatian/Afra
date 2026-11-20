<?php

namespace Database\Seeders;

use App\Services\Access;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Access::ACTIONS as $key) {
            DB::table('permission_catalog')->updateOrInsert(['key' => $key], ['domain' => explode('.', $key)[0]]);
        }
    }
}
