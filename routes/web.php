<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PointController;
use App\Http\Controllers\LineController;
use App\Http\Controllers\RammController;
use App\Http\Controllers\FwpController;
use App\Http\Controllers\CouncilController;

// Redirect root to login
Route::get('/', fn() => redirect()->route('login'));

// Auth routes
Route::get('/login',   [AuthController::class, 'showLogin'])->name('login');
Route::post('/login',  [AuthController::class, 'login'])->name('login.post');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Protected routes
Route::get('/dashboard', [AuthController::class, 'dashboard'])->name('dashboard');
Route::get('/maps', [AuthController::class, 'maps'])->name('maps');
Route::post('/council', [CouncilController::class, 'switch'])->name('council.switch');

// Map points (JSON API, session-guarded)
Route::get('/maps/points',  [PointController::class, 'index'])->name('points.index');
Route::post('/maps/points', [PointController::class, 'store'])->name('points.store');
Route::delete('/maps/points/{point}', [PointController::class, 'destroy'])->name('points.destroy');

// Map lines (JSON API, session-guarded)
Route::get('/maps/lines',         [LineController::class, 'index'])->name('lines.index');
Route::post('/maps/lines',        [LineController::class, 'store'])->name('lines.store');
Route::patch('/maps/lines/{line}', [LineController::class, 'update'])->name('lines.update');
Route::delete('/maps/lines/{line}', [LineController::class, 'destroy'])->name('lines.destroy');

// RAMM API test endpoints (session-guarded)
Route::get('/ramm/ping',          [RammController::class, 'ping'])->name('ramm.ping');
Route::get('/ramm/road/{roadId}', [RammController::class, 'road'])->name('ramm.road');
Route::get('/ramm/road/{roadId}/works', [RammController::class, 'works'])->name('ramm.works');
Route::get('/ramm/road/{roadId}/line', [RammController::class, 'line'])->name('ramm.line');
Route::get('/ramm/tl-debug/{roadId}', [RammController::class, 'tlDebug'])->name('ramm.tldebug');
Route::get('/ramm/fwp-probe/{roadId}', [RammController::class, 'fwpProbe'])->name('ramm.fwpprobe');
Route::get('/ramm/fwp-inspect/{roadId}', [RammController::class, 'fwpInspect'])->name('ramm.fwpinspect');

// Forward Works Programme — user overrides (edits) + review/export
Route::get('/fwp/treatments',      [FwpController::class, 'treatments'])->name('fwp.treatments');
Route::post('/fwp/override',       [FwpController::class, 'saveOverride'])->name('fwp.override.save');
Route::post('/fwp/override/delete',[FwpController::class, 'deleteOverride'])->name('fwp.override.delete');
Route::post('/fwp/sync',           [FwpController::class, 'sync'])->name('fwp.sync');
Route::get('/fwp/changes',         [FwpController::class, 'changes'])->name('fwp.changes');
Route::get('/fwp/changes.csv',     [FwpController::class, 'changesCsv'])->name('fwp.changes.csv');
Route::get('/ramm/road/{roadId}/surf-debug', [RammController::class, 'surfDebug'])->name('ramm.surfdebug');
Route::get('/ramm/road/{roadId}/works-check', [RammController::class, 'worksCheck'])->name('ramm.workscheck');
Route::get('/ramm/tables', [RammController::class, 'tables'])->name('ramm.tables');
Route::get('/ramm/table/{table}', [RammController::class, 'table'])->name('ramm.table');
