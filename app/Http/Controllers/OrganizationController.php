<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\TenantUser;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrganizationController extends Controller
{
    public function createOrganization(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string',
            'email' => 'required|email|unique:users,email',
            'subdomain' => 'required|string|unique:organizations',
            'azure_client_id' => 'nullable|string',
            'azure_client_secret' => 'nullable|string',
            'azure_tenant_id' => 'nullable|string',
            'azure_redirect_uri' => 'nullable|string',
            'zoho_client_id' => 'nullable|string',
            'zoho_client_secret' => 'nullable|string',
            'zoho_redirect_uri' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction(); // Start transaction

        try {
            // Step 1: Create the organization
            $organization = Organization::create([
                'name' => $request->name,
                'email' => $request->email,
                'subdomain' => $request->subdomain,
                'database_name' => 'tenant_' . $request->subdomain,
                'azure_client_id' => $request->azure_client_id,
                'azure_client_secret' => $request->azure_client_secret,
                'azure_tenant_id' => $request->azure_tenant_id,
                'azure_redirect_uri' => $request->azure_redirect_uri,
                'zoho_client_id' => $request->zoho_client_id,
                'zoho_client_secret' => $request->zoho_client_secret,
                'zoho_redirect_uri' => $request->zoho_redirect_uri,
            ]);

            // Step 2: Create the new tenant database
            DB::statement("CREATE DATABASE {$organization->database_name}");

            // Step 3: Run migrations on the new tenant database
            $this->runTenantMigrations($organization);

            DB::commit(); // Commit transaction if all went well

            return response()->json(['status' => 'Success', 'message' => 'Organization and tenant database created successfully!'], 201);
        } catch (QueryException $e) {
            DB::rollBack(); // Rollback transaction on error
            return response()->json(['error' => 'Database error: ' . $e->getMessage()], 500);
        } catch (Exception $e) {
            DB::rollBack(); // Rollback transaction on general exception
            return response()->json(['error' => 'An error occurred: ' . $e->getMessage()], 500);
        }
    }

    // Function to switch the database and run migrations
    private function runTenantMigrations($organization)
    {
        try {
            // Step 4: Set the tenant database dynamically for migrations
            Config::set('database.connections.tenant', [
                'driver' => 'mysql',
                'host' => env('DB_HOST'),
                'database' => $organization->database_name,
                'username' => env('DB_USERNAME'),
                'password' => env('DB_PASSWORD'),
            ]);

            // Step 5: Run migrations for the tenant's database
            DB::setDefaultConnection('tenant');
            Artisan::call('migrate', [
                '--database' => 'tenant',
                '--path' => 'database/migrations/tenant', // Path to tenant-specific migrations (for roles, users, etc.)
                '--force' => true,
            ]);

            // Step 6: Run Laravel Passport migrations for the tenant
            Artisan::call('migrate', [
                '--database' => 'tenant',
                '--path' => 'vendor/laravel/passport/database/migrations', // Path to Passport migrations
                '--force' => true,
            ]);

            // Step 7: Manually create Passport personal access client for the tenant
            $clientId = DB::table('oauth_clients')->insertGetId([
                'name' => 'Tenant Personal Access Client',
                'secret' => Str::random(40),
                'redirect' => 'http://localhost',
                'personal_access_client' => true,
                'password_client' => false,
                'revoked' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Step 8: Insert into oauth_personal_access_clients table
            DB::table('oauth_personal_access_clients')->insert([
                'client_id' => $clientId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Step 9: Run Seeder files for the tenant
            Artisan::call('db:seed', [
                '--class' => 'DatabaseSeeder',
                '--database' => 'tenant',
                '--force' => true,
            ]);

            // $tenantUser = TenantUser::create([
            //     'name' => $request->name,
            //     'email' => $request->email,
            //     'role_id' => $request->role_id,
            //     'password' => null,
            //     'emp_id' => $request->emp_id,
            // ]);
        } catch (Exception $e) {
            throw new Exception('Migration error: ' . $e->getMessage());
        } finally {
            // Restore default connection
            DB::setDefaultConnection(config('database.default'));
        }
    }



    public function getAllOrganizations(Request $request)
    {
        try {
            $request->validate([
                'sortBy' => 'in:name,subdomain,created_at,email',  // allowed fields to sort
                'sortOrder' => 'in:asc,desc',                // sorting order
                'perPage' => 'integer|min:1|max:100',        // limit per page items
            ]);

            $search = $request->input('search');
            $sortBy = $request->input('sortBy', 'name');
            $sortOrder = $request->input('sortOrder', 'asc');
            // $perPage = $request->input('perPage', 10);
            $perPage = min($request->input('perPage', 10), 25);
            $page = $request->input('page', 1);

            // $data = Organization::query();
            // $organizations = $data->paginate(20000);
            // return response()->json([
            //     'data' => $organizations->items(),
            //     'current_page' => $organizations->currentPage(),
            //     'last_page' => $organizations->lastPage(),
            //     'total' => $organizations->total(),
            //     'per_page' => $organizations->perPage(),
            // ], 200);

            // Redis key for caching organizations data with pagination and search options
            $redisKey = "organizations_data:{$sortBy}:{$sortOrder}:{$perPage}:{$page}:{$search}";

            // Check if data is already cached in Redis
            if (Redis::exists($redisKey)) {
                $organizations = json_decode(Redis::get($redisKey), true);
            } else {
                // Fetch data from the database
                $query = Organization::query();

                // Apply search filter
                if ($search) {
                    $query->where(function ($q) use ($search) {
                        $q->where('name', 'like', '%' . $search . '%')
                            ->orWhere('subdomain', 'like', '%' . $search . '%')
                            ->orWhere('email', 'like', '%' . $search . '%');
                    });
                }

                // Apply sorting
                $query->orderBy($sortBy, $sortOrder);

                // Get paginated results
                $organization = $query->paginate($perPage);
                // $organization = $query->paginate(50000);
                $organizations = collect($organization)->toArray();

                // Cache the data in Redis (store for 1 hour for 3600 sec)
                Redis::setex($redisKey, 3600, json_encode($organizations));
            }

            // Return response with paginated data
            return response()->json([
                'status' => 'Success',
                'data' => $organizations['data'],
                'current_page' => $organizations['current_page'],
                'last_page' => $organizations['last_page'],
                'total' => $organizations['total'],
                'per_page' => $organizations['per_page'],
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (QueryException $e) {
            return response()->json(['error' => 'Database error: ' . $e->getMessage()], 500);
        } catch (Exception $e) {
            return response()->json(['error' => 'An error occurred: ' . $e->getMessage()], 500);
        }
    }
}
