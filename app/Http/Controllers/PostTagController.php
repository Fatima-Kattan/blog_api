<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\Tag;
use Illuminate\Http\Request;

class PostTagController extends Controller
{
    /**
     * ربط تاغ مع منشور
     */
    public function store(Request $request, $postId)
    {
        $validated = $request->validate([
            'tag_id' => 'required|exists:tags,id'
        ]);
        
        $post = Post::findOrFail($postId);
        
        // التحقق من عدم وجود التاغ مسبقاً
        if ($post->tags()->where('tag_id', $validated['tag_id'])->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'التاغ موجود مسبقاً'
            ], 422);
        }
        
        $post->tags()->attach($validated['tag_id']);
        
        return response()->json([
            'success' => true,
            'message' => 'تم ربط التاغ بالمنشور'
        ], 201);
    }
    
    /**
     * إزالة تاغ من منشور
     */
    public function destroy($postId, $tagId)
    {
        $post = Post::findOrFail($postId);
        $post->tags()->detach($tagId);
        
        return response()->json([
            'success' => true,
            'message' => 'تم إزالة التاغ من المنشور'
        ]);
    }
    
    /**
     * عرض تاغات منشور مع تفاصيل
     */
    public function index($postId)
    {
        $post = Post::with(['tags' => function($query) {
            $query->withCount('posts'); // عدد المنشورات لكل تاغ
        }])->findOrFail($postId);
        
        return response()->json([
            'success' => true,
            'data' => $post->tags,
            'post' => [
                'id' => $post->id,
                'title' => $post->title
            ]
        ]);
    }
    
    /**
     * مزامنة التاغات (استبدال الكل)
     */
    public function sync(Request $request, $postId)
    {
        $validated = $request->validate([
            'tag_ids' => 'required|array',
            'tag_ids.*' => 'exists:tags,id'
        ]);
        
        $post = Post::findOrFail($postId);
        
        // الحصول على التاغات الحالية والجديدة
        $currentTags = $post->tags->pluck('id')->toArray();
        $newTags = $validated['tag_ids'];
        
        // العمليات التي تمت
        $added = array_diff($newTags, $currentTags);
        $removed = array_diff($currentTags, $newTags);
        
        $post->tags()->sync($newTags);
        
        return response()->json([
            'success' => true,
            'message' => 'تم مزامنة التاغات',
            'data' => [
                'added_tags' => $added,
                'removed_tags' => $removed,
                'total_tags' => count($newTags)
            ]
        ]);
    }
    
    /**
     * البحث عن منشورات بتاغ معين
     */
    // في PostTagController.php
public function postsByTag($tagId)
{
    $tag = Tag::with(['posts.user', 'posts.tags'])
              ->findOrFail($tagId);
    
    // ⭐ **أضف withCount هنا!**
    $posts = $tag->posts()
        ->with(['user:id,full_name,image', 'tags'])
        ->withCount(['likes', 'comments']) // ⭐ ⭐ ⭐ ⭐ ⭐
        ->paginate(10);
    
    return response()->json([
        'success' => true,
        'data' => [
            'tag' => $tag,
            'posts' => $posts
        ]
    ]);
}
}