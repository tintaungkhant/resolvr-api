<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Agent;
use App\Models\Client;
use App\Enums\UserRole;
use App\Models\Organization;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $x = 1;
        for ($i = 0; $i < 5; $i++) {
            $user = User::factory()->create([
                'role' => UserRole::Agent->value,
            ]);

            Agent::factory()->create([
                'user_id' => $user->id,
                'email'   => 'agent'.$x.'@gmail.com',
            ]);

            $x++;
        }

        $y = 1;
        foreach (Organization::all() as $organization) {
            for ($i = 0; $i < rand(10, 20); $i++) {
                $user = User::factory()->create([
                    'role' => UserRole::Client->value,
                ]);

                Client::factory()->create([
                    'user_id'         => $user->id,
                    'organization_id' => $organization->id,
                    'email'           => 'client'.$y.'@gmail.com',
                ]);

                $y++;
            }
        }
    }
}
