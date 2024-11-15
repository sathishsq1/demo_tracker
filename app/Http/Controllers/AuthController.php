<?php

namespace App\Http\Controllers;

use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Validator;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    public function redirectToProvider($provider)
    {
        return Response::json([
            'url' => Socialite::driver($provider)->stateless()->redirect()->getTargetUrl(),
        ]);
    }


    public function handleMicrosoftCallback()
    {
        $ssoUser = Socialite::driver('azure')->stateless()->user();
        $user = User::where('email', $ssoUser->getEmail())->first();

        if (!$user) {
            return response()->json(['error' => 'User not found'], 422);
        } else {
            $user_data = $this->prepareUserData($user);
            $access_token = $this->getAccessToken($user);
            return response()->json([
                'user' => $user_data,
                'token' => $access_token,
            ]);
        }
    }

    public function handleZohoCallback(Request $request)
    {
        $ssoUser = Socialite::driver('zoho')->stateless()->user();
        $user = User::where('email', $ssoUser->email)->first();

        if (!$user) {
            return response()->json(['error' => 'User not found'], 422);
        } else {
            $user_data = $this->prepareUserData($user);
            $access_token = $this->getAccessToken($user);
            return response()->json([
                'user' => $user_data,
                'token' => $access_token,
            ]);
        }
    }

    private function prepareUserData($user)
    {
        $user_data = [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
        ];

        return $user_data;
    }

    private function getAccessToken($user): String
    {
        $accessToken = $user->createToken('authToken')->accessToken;
        return $accessToken;
    }



    public function signup(Request $request)
    {
        // Validate incoming request
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'role' => 'required', // Ensure the role exists in tenant roles
            'password' => 'required|string|min:8',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction(); // Start transaction

        try {
            // Create new tenant user
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'role' => $request->role,
                'password' => Hash::make($request->password),
            ]);

            DB::commit(); // Commit if all goes well

            // Generate access token for the user
            $token = $user->createToken('Super Admin User Token')->accessToken;

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


    public function signin(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|string|email|max:255',
                'password' => 'required|string|min:8',
            ]);
            if ($validator->fails()) {
                return response(['errors' => $validator->errors()->all()], 422);
            }
            $user = User::where('email', $request->email)->first();
            if ($user) {
                if (Hash::check($request->password, $user->password)) {
                    $token = $user->createToken('Super Admin User Token')->accessToken;
                    $response = ['message' => 'Login Successfully', 'data' => $user, 'token' => $token];
                    return response($response, 200);
                } else {
                    $response = ["message" => "Password mismatch"];
                    return response($response, 422);
                }
            } else {
                $response = ["message" => 'User does not exist'];
                return response($response, 422);
            }
        } catch (Exception $e) {
            return response()->json(['error' => 'An error occurred: ' . $e->getMessage() . ' ' . $e->getLine()], 500);
        }
    }
}
