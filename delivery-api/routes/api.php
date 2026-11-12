<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PricingController;
use App\Http\Controllers\Api\DispatcherOrderController;
use App\Http\Controllers\Api\DispatcherAssignmentController;
use App\Http\Controllers\Api\CourierOrderController;
use App\Http\Controllers\Api\CourierDeliveryProofController;
use App\Http\Controllers\Api\CourierAvailabilityController;
use App\Http\Controllers\Api\CourierLocationController;
use App\Http\Controllers\AdminDashboardController;


// ==========================================
// PUBLIC ENDPOINTS
// ==========================================

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);


// ==========================================
// PROTECTED ENDPOINTS
// ==========================================

Route::middleware('auth:sanctum')->group(function () {

    // Authentication
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);


    // ======================================
    // CUSTOMER
    // ======================================

    Route::middleware('role:customer')->group(function () {

        Route::post('/orders', [OrderController::class, 'store']);

        Route::get('/orders', [OrderController::class, 'index']);

        Route::get('/orders/{id}', [OrderController::class, 'show']);

        Route::post('/orders/{id}/cancel', [OrderController::class, 'cancel']);

        Route::post('/pricing/estimate', [
            PricingController::class,
            'estimate',
        ]);
    });


    // ======================================
    // DISPATCHER
    // ======================================

    Route::middleware('role:admin,dispatcher')
        ->prefix('dispatcher')
        ->group(function () {

            Route::get('/orders', [
                DispatcherOrderController::class,
                'index',
            ]);

            Route::get('/orders/{id}', [
                DispatcherOrderController::class,
                'show',
            ]);

            Route::get('/couriers', [
                DispatcherAssignmentController::class,
                'couriers',
            ]);

            Route::get('/courier-availability', [
                DispatcherAssignmentController::class,
                'availabilityOverview',
            ]);

            Route::post('/orders/{id}/assign', [
                DispatcherAssignmentController::class,
                'assign',
            ]);
        });


    // ======================================
    // COURIER
    // ======================================

    Route::middleware('role:admin,courier')
        ->prefix('courier')
        ->group(function () {

            // Courier-only endpoints
            Route::middleware('role:courier')->group(function () {

                Route::get('/availability', [
                    CourierAvailabilityController::class,
                    'show',
                ]);

                Route::patch('/availability', [
                    CourierAvailabilityController::class,
                    'update',
                ]);

                Route::post('/orders/{id}/location', [
                    CourierLocationController::class,
                    'store',
                ]);
            });

            Route::get('/orders', [
                CourierOrderController::class,
                'index',
            ]);

            Route::get('/orders/{id}', [
                CourierOrderController::class,
                'show',
            ]);

            Route::patch('/orders/{id}/status', [
                CourierOrderController::class,
                'updateStatus',
            ]);

            Route::post('/orders/{id}/proof', [
                CourierDeliveryProofController::class,
                'store',
            ]);
        });


    // ======================================
    // ADMIN
    // ======================================

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


    // ======================================
    // DASHBOARD ENDPOINTS
    // ======================================

    Route::get('/dispatcher/dashboard', function () {
        return response()->json([
            'message' => 'Welcome Dispatcher',
        ]);
    })->middleware('role:admin,dispatcher');


    Route::get('/courier/dashboard', function () {
        return response()->json([
            'message' => 'Welcome Courier',
        ]);
    })->middleware('role:admin,courier');


    Route::get('/customer/dashboard', function () {
        return response()->json([
            'message' => 'Welcome Customer',
        ]);
    })->middleware('role:admin,customer');

});

