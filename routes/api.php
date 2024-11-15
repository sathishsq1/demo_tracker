<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\SSOController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');



Route::prefix('sso')->group(function () {
    Route::get('/login/{provider}', [AuthController::class, 'redirectToProvider']);
    Route::get('/login/microsoft/callback', [AuthController::class, 'handleMicrosoftCallback']);
    Route::get('/login/zoho/callback', [AuthController::class, 'handleZohoCallback']);
    Route::post('/signup', [AuthController::class, 'signup']);
    Route::get('/signin', [AuthController::class, 'signin']);
});

Route::post('/create-organization', [OrganizationController::class, 'createOrganization'])->middleware('auth:api');
Route::get('/organizations', [OrganizationController::class, 'getAllOrganizations'])->middleware('auth:api');


// Route::domain('{organization}.yourapp.com')->group(function () {
Route::prefix('tenant')->group(function () {
    // Email-Password Auth Routes
    Route::post('/login', [LoginController::class, 'login']);
    Route::post('/logout', [LoginController::class, 'logout']);
    Route::post('/signup', [LoginController::class, 'signup'])->middleware('tenant');
 
    // SSO Routes
    Route::get('/auth/{provider}', [SSOController::class, 'redirectToProvider']);
    Route::get('/auth/{provider}/callback', [SSOController::class, 'handleProviderCallback']);

    // Project Routes (protected routes)
    Route::middleware(['tenant', 'auth:tenant-api'])->group(function () {
        Route::get('/projects', [ProjectController::class, 'index']);
        Route::post('/projects', [ProjectController::class, 'store']);
    });
});