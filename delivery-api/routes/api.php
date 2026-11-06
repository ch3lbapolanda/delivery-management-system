<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\Api\DispatcherOrderController;

// Public endpoints
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Protected endpoints
Route::middleware('auth:sanctum')->group(function () {

    // Authentication
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    //OrderController
    Route::middleware('role:customer')->group(function () {
      Route::post('/orders', [OrderController::class, 'store']);
      Route::get('/orders', [OrderController::class, 'index']);
      Route::get('/orders/{id}', [OrderController::class, 'show']);
      Route::post('/orders/{id}/cancel', [OrderController::class, 'cancel']);
    });

    Route::middleware('role:admin,dispatcher')
    ->prefix('dispatcher')
    ->group(function () {
        Route::get('/orders', [DispatcherOrderController::class, 'index']);
    });
    
    // Admin dashboard
    Route::get('/admin/dashboard', function () {
        return response()->json([
            'message' => 'Welcome Admin',
        ]);
    })->middleware('role:admin');

    // Dispatcher dashboard
    Route::get('/dispatcher/dashboard', function () {
        return response()->json([
            'message' => 'Welcome Dispatcher',
        ]);
    })->middleware('role:admin,dispatcher');

    // Courier dashboard
    Route::get('/courier/dashboard', function () {
        return response()->json([
            'message' => 'Welcome Courier',
        ]);
    })->middleware('role:admin,courier');

    // Customer dashboard
    Route::get('/customer/dashboard', function () {
        return response()->json([
            'message' => 'Welcome Customer',
        ]);
    })->middleware('role:admin,customer');

});
