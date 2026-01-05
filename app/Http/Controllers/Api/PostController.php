<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PostController extends Controller
{
    /**
     * عرض جميع المنشورات مع العلاقات
     */
    public function index()
    {
        $posts = Post::with([
            'user:id,full_name,image',
            'likes.user:id,full_name',
            'comments.user:id,full_name'
        ])
        ->withCount(['likes', 'comments'])
        ->orderBy('created_at', 'desc')
        ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $posts,
            'message' => 'تم جلب المنشورات بنجاح'
        ]);
    }

    /**
     * إنشاء منشور جديد
     */
    public function store(Request $request)
    {
        // التحقق من المستخدم المصادق
        if (!Auth::check()) {
            return response()->json([
                'success' => false,
                'message' => 'يجب تسجيل الدخول أولاً'
            ], 401);
        }

        // التحقق من البيانات
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'caption' => 'required|string',
            'images' => 'nullable|array|max:4',
            'images.*' => 'nullable|url|max:500',
        ], [
            'title.required' => 'العنوان مطلوب',
            'caption.required' => 'الوصف مطلوب',
            'images.max' => 'يمكنك إضافة ما لا يزيد عن 4 صور',
            'images.*.url' => 'يجب أن تكون الرابط صحيحاً',
            'images.*.max' => 'طول الرابط يجب أن لا يتجاوز 500 حرف'
        ]);

        // تنظيف الصور من القيم الفارغة
        $images = isset($validated['images']) ? array_filter($validated['images']) : [];

        // التحقق من عدد الصور
        if (count($images) > 4) {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن إضافة أكثر من 4 صور',
                'images_count' => count($images)
            ], 422);
        }

        // إنشاء المنشور
        try {
            $post = Post::create([
                'user_id' => Auth::id(),
                'title' => $validated['title'],
                'caption' => $validated['caption'],
                'images' => $images
            ]);

            // تحميل علاقة المستخدم
            $post->load(['user:id,full_name,image']);

            return response()->json([
                'success' => true,
                'data' => $post,
                'message' => 'تم إنشاء المنشور بنجاح'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء إنشاء المنشور',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * عرض منشور معين
     */
    public function show($id)
    {
        $post = Post::with([
            'user:id,full_name,image,bio',
            'likes.user:id,full_name,image',
            'comments.user:id,full_name,image',
            'comments.replies.user:id,full_name,image'
        ])
        ->withCount(['likes', 'comments'])
        ->find($id);

        if (!$post) {
            return response()->json([
                'success' => false,
                'message' => 'المنشور غير موجود'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $post,
            'message' => 'تم جلب المنشور بنجاح'
        ]);
    }

    /**
     * تحديث منشور
     */
    public function update(Request $request, $id)
    {
        // البحث عن المنشور المملوك للمستخدم الحالي
        $post = Post::where('user_id', Auth::id())->find($id);

        if (!$post) {
            return response()->json([
                'success' => false,
                'message' => 'المنشور غير موجود أو ليس لديك صلاحية التعديل'
            ], 404);
        }

        // التحقق من البيانات
        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'caption' => 'sometimes|required|string',
            'images' => 'sometimes|array|max:4',
            'images.*' => 'sometimes|url|max:500',
        ], [
            'images.max' => 'يمكنك إضافة ما لا يزيد عن 4 صور',
            'images.*.url' => 'يجب أن تكون الرابط صحيحاً'
        ]);

        try {
            // إذا كانت هناك صور جديدة، استبدلها
            if (isset($validated['images'])) {
                $images = array_filter($validated['images']); // تنظيف القيم الفارغة
                
                // التحقق من عدد الصور
                if (count($images) > 4) {
                    return response()->json([
                        'success' => false,
                        'message' => 'لا يمكن أن يكون لديك أكثر من 4 صور في المنشور',
                        'images_count' => count($images)
                    ], 422);
                }
                
                $post->update([
                    'title' => $validated['title'] ?? $post->title,
                    'caption' => $validated['caption'] ?? $post->caption,
                    'images' => $images
                ]);
            } else {
                $post->update([
                    'title' => $validated['title'] ?? $post->title,
                    'caption' => $validated['caption'] ?? $post->caption
                ]);
            }

            // تحميل علاقة المستخدم
            $post->load(['user:id,full_name,image']);

            return response()->json([
                'success' => true,
                'data' => $post,
                'message' => 'تم تحديث المنشور بنجاح'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء تحديث المنشور',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * إضافة صور إلى منشور موجود
     */
    public function addImages(Request $request, $id)
    {
        $post = Post::where('user_id', Auth::id())->find($id);

        if (!$post) {
            return response()->json([
                'success' => false,
                'message' => 'المنشور غير موجود أو ليس لديك صلاحية'
            ], 404);
        }

        // التحقق من البيانات
        $validated = $request->validate([
            'images' => 'required|array|max:4',
            'images.*' => 'required|url|max:500'
        ], [
            'images.max' => 'يمكنك إضافة ما لا يزيد عن 4 صور دفعة واحدة',
            'images.*.url' => 'يجب أن تكون الرابط صحيحاً'
        ]);

        // الحصول على الصور الحالية
        $currentImages = $post->images ?? [];
        
        // الصور الجديدة
        $newImages = array_filter($validated['images']);
        
        // التحقق من العدد الإجمالي
        $totalImages = array_merge($currentImages, $newImages);
        
        if (count($totalImages) > 4) {
            $availableSlots = 4 - count($currentImages);
            
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن إضافة أكثر من 4 صور للمنشور',
                'current_images_count' => count($currentImages),
                'new_images_count' => count($newImages),
                'available_slots' => $availableSlots,
                'suggestion' => $availableSlots > 0 ? 
                    "يمكنك إضافة {$availableSlots} صور فقط" : 
                    "يجب حذف بعض الصور أولاً"
            ], 422);
        }

        try {
            // دمج الصور
            $post->update([
                'images' => $totalImages
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'post' => $post->fresh(),
                    'added_count' => count($newImages),
                    'total_images' => count($totalImages)
                ],
                'message' => 'تم إضافة الصور بنجاح'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء إضافة الصور',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * حذف صورة من منشور
     */
    public function removeImage(Request $request, $id)
    {
        $post = Post::where('user_id', Auth::id())->find($id);

        if (!$post) {
            return response()->json([
                'success' => false,
                'message' => 'المنشور غير موجود أو ليس لديك صلاحية'
            ], 404);
        }

        // التحقق من البيانات
        $validated = $request->validate([
            'image_url' => 'required|url'
        ], [
            'image_url.required' => 'رابط الصورة مطلوب',
            'image_url.url' => 'يجب أن يكون الرابط صحيحاً'
        ]);

        $currentImages = $post->images ?? [];
        
        // البحث عن الصورة وإزالتها
        $imageIndex = array_search($validated['image_url'], $currentImages);
        
        if ($imageIndex === false) {
            return response()->json([
                'success' => false,
                'message' => 'الصورة غير موجودة في المنشور'
            ], 404);
        }

        try {
            // إزالة الصورة
            unset($currentImages[$imageIndex]);
            $currentImages = array_values($currentImages); // إعادة ترتيب المفاتيح

            $post->update([
                'images' => $currentImages
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'post' => $post->fresh(),
                    'remaining_images' => count($currentImages)
                ],
                'message' => 'تم حذف الصورة بنجاح'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء حذف الصورة',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * حذف منشور
     */
    public function destroy($id)
    {
        $post = Post::where('user_id', Auth::id())->find($id);

        if (!$post) {
            return response()->json([
                'success' => false,
                'message' => 'المنشور غير موجود أو ليس لديك صلاحية الحذف'
            ], 404);
        }

        try {
            $post->delete();

            return response()->json([
                'success' => true,
                'message' => 'تم حذف المنشور بنجاح'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء حذف المنشور',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * منشورات مستخدم معين
     */
    public function userPosts($userId)
    {
        $user = User::find($userId);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'المستخدم غير موجود'
            ], 404);
        }

        $posts = Post::withCount(['likes', 'comments'])
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $user->only(['id', 'full_name', 'image']),
                'posts' => $posts
            ],
            'message' => 'تم جلب منشورات المستخدم بنجاح'
        ]);
    }

    /**
     * منشوراتي (المستخدم الحالي)
     */
    public function myPosts()
    {
        $posts = Post::withCount(['likes', 'comments'])
            ->where('user_id', Auth::id())
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $posts,
            'message' => 'تم جلب منشوراتك بنجاح'
        ]);
    }

    /**
     * البحث في المنشورات
     */
    public function search(Request $request)
    {
        // التحقق من البيانات
        $validated = $request->validate([
            'keyword' => 'required|string|min:2'
        ], [
            'keyword.required' => 'كلمة البحث مطلوبة',
            'keyword.min' => 'الكلمة المفتاحية يجب أن تكون على الأقل حرفين'
        ]);

        $keyword = $validated['keyword'];

        $posts = Post::with(['user:id,full_name,image'])
            ->withCount(['likes', 'comments'])
            ->where('title', 'LIKE', "%{$keyword}%")
            ->orWhere('caption', 'LIKE', "%{$keyword}%")
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $posts,
            'message' => 'نتائج البحث'
        ]);
    }

    /**
     * التحقق من روابط الصور (اختياري)
     */
    public function validateImageUrls(Request $request)
    {
        // التحقق من البيانات
        $validated = $request->validate([
            'images' => 'required|array|max:4',
            'images.*' => 'required|url|max:500'
        ], [
            'images.required' => 'الصور مطلوبة',
            'images.max' => 'لا يمكن إضافة أكثر من 4 صور',
            'images.*.url' => 'يجب أن تكون الروابط صحيحة'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'جميع روابط الصور صالحة',
            'images_count' => count($validated['images'])
        ]);
    }

    /**
     * تعداد الصور في منشور
     */
    public function getImageCount($id)
    {
        $post = Post::find($id);

        if (!$post) {
            return response()->json([
                'success' => false,
                'message' => 'المنشور غير موجود'
            ], 404);
        }

        $images = $post->images ?? [];
        
        return response()->json([
            'success' => true,
            'data' => [
                'post_id' => $post->id,
                'total_images' => count($images),
                'available_slots' => 4 - count($images),
                'images' => $images
            ],
            'message' => 'تم جلب عدد الصور بنجاح'
        ]);
    }
}