<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\Api\DispatcherOrderController;
use App\Http\Controllers\Api\DispatcherAssignmentController;
use App\Http\Controllers\Api\CourierOrderController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\Api\CourierDeliveryProofController;
use App\Http\Controllers\Api\CourierAvailabilityController;

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
      Route::post('/pricing/estimate', [
    \App\Http\Controllers\PricingController::class,
    'estimate',
]);
    });

    Route::middleware('role:admin,dispatcher')
    ->prefix('dispatcher')
    ->group(function () {
      Route::get('/orders', [DispatcherOrderController::class, 'index']);
      Route::get('/orders/{id}', [DispatcherOrderController::class, 'show']);
      Route::get('/couriers', [DispatcherAssignmentController::class, 'couriers']);
      Route::post('/orders/{id}/assign', [DispatcherAssignmentController::class, 'assign']);
    });

    Route::middleware('role:admin,courier')
    ->prefix('courier')
    ->group(function () {
      Route::middleware('role:courier')->group(function () {
    Route::get('/availability', [
        CourierAvailabilityController::class,
        'show',
    ]);

    Route::patch('/availability', [
        CourierAvailabilityController::class,
        'update',
    ]);
});
        Route::get('/orders', [CourierOrderController::class, 'index']);
        Route::get('/orders/{id}', [CourierOrderController::class, 'show']);
      Route::patch('/orders/{id}/status', [
      CourierOrderController::class,
      'updateStatus']);
      Route::post('/orders/{id}/proof', [
      CourierDeliveryProofController::class,
      'store',
    ]);
    });
    
    
    // Admin endpoints
    Route::middleware('role:admin')
        ->prefix('admin')
        ->group(function () {
            Route::get('/dashboard', [
                AdminDashboardController::class,
                'index',
            ]);
    
            Route::get('/orders', [
                AdminDashboardController::class,
                'orders',
            ]);
    
            Route::get('/users', [
                AdminDashboardController::class,
                'users',
            ]);
        });

    

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
