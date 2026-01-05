<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\Api\PostController;

Route::prefix('v1')->group(function () {
    
    // 🔵 الروتات العامة (بدون مصادقة)
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    
    // 🔵 الروتات الخاصة (تتطلب مصادقة)
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/user', [AuthController::class, 'user']);
        Route::put('/user/profile', [AuthController::class, 'updateProfile']);
        Route::put('/user/password', [AuthController::class, 'updatePassword']);
        Route::post('/user/image', [AuthController::class, 'updateProfilePicture']);
        Route::delete('/user/account', [AuthController::class, 'deleteAccount']);
        
        Route::get('/posts', [PostController::class, 'index']);
        Route::post('/posts', [PostController::class, 'store']);
        Route::get('/posts/{post}', [PostController::class, 'show']);
        Route::put('/posts/{post}', [PostController::class, 'update']);
        Route::delete('/posts/{post}', [PostController::class, 'destroy']);
        
        Route::post('/posts/{post}/images', [PostController::class, 'addImages']);
        Route::delete('/posts/{post}/images', [PostController::class, 'removeImage']);
        
        Route::get('/posts/user/{userId}', [PostController::class, 'userPosts']);
        Route::get('/my/posts', [PostController::class, 'myPosts']);
        Route::get('/posts/search', [PostController::class, 'search']);
        
        Route::post('/validate-images', [PostController::class, 'validateImageUrls']);
        Route::get('/posts/{post}/image-count', [PostController::class, 'getImageCount']);
    });
});

Route::prefix('tags')->group(function () {
    Route::get('/', [TagController::class, 'index']);
    Route::post('/', [TagController::class, 'store']);
    Route::get('/search', [TagController::class, 'search']);
    Route::get('/{id}', [TagController::class, 'show']);
    Route::put('/{id}', [TagController::class, 'update']);
    Route::delete('/{id}', [TagController::class, 'destroy']);
    Route::get('/{id}/posts', [TagController::class, 'getPosts']);
});