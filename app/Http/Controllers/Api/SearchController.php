<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\User;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class SearchController extends Controller
{
    /**
     * Search everything in one endpoint
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

            // 👇 **تهيئة المصفوفات أولاً لتجنب أخطاء undefined**
            $results = [
                'users' => collect(),
                'posts' => collect(),
                'tags' => collect()
            ];
            
            Log::info('Search request:', [
                'query' => $query,
                'type' => $type,
                'limit' => $limit
            ]);

            // ⭐ **تحقق إذا كان البحث عن تاغ (يبدأ بـ #)**
            $isTagSearch = str_starts_with($query, '#');
            
            if ($isTagSearch) {
                $tagName = substr($query, 1); // أزل الـ #
                Log::info('Tag search detected', ['tagName' => $tagName]);
                
                // ⭐ **البحث في التاغات**
                if ($type === 'all' || $type === 'tags') {
                    $results['tags'] = Tag::where('tag_name', 'LIKE', "%{$tagName}%")
                        ->limit($limit)
                        ->get()
                        ->map(function ($tag) {
                            return [
                                'id' => $tag->id,
                                'type' => 'tag',
                                'name' => $tag->tag_name,
                            ];
                        });
                    
                    Log::info('Tags found:', ['count' => $results['tags']->count()]);
                }
                
                // ⭐ **البحث في البوستات التي تحتوي على التاغ**
                if ($type === 'all' || $type === 'posts') {
                    $results['posts'] = Post::with(['user:id,full_name,image', 'tags:id,tag_name'])
                        ->whereHas('tags', function ($q) use ($tagName) {
                            $q->where('tag_name', 'LIKE', "%{$tagName}%");
                        })
                        ->limit($limit)
                        ->get()
                        ->map(function ($post) {
                            return [
                                'id' => $post->id,
                                'type' => 'post',
                                'title' => $post->title,
                                'caption' => $post->caption,
                                'user' => $post->user,
                                'tags' => $post->tags,
                                'created_at' => $post->created_at,
                            ];
                        });
                    
                    Log::info('Posts with tag found:', ['count' => $results['posts']->count()]);
                }
                
                // ⭐ **المستخدمين: ما في علاقة مباشرة مع التاغات**
                if ($type === 'all' || $type === 'users') {
                    $results['users'] = collect();
                    Log::info('Users search skipped for tag search');
                }
                
            } else {
                // البحث العادي بدون #
                Log::info('Normal search (not a tag)');
                
                // 🔍 Search Users
                if ($type === 'all' || $type === 'users') {
                    $results['users'] = User::where('full_name', 'LIKE', "%{$query}%")
                        ->orWhere('email', 'LIKE', "%{$query}%")
                        ->orWhere('bio', 'LIKE', "%{$query}%")
                        ->select(['id', 'full_name', 'email', 'bio', 'image', 'created_at'])
                        ->limit($limit)
                        ->get()
                        ->map(function ($user) {
                            return [
                                'id' => $user->id,
                                'type' => 'user',
                                'name' => $user->full_name,
                                'email' => $user->email,
                                'image' => $user->image,
                                'bio' => $user->bio,
                            ];
                        });
                    
                    Log::info('Users found:', ['count' => $results['users']->count()]);
                }

                // 🔍 Search Posts
                if ($type === 'all' || $type === 'posts') {
                    $results['posts'] = Post::with(['user:id,full_name,image', 'tags:id,tag_name'])
                        ->where(function ($queryBuilder) use ($query) {
                            $queryBuilder->where('title', 'LIKE', "%{$query}%")
                                         ->orWhere('caption', 'LIKE', "%{$query}%");
                        })
                        ->limit($limit)
                        ->get()
                        ->map(function ($post) {
                            return [
                                'id' => $post->id,
                                'type' => 'post',
                                'title' => $post->title,
                                'caption' => $post->caption,
                                'user' => $post->user,
                                'tags' => $post->tags,
                                'created_at' => $post->created_at,
                            ];
                        });
                    
                    Log::info('Posts found:', ['count' => $results['posts']->count()]);
                }

                // 🔍 Search Tags
                if ($type === 'all' || $type === 'tags') {
                    $results['tags'] = Tag::where('tag_name', 'LIKE', "%{$query}%")
                        ->limit($limit)
                        ->get()
                        ->map(function ($tag) {
                            return [
                                'id' => $tag->id,
                                'type' => 'tag',
                                'name' => $tag->tag_name,
                            ];
                        });
                    
                    Log::info('Tags found:', ['count' => $results['tags']->count()]);
                }
            }

            $response = [
                'success' => true,
                'query' => $query,
                'is_tag_search' => $isTagSearch,
                'type' => $type,
                'results' => $results,
                'users_count' => $results['users']->count(),
                'posts_count' => $results['posts']->count(),
                'tags_count' => $results['tags']->count(),
                'total' => $results['users']->count() + 
                          $results['posts']->count() + 
                          $results['tags']->count()
            ];

            Log::info('Search response:', $response);
            
            return response()->json($response);

        } catch (\Exception $e) {
            // 👇 **سجل الخطأ في سجلات Laravel للتحقق منه**
            Log::error('SearchController Error: ' . $e->getMessage(), [
                'query' => $query ?? 'null',
                'type' => $type ?? 'null',
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Search failed: ' . ($e->getMessage()),
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Quick search for suggestions
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

            // ⭐ **تحقق إذا كان البحث عن تاغ (يبدأ بـ #)**
            $isTagSearch = str_starts_with($query, '#');
            
            // 👇 **تهيئة النتائج أولاً**
            $results = [
                'users' => collect(),
                'posts' => collect(),
                'tags' => collect()
            ];
            
            if ($isTagSearch) {
                $tagName = substr($query, 1);
                
                $results['tags'] = Tag::where('tag_name', 'LIKE', "%{$tagName}%")
                    ->select(['id', 'tag_name'])
                    ->limit(5)
                    ->get()
                    ->map(function ($tag) {
                        $tag->display_name = '#' . $tag->tag_name;
                        return $tag;
                    });
            } else {
                $results['users'] = User::where('full_name', 'LIKE', "%{$query}%")
                    ->select(['id', 'full_name', 'image'])
                    ->limit(3)
                    ->get();
                    
                $results['posts'] = Post::where(function ($queryBuilder) use ($query) {
                        $queryBuilder->where('title', 'LIKE', "%{$query}%")
                                     ->orWhere('caption', 'LIKE', "%{$query}%");
                    })
                    ->select(['id', 'title', 'user_id', 'caption'])
                    ->with('user:id,full_name,image')
                    ->limit(3)
                    ->get();
                    
                $results['tags'] = Tag::where('tag_name', 'LIKE', "%{$query}%")
                    ->select(['id', 'tag_name'])
                    ->limit(3)
                    ->get()
                    ->map(function ($tag) {
                        $tag->display_name = '#' . $tag->tag_name;
                        return $tag;
                    });
            }

            return response()->json([
                'success' => true,
                'query' => $query,
                'is_tag_search' => $isTagSearch,
                'results' => $results
            ]);

        } catch (\Exception $e) {
            Log::error('QuickSearchController Error: ' . $e->getMessage(), [
                'query' => $query ?? 'null'
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Quick search failed',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}