<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Organization extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'subdomain',
        'database_name',
        'azure_client_id',
        'azure_client_secret',
        'azure_tenant_id',
        'azure_redirect_uri',
        'zoho_client_id',
        'zoho_client_secret',
        'zoho_redirect_uri'
    ];
}
