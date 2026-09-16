<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\DiagnosticScan\DiagnosticScanController;
use App\Http\Controllers\Disease\DiseaseController;
use App\Http\Controllers\DiseaseSupply\DiseaseSupplyController;
use App\Http\Controllers\Plot\PlotController;
use App\Http\Controllers\Sector\SectorController;
use App\Http\Controllers\Supply\SupplyController;
use App\Http\Controllers\User\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('diagnostic-scans')->middleware('auth:api')->group(function () {
    Route::get('list', [DiagnosticScanController::class, 'index']);
    Route::get('list/{id}', [DiagnosticScanController::class, 'show'])->whereNumber('id');
    Route::get('by-sector/{sectorId}', [DiagnosticScanController::class, 'bySector'])->whereNumber('sectorId');
    Route::get('by-plot/{plotId}', [DiagnosticScanController::class, 'byPlot'])->whereNumber('plotId');
    Route::post('create/{sectorId}', [DiagnosticScanController::class, 'store'])->whereNumber('sectorId');
    Route::put('update/{id}', [DiagnosticScanController::class, 'update'])->whereNumber('id');
    Route::patch('update/{id}', [DiagnosticScanController::class, 'patch'])->whereNumber('id');
    Route::delete('delete/{id}', [DiagnosticScanController::class, 'destroy'])->whereNumber('id');
});

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    // The guard refresh flow validates the refresh window, including expired JWTs.
    Route::post('/refresh', [AuthController::class, 'refresh']);

    Route::middleware('auth:api')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
    });
});

Route::prefix('plots')->middleware('auth:api')->group(function () {
    Route::get('list', [PlotController::class, 'index']);
    Route::get('list/{id}', [PlotController::class, 'show'])->whereNumber('id');
    Route::post('create', [PlotController::class, 'store']);
    Route::put('update/{id}', [PlotController::class, 'update'])->whereNumber('id');
    Route::patch('update/{id}', [PlotController::class, 'patch'])->whereNumber('id');
    Route::delete('delete/{id}', [PlotController::class, 'destroy'])->whereNumber('id');
});

Route::prefix('sectors')->middleware('auth:api')->group(function () {
    Route::get('by-plot/{plotId}', [SectorController::class, 'byPlot'])->whereNumber('plotId');
    Route::post('create/{plotId}', [SectorController::class, 'store'])->whereNumber('plotId');
    Route::get('list/', [SectorController::class, 'index']);
    Route::get('list/{id}', [SectorController::class, 'show'])->whereNumber('id');
    Route::put('update/{id}', [SectorController::class, 'update'])->whereNumber('id');
    Route::patch('update/{id}', [SectorController::class, 'patch'])->whereNumber('id');
    Route::delete('delete/{id}', [SectorController::class, 'destroy'])->whereNumber('id');
});

Route::prefix('diseases')->middleware('auth:api')->group(function () {
    Route::get('{diseaseId}/supplies', [DiseaseSupplyController::class, 'byDisease'])->whereNumber('diseaseId');
    Route::get('list/', [DiseaseController::class, 'index']);
    Route::get('list/{id}', [DiseaseController::class, 'show'])->whereNumber('id');
});

// Catalog reads are available to any authenticated account.
Route::prefix('supplies')->middleware('auth:api')->group(function () {
    Route::get('list/', [SupplyController::class, 'index']);
    Route::get('list/{id}', [SupplyController::class, 'show'])->whereNumber('id');
});

// Administrative operations require authentication before role authorization.
Route::prefix('disease-supplies')->middleware('auth:api')->group(function () {
    Route::get('list', [DiseaseSupplyController::class, 'index']);
    Route::get('list/{id}', [DiseaseSupplyController::class, 'show'])->whereNumber('id');
});

Route::prefix('disease-supplies')->middleware(['auth:api', 'role:admin'])->group(function () {
    Route::post('create', [DiseaseSupplyController::class, 'store']);
    Route::put('update/{id}', [DiseaseSupplyController::class, 'update'])->whereNumber('id');
    Route::patch('update/{id}', [DiseaseSupplyController::class, 'patch'])->whereNumber('id');
    Route::delete('delete/{id}', [DiseaseSupplyController::class, 'destroy'])->whereNumber('id');
});

Route::middleware(['auth:api', 'role:admin'])->group(function () {
    Route::prefix('users')->group(function () {
        Route::get('list', [UserController::class, 'index']);
        Route::get('list/{id}', [UserController::class, 'show'])->whereNumber('id');
        Route::post('create', [UserController::class, 'store']);
        Route::put('update/{id}', [UserController::class, 'update'])->whereNumber('id');
        Route::patch('update/{id}', [UserController::class, 'patch'])->whereNumber('id');
        Route::delete('delete/{id}', [UserController::class, 'destroy'])->whereNumber('id');
    });

    Route::prefix('supplies')->group(function () {
        Route::post('create', [SupplyController::class, 'store']);
        Route::put('update/{id}', [SupplyController::class, 'update'])->whereNumber('id');
        Route::patch('update/{id}', [SupplyController::class, 'patch'])->whereNumber('id');
        Route::delete('delete/{id}', [SupplyController::class, 'destroy'])->whereNumber('id');
    });
});
