<?php

namespace App\Http\Controllers;

use App\Models\TenantUser;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class LoginController extends Controller
{

    /**
     * Sign up a new tenant user
     */
    public function signup(Request $request)
    {
        // Validate incoming request
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:tenant_users,email',
            'role_id' => 'required|exists:tenant_roles,id', // Ensure the role exists in tenant roles
            'password' => 'required|string|min:8',
            'emp_id' => 'required|string|unique:tenant_users,emp_id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction(); // Start transaction

        try {
            // Create new tenant user
            $tenantUser = TenantUser::create([
                'name' => $request->name,
                'email' => $request->email,
                'role_id' => $request->role_id,
                'password' => Hash::make($request->password),
                'emp_id' => $request->emp_id,
            ]);

            DB::commit(); // Commit if all goes well

            // Generate access token for the user
            $token = $tenantUser->createToken('Tenant User Token')->accessToken;

            // Return success response with the token
            return response()->json([
                'status' => 'success',
                'message' => 'User registered successfully!',
                'token' => $token,
            ], 201);
        } catch (Exception $e) {
            DB::rollBack(); // Rollback on error
            return response()->json(['error' => 'An error occurred: ' . $e->getMessage() . ' ' . $e->getLine()], 500);
        }
    }

    // For email/password login for subdomain users
    public function login(Request $request)
    {
        // Validate request data
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        // Check the correct provider based on subdomain or tenant context
        $credentials = $request->only('email', 'password');

        if (Auth::guard('tenant-api')->attempt($credentials)) {
            // Login successful
            $user = Auth::guard('tenant-api')->user();

            // Create Passport token for the user
            // $token = $user->createToken('Tenant User Token')->accessToken;
            $token = null;

            return response()->json([
                'user' => $user,
                'token' => $token,
            ], 200);
        }

        // If login fails
        return response()->json(['error' => 'Unauthorized'], 401);
    }

    // Logout for tenant users
    public function logout(Request $request)
    {
        $request->user()->token()->revoke();
        return response()->json(['message' => 'Successfully logged out']);
    }
}
