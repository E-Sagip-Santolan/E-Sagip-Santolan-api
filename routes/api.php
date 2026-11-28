<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SkillController;
use App\Http\Controllers\VolunteerController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json([
    'status' => 'ok',
    'service' => 'esagip-api',
    'time' => now(),
]));

Route::get('/skills', [SkillController::class, 'index'])
    ->middleware('throttle:api');

Route::post('/register', [AuthController::class, 'register'])
    ->middleware('throttle:register');

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:login');

Route::post('/admin/login', [AuthController::class, 'adminLogin'])
    ->middleware('throttle:login');

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [ProfileController::class, 'show']);
    Route::put('/me', [ProfileController::class, 'update']);

    Route::middleware('permission:volunteer_approvals')->group(function () {
        Route::get('/volunteers', [VolunteerController::class, 'index']);
        Route::get('/volunteers/summary', [VolunteerController::class, 'summary']);
    });
});
