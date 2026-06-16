<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\FriendshipController;

/*
|--------------------------------------------------------------------------
| Public Routes (Anyone can access these without a token)
|--------------------------------------------------------------------------
*/
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/users/offline', [ChatController::class, 'setUserOffline']);

/*
|--------------------------------------------------------------------------
| Protected Routes (Requires a valid JWT Authorization Token)
|--------------------------------------------------------------------------
| The 'auth:api' middleware intercepts these routes, reads the incoming 
| request headers, checks for the token, and verifies it with our JWT driver.
| */
Route::middleware('auth:api')->group(function () {
    
    // Auth cleanup route
    Route::post('/logout', [AuthController::class, 'logout']);
    
    // Chat layout data routes
    Route::get('/users', [ChatController::class, 'getUsers']);
    Route::get('/messages/{receiverId}', [ChatController::class, 'getMessages']);
    Route::post('/messages', [ChatController::class, 'sendMessage']);
    Route::post('/messages/seen/{senderId}', [ChatController::class, 'markAsSeen']);

    Route::get('/suggested-friends', [FriendshipController::class, 'getRandomUsers']);
    Route::get('/pending-requests', [FriendshipController::class, 'getPendingRequests']);
    Route::post('/friend-request/send', [FriendshipController::class, 'sendRequest']);
    Route::post('/friend-request/accept', [FriendshipController::class, 'acceptRequest']);
    Route::post('/friend-request/decline', [FriendshipController::class, 'declineRequest']);
    Route::post('/friend-request/remove', [FriendshipController::class, 'removeFriend']);
    Route::delete('/messages/{id}', [ChatController::class, 'deleteMessage']);
    Route::post('/messages/clear/{friendId}', [ChatController::class, 'clearChat']);
    
});