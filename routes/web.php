<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan; 

// Route::get('/run-my-database-setup-secret', function () {
//     Artisan::call('migrate:fresh', ['--seed' => true, '--force' => true]);
//     return "Database successfully migrated and seeded!";
// });
// Route::get('/', function () {
//     return view('welcome');
// });
Route::get('/', function () {
    return response()->json([
        'status' => 'ok',
        'message' => 'Live Chat Backend Running'
    ]);
});
