<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class CommentController extends Controller
{
    /**
     * عرض جميع التعليقات (مع الفلترة والترتيب)
     */
    public function index(Request $request)
    {
        try {
            // فلترة حسب البوست إذا أردت
            $query = Comment::with(['user:id,full_name,image', 'post:id,title'])
                ->orderBy('created_at', 'desc');

            // فلترة حسب البوست
            if ($request->has('post_id')) {
                $query->where('post_id', $request->post_id);
            }

            // فلترة حسب المستخدم
            if ($request->has('user_id')) {
                $query->where('user_id', $request->user_id);
            }

            // البحث في نص التعليق
            if ($request->has('search')) {
                $query->where('comment_text', 'LIKE', '%' . $request->search . '%');
            }

            $comments = $query->paginate($request->per_page ?? 15);

            return response()->json([
                'success' => true,
                'data' => $comments,
                'message' => 'تم جلب التعليقات بنجاح'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب التعليقات',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * إنشاء تعليق جديد
     */
    public function store(Request $request)
{
    try {
        // التحقق من المستخدم المصادق
        if (!Auth::check()) {
            return response()->json([
                'success' => false,
                'message' => 'يجب تسجيل الدخول أولاً'
            ], 401);
        }

        // التحقق من البيانات
        $validated = $request->validate([
            'post_id' => 'required|exists:posts,id',
            'comment_text' => 'required|string|max:1000',
        ], [
            'post_id.required' => 'معرف المنشور مطلوب',
            'post_id.exists' => 'المنشور غير موجود',
            'comment_text.required' => 'نص التعليق مطلوب',
            'comment_text.max' => 'نص التعليق يجب أن لا يتجاوز 1000 حرف'
        ]);

        // التحقق من وجود المنشور
        $post = Post::find($validated['post_id']);
        if (!$post) {
            return response()->json([
                'success' => false,
                'message' => 'المنشور غير موجود'
            ], 404);
        }

        // إنشاء التعليق
        $comment = Comment::create([
            'user_id' => Auth::id(),
            'post_id' => $validated['post_id'],
            'comment_text' => $validated['comment_text']
        ]);

        // تحميل علاقة المستخدم
        $comment->load(['user:id,full_name,image']);

        // إنشاء إشعار (اختياري)
        // $this->createCommentNotification($comment, $post);

        return response()->json([
            'success' => true,
            'data' => $comment,
            'message' => 'تم إضافة التعليق بنجاح',
            'comments_count' => Comment::where('post_id', $validated['post_id'])->count()
        ], 201);
        
    } catch (\Illuminate\Validation\ValidationException $e) {
        // هذا خاص بال validation errors
        return response()->json([
            'success' => false,
            'errors' => $e->errors(),
            'message' => 'فشل التحقق من البيانات'
        ], 422);
        
    } catch (\Exception $e) {
        // هذا لجميع الأخطاء الأخرى
        return response()->json([
            'success' => false,
            'message' => 'حدث خطأ أثناء إضافة التعليق',
            'error' => $e->getMessage(),
            'trace' => env('APP_DEBUG') ? $e->getTraceAsString() : null
        ], 500);
    }
}
    /**
     * عرض تعليق معين
     */
    public function show($id)
    {
        try {
            $comment = Comment::with([
                'user:id,full_name,image,bio',
                'post:id,title,user_id',
                'post.user:id,full_name,image'
            ])->find($id);

            if (!$comment) {
                return response()->json([
                    'success' => false,
                    'message' => 'التعليق غير موجود'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $comment,
                'message' => 'تم جلب التعليق بنجاح'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب التعليق',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * تحديث تعليق
     */
    public function update(Request $request, $id)
    {
        try {
            $comment = Comment::where('user_id', Auth::id())->find($id);

            if (!$comment) {
                return response()->json([
                    'success' => false,
                    'message' => 'التعليق غير موجود أو ليس لديك صلاحية التعديل'
                ], 404);
            }

            // التحقق من البيانات
            $validator = Validator::make($request->all(), [
                'comment_text' => 'required|string|min:1|max:1000'
            ], [
                'comment_text.required' => 'نص التعليق مطلوب',
                'comment_text.min' => 'نص التعليق يجب أن يكون على الأقل حرفاً واحداً',
                'comment_text.max' => 'نص التعليق يجب أن لا يتجاوز 1000 حرف'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors(),
                    'message' => 'فشل التحقق من البيانات'
                ], 422);
            }

            // تحديث التعليق
            $comment->update([
                'comment_text' => $request->comment_text,
                'updated_at' => now() // تحديث وقت التعديل فقط
            ]);

            $comment->load(['user:id,full_name,image']);

            return response()->json([
                'success' => true,
                'data' => $comment,
                'message' => 'تم تحديث التعليق بنجاح'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء تحديث التعليق',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * حذف تعليق
     */
    public function destroy($id)
    {
        try {
            $comment = Comment::where('user_id', Auth::id())->find($id);

            if (!$comment) {
                return response()->json([
                    'success' => false,
                    'message' => 'التعليق غير موجود أو ليس لديك صلاحية الحذف'
                ], 404);
            }

            // حفظ post_id قبل الحذف
            $postId = $comment->post_id;

            // حذف التعليق
            $comment->delete();



            return response()->json([
                'success' => true,
                'message' => 'تم حذف التعليق بنجاح',
                'remaining_comments' => Comment::where('post_id', $postId)->count() // إرجاع العدد المتبقي
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء حذف التعليق',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * تعليقات منشور معين
     */
    public function postComments($postId, Request $request)
    {
        try {
            $post = Post::find($postId);

            if (!$post) {
                return response()->json([
                    'success' => false,
                    'message' => 'المنشور غير موجود'
                ], 404);
            }

            $comments = Comment::with(['user:id,full_name,image'])
                ->where('post_id', $postId)
                ->orderBy('created_at', $request->sort ?? 'desc')
                ->paginate($request->per_page ?? 15);

            return response()->json([
                'success' => true,
                'data' => [
                    'post' => [
                        'id' => $post->id,
                        'title' => $post->title,
                        'comments_count' => $comments->total() // استخدام total() من الباجينيت
                    ],
                    'comments' => $comments
                ],
                'message' => 'تعليقات المنشور'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب تعليقات المنشور',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * تعليقاتي (المستخدم الحالي)
     */
    public function myComments(Request $request)
    {
        try {
            $comments = Comment::with([
                'post:id,title,user_id', // ← إضافة user_id هنا
                'post.user:id,full_name,image' // ← تحميل المستخدم مع المنشور
            ])
                ->where('user_id', Auth::id())
                ->orderBy('created_at', 'desc')
                ->paginate($request->per_page ?? 10);

            return response()->json([
                'success' => true,
                'data' => $comments,
                'message' => 'تم جلب تعليقاتك بنجاح',
                'total_comments' => $comments->total(),
                'user_info' => [
                    'id' => Auth::id(),
                    'name' => Auth::user()->full_name
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب تعليقاتك',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * تعليقات مستخدم معين
     */
    public function userComments($userId, Request $request)
    {
        try {
            $comments = Comment::with(['post:id,title', 'user:id,full_name,image'])
                ->where('user_id', $userId)
                ->orderBy('created_at', 'desc')
                ->paginate($request->per_page ?? 15);

            return response()->json([
                'success' => true,
                'data' => $comments,
                'message' => 'تعليقات المستخدم',
                'user_id' => $userId,
                'total_comments' => $comments->total()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب تعليقات المستخدم',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * البحث في التعليقات
     */
    public function search(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'keyword' => 'required|string|min:2'
            ], [
                'keyword.required' => 'كلمة البحث مطلوبة',
                'keyword.min' => 'الكلمة المفتاحية يجب أن تكون على الأقل حرفين'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $comments = Comment::with(['user:id,full_name,image', 'post:id,title'])
                ->where('comment_text', 'LIKE', '%' . $request->keyword . '%')
                ->orderBy('created_at', 'desc')
                ->paginate($request->per_page ?? 15);

            return response()->json([
                'success' => true,
                'data' => $comments,
                'message' => 'نتائج البحث في التعليقات',
                'keyword' => $request->keyword,
                'total_results' => $comments->total()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء البحث',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * عدد التعليقات لمنشور
     */
    public function commentsCount($postId)
    {
        try {
            $count = Comment::where('post_id', $postId)->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'post_id' => $postId,
                    'comments_count' => $count
                ],
                'message' => 'عدد التعليقات'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء حساب عدد التعليقات',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * أحدث التعليقات
     */
    public function latestComments(Request $request)
    {
        try {
            $limit = $request->limit ?? 10;

            $comments = Comment::with(['user:id,full_name,image', 'post:id,title'])
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $comments,
                'message' => 'أحدث التعليقات',
                'total' => $comments->count()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب أحدث التعليقات',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 🔧 دالة مساعدة: إضافة عمود is_edited للتعليقات
     * (اختيارية - تحتاج إلى ميجريشن إذا أردت استخدامها)
     */
    public function addIsEditedColumnToComments()
    {
        // يمكنك إنشاء ميجريشن لهذا الغرض:
        // php artisan make:migration add_is_edited_to_comments_table

        /*
        Schema::table('comments', function (Blueprint $table) {
            $table->boolean('is_edited')->default(false)->after('comment_text');
        });
        */
    }
}
