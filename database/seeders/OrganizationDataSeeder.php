<?php

namespace Database\Seeders;

use Faker\Factory as Faker;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OrganizationDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create();
        $chunkSize = 1000; // Insert in batches of 1000

        for ($i = 50002; $i < 53000; $i += $chunkSize) {
            $data = [];

            for ($j = 0; $j < $chunkSize; $j++) {
                $data[] = [
                    'name' => $faker->name,
                    'email' => $faker->unique()->safeEmail,
                    'subdomain' => 'subdomain_' . ($i + $j),
                    'database_name' => 'db_' . ($i + $j),
                    'azure_client_id' => $faker->uuid,
                    'azure_client_secret' => $faker->uuid,
                    'azure_tenant_id' => $faker->uuid,
                    'azure_redirect_uri' => $faker->url,
                    'zoho_client_id' => $faker->uuid,
                    'zoho_client_secret' => $faker->uuid,
                    'zoho_redirect_uri' => $faker->url,
                    'created_at' => now(),
                    'updated_at' => now(),
                    'deleted_at' => null,
                ];
            }

            // Insert the chunk
            DB::table('organizations')->insert($data);
        }
    }
}
