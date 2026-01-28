<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\User;
use App\Models\Tag;
use App\Models\Like;
use App\Models\Comment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class SearchController extends Controller
{
    /**
     * Search everything in one endpoint - نسخة مع اللايكات والتعليقات
     */
    public function search(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'q' => 'required|string|min:2|max:255',
                'type' => 'nullable|in:all,users,posts,tags',
                'limit' => 'nullable|integer|min:1|max:50',
            ]);

            $query = $request->input('q');
            $type = $request->input('type', 'all');
            $limit = $request->input('limit', 15);

            // 👇 **تهيئة النتائج**
            $results = [
                'users' => collect(),
                'posts' => collect(),
                'tags' => collect()
            ];
            
            $isTagSearch = str_starts_with($query, '#');
            
            if ($isTagSearch) {
                $tagName = substr($query, 1);
                
                // 🔍 **البحث في التاغات**
                if ($type === 'all' || $type === 'tags') {
                    $results['tags'] = Tag::where('tag_name', 'LIKE', "%{$tagName}%")
                        ->limit($limit)
                        ->get();
                }
                
                // 🔍 **البحث في البوستات للتاغ**
                if ($type === 'all' || $type === 'posts') {
                    $results['posts'] = Post::with(['user:id,full_name,image', 'tags:id,tag_name'])
                        ->whereHas('tags', function ($q) use ($tagName) {
                            $q->where('tag_name', 'LIKE', "%{$tagName}%");
                        })
                        ->limit($limit)
                        ->get()
                        ->map(function ($post) {
                            return $this->formatPostWithCounts($post);
                        });
                }
                
            } else {
                // البحث العادي
                
                // 🔍 **المستخدمين**
                if ($type === 'all' || $type === 'users') {
                    $results['users'] = User::where('full_name', 'LIKE', "%{$query}%")
                        ->orWhere('email', 'LIKE', "%{$query}%")
                        ->orWhere('bio', 'LIKE', "%{$query}%")
                        ->select(['id', 'full_name', 'email', 'bio', 'image', 'created_at'])
                        ->limit($limit)
                        ->get();
                }

                // 🔍 **البوستات - مع اللايكات والتعليقات**
                if ($type === 'all' || $type === 'posts') {
                    $results['posts'] = Post::with(['user:id,full_name,image', 'tags:id,tag_name'])
                        ->where(function ($queryBuilder) use ($query) {
                            $queryBuilder->where('title', 'LIKE', "%{$query}%")
                                         ->orWhere('caption', 'LIKE', "%{$query}%");
                        })
                        ->limit($limit)
                        ->get()
                        ->map(function ($post) {
                            return $this->formatPostWithCounts($post);
                        });
                }

                // 🔍 **التاغات**
                if ($type === 'all' || $type === 'tags') {
                    $results['tags'] = Tag::where('tag_name', 'LIKE', "%{$query}%")
                        ->limit($limit)
                        ->get();
                }
            }

            // ⭐ **تنسيق النتائج**
            $formattedResults = [
                'users' => $results['users']->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'type' => 'user',
                        'full_name' => $user->full_name,
                        'email' => $user->email,
                        'image' => $user->image,
                        'bio' => $user->bio,
                        'created_at' => $user->created_at,
                    ];
                }),
                'posts' => $results['posts']->filter(),
                'tags' => $results['tags']->map(function ($tag) {
                    return [
                        'id' => $tag->id,
                        'type' => 'tag',
                        'tag_name' => $tag->tag_name,
                        'created_at' => $tag->created_at,
                    ];
                }),
            ];

            return response()->json([
                'success' => true,
                'query' => $query,
                'is_tag_search' => $isTagSearch,
                'type' => $type,
                'results' => $formattedResults,
                'users_count' => $formattedResults['users']->count(),
                'posts_count' => $formattedResults['posts']->count(),
                'tags_count' => $formattedResults['tags']->count(),
                'total' => $formattedResults['users']->count() + 
                          $formattedResults['posts']->count() + 
                          $formattedResults['tags']->count()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Search failed: ' . $e->getMessage(),
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * دالة مساعدة لإضافة اللايكات والتعليقات للبوست
     */
    private function formatPostWithCounts($post)
    {
        try {
            // الحصول على عدد اللايكات والتعليقات بشكل منفصل
            $likesCount = DB::table('likes')->where('post_id', $post->id)->count();
            $commentsCount = DB::table('comments')->where('post_id', $post->id)->count();
            
            return [
                'id' => $post->id,
                'type' => 'post',
                'title' => $post->title ?? '',
                'caption' => $post->caption ?? '',
                'content' => $post->content ?? ($post->caption ?? ''),
                'user' => $post->user ? [
                    'id' => $post->user->id,
                    'full_name' => $post->user->full_name ?? 'Unknown',
                    'image' => $post->user->image,
                ] : null,
                'tags' => $post->tags ? $post->tags->map(function ($tag) {
                    return [
                        'id' => $tag->id,
                        'tag_name' => $tag->tag_name,
                    ];
                })->toArray() : [],
                'images' => $post->images ?? [],
                'created_at' => $post->created_at,
                'updated_at' => $post->updated_at,
                'likes_count' => $likesCount,
                'comments_count' => $commentsCount,
                '_count' => [
                    'likes' => $likesCount,
                    'comments' => $commentsCount,
                ]
            ];
        } catch (\Exception $e) {
            Log::warning('Error formatting post ' . $post->id . ': ' . $e->getMessage());
            return null;
        }
    }

    /**
 * Quick search for suggestions - نسخة تعمل بدون أخطاء
 */
public function quickSearch(Request $request): JsonResponse
{
    try {
        $query = $request->input('q', '');
        
        if (empty($query)) {
            return response()->json([
                'success' => true,
                'query' => $query,
                'results' => []
            ]);
        }
        
        $isTagSearch = str_starts_with($query, '#');
        
        if ($isTagSearch) {
            $tagName = substr($query, 1);
            
            $tags = Tag::where('tag_name', 'LIKE', "%{$tagName}%")
                ->select(['id', 'tag_name'])
                ->limit(5)
                ->get()
                ->map(function ($tag) {
                    return [
                        'id' => $tag->id,
                        'tag_name' => $tag->tag_name
                    ];
                })
                ->values() // ⭐ أضف values() هنا
                ->all();
            
            return response()->json([
                'success' => true,
                'query' => $query,
                'is_tag_search' => $isTagSearch,
                'results' => [
                    'users' => [],
                    'posts' => [],
                    'tags' => $tags
                ]
            ]);
        } else {
            // 🔍 **المستخدمين**
            $users = User::where('full_name', 'LIKE', "%{$query}%")
                ->select(['id', 'full_name', 'image', 'email'])
                ->limit(3)
                ->get()
                ->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'full_name' => $user->full_name,
                        'image' => $user->image,
                        'email' => $user->email
                    ];
                })
                ->values() // ⭐ أضف values() هنا
                ->all();
                
            // 🔍 **البوستات - التعديل المهم!**
            $posts = Post::where('title', 'LIKE', "%{$query}%")
                ->orWhere('caption', 'LIKE', "%{$query}%")
                ->select(['id', 'title', 'caption', 'user_id', 'created_at'])
                ->with('user:id,full_name,image') // ⭐ تأكد من with
                ->limit(3)
                ->get()
                ->map(function ($post) {
                    // ⭐ تأكد من إضافة بيانات المستخدم
                    $userData = null;
                    if ($post->user) {
                        $userData = [
                            'id' => $post->user->id,
                            'full_name' => $post->user->full_name,
                            'image' => $post->user->image
                        ];
                    } elseif ($post->user_id) {
                        // إذا فشل with، جلب المستخدم يدوياً
                        $user = User::find($post->user_id);
                        if ($user) {
                            $userData = [
                                'id' => $user->id,
                                'full_name' => $user->full_name,
                                'image' => $user->image
                            ];
                        }
                    }
                    
                    return [
                        'id' => $post->id,
                        'title' => $post->title,
                        'caption' => $post->caption,
                        'user_id' => $post->user_id,
                        'created_at' => $post->created_at,
                        'user' => $userData, // ⭐ تأكد من إضافة user هنا
                        'type' => 'post',
                    ];
                })
                ->values() // ⭐ أضف values() هنا
                ->all();
                
            // 🔍 **التاغات**
            $tags = Tag::where('tag_name', 'LIKE', "%{$query}%")
                ->select(['id', 'tag_name'])
                ->limit(3)
                ->get()
                ->map(function ($tag) {
                    return [
                        'id' => $tag->id,
                        'tag_name' => $tag->tag_name
                    ];
                })
                ->values() // ⭐ أضف values() هنا
                ->all();

            return response()->json([
                'success' => true,
                'query' => $query,
                'is_tag_search' => $isTagSearch,
                'results' => [
                    'users' => $users,
                    'posts' => $posts,
                    'tags' => $tags
                ]
            ]);
        }

    } catch (\Exception $e) {
        // ⭐ أضف log للخطأ
        \Illuminate\Support\Facades\Log::error('Quick search error: ' . $e->getMessage(), [
            'query' => $query ?? 'null',
            'trace' => $e->getTraceAsString()
        ]);
        
        return response()->json([
            'success' => false,
            'message' => 'Quick search failed'
        ], 500);
    }
}
}