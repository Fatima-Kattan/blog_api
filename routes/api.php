<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\LikeController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\FollowController;
use App\Http\Controllers\PostTagController;

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
        Route::get('/posts/search', [PostController::class, 'search']);
        Route::get('/posts/{post}', [PostController::class, 'show']);
        Route::put('/posts/{post}', [PostController::class, 'update']);
        Route::delete('/posts/{post}', [PostController::class, 'destroy']);
        
        Route::post('/posts/{post}/images', [PostController::class, 'addImages']);
        Route::delete('/posts/{post}/images', [PostController::class, 'removeImage']);
        
        Route::get('/posts/user/{userId}', [PostController::class, 'userPosts']);
        Route::get('/my/posts', [PostController::class, 'myPosts']);
        
        Route::post('/validate-images', [PostController::class, 'validateImageUrls']);
        Route::get('/posts/{post}/image-count', [PostController::class, 'getImageCount']);
        
        Route::post('/likes/toggle', [LikeController::class, 'toggle']);

        // التحقق من الإعجاب
        Route::post('/likes/check', [LikeController::class, 'check']);

        // إعجاباتي (المستخدم الحالي)
        Route::get('/likes/my-likes', [LikeController::class, 'myLikes']);

        // حذف إعجاب محدد
        Route::delete('/likes/{id}', [LikeController::class, 'destroy']);

        Route::post('follows', [FollowController::class, 'store']);
        Route::delete('follows/{id}', [FollowController::class, 'destroy']);
        Route::get('follows', [FollowController::class, 'index']);
        Route::get('users/{id}/followers', [FollowController::class, 'followers']);
        Route::get('users/{id}/followings', [FollowController::class, 'followings']);

        Route::prefix('post-tags')->group(function () {
            Route::post('/{postId}', [PostTagController::class, 'store']);
            Route::delete('/{postId}/{tagId}', [PostTagController::class, 'destroy']);
            Route::put('/{postId}/sync', [PostTagController::class, 'sync']);
        });
    });
    Route::get('/likes', [LikeController::class, 'index']); // جميع الإعجابات (للإدارة)
    Route::get('/posts/{postId}/likes', [LikeController::class, 'getPostLikes']); // إعجابات منشور معين
    Route::get('/users/{userId}/likes', [LikeController::class, 'getUserLikes']); // إعجابات مستخدم معين
    Route::get('/posts/{postId}/likes-count', [LikeController::class, 'getLikesCount']); // عدد إعجابات منشور
    Route::prefix('post-tags')->group(function () {
        Route::get('/{postId}', [PostTagController::class, 'index']);
        Route::get('/tag/{tagId}/posts', [PostTagController::class, 'postsByTag']);
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
Route::get('/posts/top-liked', [LikeController::class, 'getTopLikedPosts']);