<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\TenantUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Laravel\Socialite\Facades\Socialite;

class SSOController extends Controller
{
    // Redirect to provider (Microsoft, Zoho) for SSO
    public function redirectToProvider($provider)
    {
        // Get organization by subdomain
        $organization = $this->getOrganizationFromSubdomain();

        // Set dynamic SSO configurations based on the organization
        $this->setSSOConfig($provider, $organization);

        // Redirect to provider (Microsoft or Zoho)
        return Socialite::driver($provider)->redirect();
    }

    // Handle provider callback
    public function handleProviderCallback($provider)
    {
        $organization = $this->getOrganizationFromSubdomain();
        $this->setSSOConfig($provider, $organization);

        // Get user info from the provider
        $user = Socialite::driver($provider)->user();

        // Here you can map SSO user info to your tenant user model
        // For example:
        $tenantUser = TenantUser::where('email', $user->email)->first();

        if (!$tenantUser) {
            return response()->json(['error' => 'User not found'], 422);
        } else {
            $user_data = $this->prepareUserData($tenantUser);
            $access_token = $this->getAccessToken($tenantUser);
            return response()->json([
                'user' => $user_data,
                'token' => $access_token,
            ]);
        }
        // Log in the tenant user
        Auth::guard('tenant-api')->login($tenantUser);

        // Generate Passport token for the user
        $token = $tenantUser->createToken('Tenant User SSO Token')->accessToken;

        return response()->json([
            'user' => $tenantUser,
            'token' => $token,
        ]);
    }

    private function prepareUserData($tenantUser)
    {
        $user_data = [
            'name' => $tenantUser->name,
            'email' => $tenantUser->email,
            'role_id' => $tenantUser->role_id,
            'emp_id' => $tenantUser->emp_id,
        ];

        return $user_data;
    }

    private function getAccessToken($tenantUser): String
    {
        $accessToken = $tenantUser->createToken('authToken')->accessToken;
        return $accessToken;
    }

    // Helper to get organization based on subdomain
    private function getOrganizationFromSubdomain()
    {
        // $subdomain = explode('.', request()->getHost())[0];
        // return Organization::where('subdomain', $subdomain)->firstOrFail();

        return Organization::where('subdomain', 'new_tenant')->firstOrFail();
    }

    // Dynamic SSO configuration
    private function setSSOConfig($provider, $organization)
    {
        if ($provider === 'azure') {
            Config::set('services.azure', [
                'client_id' => $organization->azure_client_id,
                'client_secret' => $organization->azure_client_secret,
                'tenant_id' => $organization->azure_tenant_id,
                'redirect' => $organization->azure_redirect_uri,
            ]);
        } elseif ($provider === 'zoho') {
            Config::set('services.zoho', [
                'client_id' => $organization->zoho_client_id,
                'client_secret' => $organization->zoho_client_secret,
                'redirect' => $organization->zoho_redirect_uri,
            ]);
        }
    }
}
