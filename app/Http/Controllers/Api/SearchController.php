<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\User;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

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

            $results = [];

            // 🔍 Search Users
            if ($type === 'all' || $type === 'users') {
                $users = User::where('full_name', 'LIKE', "%{$query}%")
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
                
                $results['users'] = $users;
            }

            // 🔍 Search Posts
            if ($type === 'all' || $type === 'posts') {
                $posts = Post::with(['user:id,full_name,image', 'tags:id,tag_name'])
                    ->where('title', 'LIKE', "%{$query}%")
                    ->orWhere('caption', 'LIKE', "%{$query}%")
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
                
                $results['posts'] = $posts;
            }

            // 🔍 Search Tags
            if ($type === 'all' || $type === 'tags') {
                $tags = Tag::where('tag_name', 'LIKE', "%{$query}%")
                    ->limit($limit)
                    ->get()
                    ->map(function ($tag) {
                        return [
                            'id' => $tag->id,
                            'type' => 'tag',
                            'name' => $tag->tag_name,
                        ];
                    });
                
                $results['tags'] = $tags;
            }

            return response()->json([
                'success' => true,
                'query' => $query,
                'type' => $type,
                'results' => $results,
                'users_count' => $results['users']->count() ?? 0,
                'posts_count' => $results['posts']->count() ?? 0,
                'tags_count' => $results['tags']->count() ?? 0,
                'total' => ($results['users']->count() ?? 0) + 
                          ($results['posts']->count() ?? 0) + 
                          ($results['tags']->count() ?? 0)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Search failed',
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

            // Search all in one query
            $results = [
                'users' => User::where('full_name', 'LIKE', "%{$query}%")
                    ->select(['id', 'full_name', 'image'])
                    ->limit(3)
                    ->get(),
                'posts' => Post::where('title', 'LIKE', "%{$query}%")
                    ->select(['id', 'title', 'user_id'])
                    ->with('user:id,full_name,image')
                    ->limit(3)
                    ->get(),
                'tags' => Tag::where('tag_name', 'LIKE', "%{$query}%")
                    ->select(['id', 'tag_name'])
                    ->limit(3)
                    ->get(),
            ];

            return response()->json([
                'success' => true,
                'query' => $query,
                'results' => $results
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Quick search failed'
            ], 500);
        }
    }
}