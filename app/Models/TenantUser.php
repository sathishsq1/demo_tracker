<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\HasApiTokens;

class TenantUser extends Model
{
    use Notifiable, HasApiTokens, SoftDeletes;

    protected $table = 'tenant_users'; // The tenant-specific users table

    // This model's connection will be set dynamically
    protected $connection = 'tenant';

    // Tenant-specific fillable attributes
    protected $fillable = ['name', 'email', 'password', 'role_id', 'emp_id'];

    // Hidden attributes
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Dynamically set the tenant's database connection.
     *
     * @param  string  $databaseName
     * @return void
     */
    public static function setTenantConnection($databaseName)
    {
        config(['database.connections.tenant.database' => $databaseName]);
        DB::setDefaultConnection('tenant');
    }
}
