<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;

class TenantRole extends Model
{
    use Notifiable, HasApiTokens;

    protected $table = 'tenant_roles';

    // This model's connection will be set dynamically
    protected $connection = 'tenant';

    protected $fillable = [
        'role',
    ];
}
