<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class TenantDatabaseSwitcher
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $subdomain = explode('.', $request->getHost())[0];

        // Find organization by subdomain
        // $organization = Organization::where('subdomain', $subdomain)->first(); //when production uncomment this line
        $organization = Organization::where('subdomain', 'new_tenant')->first();

        if ($organization) {
            // Set the database connection dynamically
            Config::set('database.connections.tenant', [
                'driver' => 'mysql',
                'host' => env('DB_HOST'),
                'database' => $organization->database_name,
                'username' => env('DB_USERNAME'),
                'password' => env('DB_PASSWORD'),
            ]);

            DB::setDefaultConnection('tenant');
        }

        return $next($request);
    }
}
