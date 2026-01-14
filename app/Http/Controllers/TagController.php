<?php

namespace App\Http\Controllers;

use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TagController extends Controller
{
    /**
     * عرض جميع الوسوم
     */
    public function index()
    {
        try {
            $tags = Tag::withCount('posts') // ✅ إضافة عدد المنشورات
                ->orderBy('created_at', 'desc')
                ->get();
            return response()->json([
                'success' => true,
                'data' => $tags
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب الوسوم',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * عرض وسم محدد
     */
    public function show($id)
    {
        try {
            $tag = Tag::withCount('posts') // ✅ إضافة عدد المنشورات
                ->find($id);
            
            if (!$tag) {
                return response()->json([
                    'success' => false,
                    'message' => 'الوسم غير موجود'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'data' => $tag
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب الوسم',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * إنشاء وسم جديد
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tag_name' => 'required|string|max:255|unique:tags,tag_name'
        ], [
            'tag_name.required' => 'اسم الوسم مطلوب',
            'tag_name.unique' => 'اسم الوسم موجود مسبقاً',
            'tag_name.max' => 'اسم الوسم يجب ألا يتجاوز 255 حرف'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $tag = Tag::create([
                'tag_name' => $request->tag_name
            ]);

            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء الوسم بنجاح',
                'data' => $tag
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء إنشاء الوسم',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * تحديث وسم موجود
     */
    public function update(Request $request, $id)
    {
        $tag = Tag::find($id);
        
        if (!$tag) {
            return response()->json([
                'success' => false,
                'message' => 'الوسم غير موجود'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'tag_name' => 'required|string|max:255|unique:tags,tag_name,' . $id
        ], [
            'tag_name.required' => 'اسم الوسم مطلوب',
            'tag_name.unique' => 'اسم الوسم موجود مسبقاً',
            'tag_name.max' => 'اسم الوسم يجب ألا يتجاوز 255 حرف'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $tag->update([
                'tag_name' => $request->tag_name
            ]);

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث الوسم بنجاح',
                'data' => $tag
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء تحديث الوسم',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * حذف وسم
     */
    public function destroy($id)
    {
        $tag = Tag::find($id);
        
        if (!$tag) {
            return response()->json([
                'success' => false,
                'message' => 'الوسم غير موجود'
            ], 404);
        }

        try {
            // تحقق إذا كان الوسم مرتبطاً بأي منشورات
            if ($tag->posts()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'لا يمكن حذف الوسم لأنه مرتبط بمنشورات' // ✅ تعديل الرسالة
                ], 400);
            }

            $tag->delete();

            return response()->json([
                'success' => true,
                'message' => 'تم حذف الوسم بنجاح'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء حذف الوسم',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * جلب المنشورات المرتبطة بوسم معين
     */
    public function getPosts($id)
    {
        try {
            $tag = Tag::with(['posts.user', 'posts.tags']) // ✅ تحميل المنشورات مع المستخدم والتاغات
                ->find($id);
            
            if (!$tag) {
                return response()->json([
                    'success' => false,
                    'message' => 'الوسم غير موجود'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $tag->posts()->paginate(10) // ✅ إضافة pagination
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب المنشورات',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * البحث في الوسوم
     */
    public function search(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'keyword' => 'required|string|min:1'
        ], [
            'keyword.required' => 'Search keyword required',
            'keyword.min' => 'The search word must be at least two letters long'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $tags = Tag::where('tag_name', 'like', '%' . $request->keyword . '%')
                ->withCount('posts') // ✅ إضافة عدد المنشورات
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $tags
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء البحث',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}