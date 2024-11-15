<?php

namespace Database\Seeders;

use App\Models\RoleTenant;
use App\Models\TenantRole;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            ['role' => 'super_admin', 'active' => true],
            ['role' => 'admin', 'active' => true],
            ['role' => 'hr', 'active' => true],
            ['role' => 'manager', 'active' => true],
            ['role' => 'project_head', 'active' => true],
            ['role' => 'lead', 'active' => true],
            ['role' => 'coder', 'active' => true],
            ['role' => 'auditor', 'active' => true],
        ];

        foreach ($roles as $role) {
            TenantRole::create($role);
        }
    }
}
