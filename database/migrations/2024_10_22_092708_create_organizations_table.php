<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();  //optional
            $table->string('subdomain')->unique();
            $table->string('database_name')->unique();
            
            // Azure SSO Configuration
            $table->string('azure_client_id')->nullable();
            $table->string('azure_client_secret')->nullable();
            $table->string('azure_tenant_id')->nullable();
            $table->string('azure_redirect_uri')->nullable();
            
            // Zoho SSO Configuration
            $table->string('zoho_client_id')->nullable();
            $table->string('zoho_client_secret')->nullable();
            $table->string('zoho_redirect_uri')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
