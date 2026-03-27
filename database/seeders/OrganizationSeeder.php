<?php

namespace Database\Seeders;

use App\Models\Organization;
use Illuminate\Database\Seeder;

class OrganizationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $organizations = [
            'Envolutions',
            'xDx',
            'Data Engine',
        ];

        foreach ($organizations as $organizationName) {
            Organization::create([
                'name' => $organizationName,
            ]);
        }
    }
}
