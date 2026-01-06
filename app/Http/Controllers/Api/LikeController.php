<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Like;
use App\Models\Notification;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class LikeController extends Controller
{
    /**
     * عرض جميع الإعجابات (لأغراض الإدارة)
     */
    public function index()
    {
        try {
            $likes = Like::with(['user:id,full_name,image', 'post:id,title'])
                ->orderBy('created_at', 'desc')
                ->paginate(20);

            return response()->json([
                'success' => true,
                'data' => $likes,
                'message' => 'تم جلب الإعجابات بنجاح'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب الإعجابات',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * إضافة أو إزالة إعجاب (toggle like)
     * إذا كان المستخدم معجب بالفعل، يتم إزالة الإعجاب
     * وإلا يتم إضافة إعجاب جديد
     */
    // public function toggle(Request $request)
    // {
    //     // التحقق من المستخدم المصادق
    //     if (!Auth::check()) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'يجب تسجيل الدخول أولاً'
    //         ], 401);
    //     }

    //     $validator = Validator::make($request->all(), [
    //         'post_id' => 'required|exists:posts,id'
    //     ], [
    //         'post_id.required' => 'معرف المنشور مطلوب',
    //         'post_id.exists' => 'المنشور غير موجود'
    //     ]);

    //     if ($validator->fails()) {
    //         return response()->json([
    //             'success' => false,
    //             'errors' => $validator->errors()
    //         ], 422);
    //     }

    //     try {
    //         $userId = Auth::id();
    //         $postId = $request->post_id;

    //         // البحث عن إعجاب موجود
    //         $existingLike = Like::where('user_id', $userId)
    //             ->where('post_id', $postId)
    //             ->first();

    //         if ($existingLike) {
    //             // إذا كان الإعجاب موجوداً، قم بإزالته
    //             $existingLike->delete();

    //             // تحديث عدد الإعجابات في المنشور
    //             $this->updatePostLikesCount($postId);

    //             return response()->json([
    //                 'success' => true,
    //                 'action' => 'removed',
    //                 'message' => 'تم إزالة الإعجاب بنجاح',
    //                 'data' => [
    //                     'post_id' => $postId,
    //                     'likes_count' => Post::find($postId)->likes()->count()
    //                 ]
    //             ]);
    //         } else {
    //             // إذا لم يكن الإعجاب موجوداً، قم بإضافته
    //             $like = Like::create([
    //                 'user_id' => $userId,
    //                 'post_id' => $postId
    //             ]);

    //             // تحميل العلاقات
    //             $like->load(['user:id,full_name,image']);

    //             // تحديث عدد الإعجابات في المنشور
    //             $this->updatePostLikesCount($postId);

    //             return response()->json([
    //                 'success' => true,
    //                 'action' => 'added',
    //                 'message' => 'تم إضافة الإعجاب بنجاح',
    //                 'data' => [
    //                     'like' => $like,
    //                     'post_id' => $postId,
    //                     'likes_count' => Post::find($postId)->likes()->count()
    //                 ]
    //             ], 201);
    //         }

    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'حدث خطأ أثناء معالجة الإعجاب',
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }

    public function toggle(Request $request)
{
    if (!Auth::check()) {
        return response()->json([
            'success' => false,
            'message' => 'يجب تسجيل الدخول أولاً'
        ], 401);
    }

    $validator = Validator::make($request->all(), [
        'post_id' => 'required|exists:posts,id'
    ], [
        'post_id.required' => 'معرف المنشور مطلوب',
        'post_id.exists'   => 'المنشور غير موجود'
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'errors'  => $validator->errors()
        ], 422);
    }

    try {
        $userId = Auth::id();
        $postId = $request->post_id;

        $existingLike = Like::where('user_id', $userId)
            ->where('post_id', $postId)
            ->first();

        if ($existingLike) {
            // إزالة اللايك
            $existingLike->delete();
            $this->updatePostLikesCount($postId);

            return response()->json([
                'success' => true,
                'action'  => 'removed',
                'message' => 'تم إزالة الإعجاب بنجاح',
                'data'    => [
                    'post_id'     => $postId,
                    'likes_count' => Post::find($postId)->likes()->count()
                ]
            ]);
        } else {
            // إضافة لايك جديد
            $like = Like::create([
                'user_id' => $userId,
                'post_id' => $postId
            ]);

            $like->load(['user:id,full_name,image']);
            $this->updatePostLikesCount($postId);

            // 🔔 توليد إشعار لصاحب البوست
            $postOwnerId = Post::find($postId)->user_id;
            if ($postOwnerId !== $userId) {
                Notification::create([
                    'user_id'  => $postOwnerId,   // المستلم (صاحب البوست)
                    'actor_id' => $userId,        // الفاعل (يلي عمل لايك)
                    'type'     => 'like',
                    'post_id'  => $postId,
                    'is_read'  => false,
                ]);
            }

            return response()->json([
                'success' => true,
                'action'  => 'added',
                'message' => 'تم إضافة الإعجاب بنجاح',
                'data'    => [
                    'like'        => $like,
                    'post_id'     => $postId,
                    'likes_count' => Post::find($postId)->likes()->count()
                ]
            ], 201);
        }

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'حدث خطأ أثناء معالجة الإعجاب',
            'error'   => $e->getMessage()
        ], 500);
    }
}

    /**
     * التحقق إذا كان المستخدم معجب بالمنشور
     */
    public function check(Request $request)
    {
        if (!Auth::check()) {
            return response()->json([
                'success' => false,
                'message' => 'يجب تسجيل الدخول أولاً'
            ], 401);
        }

        $validator = Validator::make($request->all(), [
            'post_id' => 'required|exists:posts,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $isLiked = Like::where('user_id', Auth::id())
                ->where('post_id', $request->post_id)
                ->exists();

            return response()->json([
                'success' => true,
                'data' => [
                    'is_liked' => $isLiked,
                    'post_id' => $request->post_id,
                    'user_id' => Auth::id()
                ],
                'message' => $isLiked ? 'المستخدم معجب بهذا المنشور' : 'المستخدم غير معجب بهذا المنشور'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء التحقق من الإعجاب',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * جلب الإعجابات لمنشور معين
     */
    public function getPostLikes($postId)
    {
        try {
            // التحقق من وجود المنشور
            $post = Post::find($postId);
            
            if (!$post) {
                return response()->json([
                    'success' => false,
                    'message' => 'المنشور غير موجود'
                ], 404);
            }

            $likes = Like::with(['user:id,full_name,image'])
                ->where('post_id', $postId)
                ->orderBy('created_at', 'desc')
                ->paginate(15);

            return response()->json([
                'success' => true,
                'data' => [
                    'post' => $post->only(['id', 'title']),
                    'likes' => $likes,
                    'total_likes' => $post->likes()->count()
                ],
                'message' => 'تم جلب الإعجابات بنجاح'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب إعجابات المنشور',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * جلب إعجابات المستخدم الحالي
     */
    public function myLikes()
    {
        if (!Auth::check()) {
            return response()->json([
                'success' => false,
                'message' => 'يجب تسجيل الدخول أولاً'
            ], 401);
        }

        try {
            $likes = Like::with(['post:id,title,caption,images', 'post.user:id,full_name,image'])
                ->where('user_id', Auth::id())
                ->orderBy('created_at', 'desc')
                ->paginate(15);

            return response()->json([
                'success' => true,
                'data' => $likes,
                'message' => 'تم جلب المنشورات المعجبة بنجاح'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب إعجاباتك',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * إزالة إعجاب محدد
     */
    public function destroy($id)
    {
        if (!Auth::check()) {
            return response()->json([
                'success' => false,
                'message' => 'يجب تسجيل الدخول أولاً'
            ], 401);
        }

        try {
            $like = Like::where('user_id', Auth::id())
                ->find($id);

            if (!$like) {
                return response()->json([
                    'success' => false,
                    'message' => 'الإعجاب غير موجود أو ليس لديك صلاحية'
                ], 404);
            }

            $postId = $like->post_id;
            $like->delete();

            // تحديث عدد الإعجابات
            $this->updatePostLikesCount($postId);

            return response()->json([
                'success' => true,
                'message' => 'تم إزالة الإعجاب بنجاح',
                'data' => [
                    'post_id' => $postId,
                    'likes_count' => Post::find($postId)->likes()->count()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء إزالة الإعجاب',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * جلب الإعجابات حسب المستخدم
     */
    public function getUserLikes($userId)
    {
        try {
            $likes = Like::with(['post:id,title,caption,images', 'post.user:id,full_name,image'])
                ->where('user_id', $userId)
                ->orderBy('created_at', 'desc')
                ->paginate(15);

            return response()->json([
                'success' => true,
                'data' => $likes,
                'message' => 'تم جلب إعجابات المستخدم بنجاح'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب إعجابات المستخدم',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * الحصول على عدد الإعجابات لمنشور معين
     */
    public function getLikesCount($postId)
    {
        try {
            $post = Post::find($postId);
            
            if (!$post) {
                return response()->json([
                    'success' => false,
                    'message' => 'المنشور غير موجود'
                ], 404);
            }

            $count = $post->likes()->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'post_id' => $postId,
                    'likes_count' => $count
                ],
                'message' => 'تم جلب عدد الإعجابات بنجاح'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب عدد الإعجابات',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * دالة مساعدة لتحديث عدد الإعجابات في المنشور
     */
    private function updatePostLikesCount($postId)
    {
        try {
            $post = Post::find($postId);
            if ($post) {
                // يمكنك إضافة منطق إضافي هنا إذا كان لديك حقل cached count
                // مثال: $post->likes_count = $post->likes()->count();
                // $post->save();
            }
        } catch (\Exception $e) {
            Log::error('خطأ في تحديث عدد الإعجابات: ' . $e->getMessage());
        }
    }

    /**
     * جلب أعلى المنشورات إعجاباً
     */
/**
 * الحصول على المنشورات الأكثر إعجاباً
 */
public function getTopLikedPosts(Request $request)
    {
        try {
            $limit = $request->get('limit', 10);
            
            $topPosts = Post::withCount('likes')
                ->with(['user:id,full_name,image'])
                ->orderBy('likes_count', 'desc')
                ->limit($limit)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $topPosts,
                'message' => 'تم جلب المنشورات الأعلى إعجاباً بنجاح'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب المنشورات الأعلى إعجاباً',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}