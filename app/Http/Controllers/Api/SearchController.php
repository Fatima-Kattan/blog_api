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
     * Global search across all data types
     */
    public function globalSearch(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'query' => 'required|string|min:2|max:255',
                'type' => 'nullable|in:all,users,posts,tags',
                'limit' => 'nullable|integer|min:1|max:50',
            ]);

            $query = $request->input('query');
            $type = $request->input('type', 'all');
            $limit = $request->input('limit', 15);
            $user = $request->user();

            $results = [];

            // 🔍 Search Users
            if ($type === 'all' || $type === 'users') {
                $users = $this->searchUsers($query, $limit, $user);
                $results['users'] = $users;
                $results['users_count'] = $users->count();
            }

            // 🔍 Search Posts
            if ($type === 'all' || $type === 'posts') {
                $posts = $this->searchPosts($query, $limit, $user);
                $results['posts'] = $posts;
                $results['posts_count'] = $posts->count();
            }

            // 🔍 Search Tags
            if ($type === 'all' || $type === 'tags') {
                $tags = $this->searchTags($query, $limit);
                $results['tags'] = $tags;
                $results['tags_count'] = $tags->count();
            }

            // 📊 Search Statistics
            $results['total_results'] = 
                ($results['users_count'] ?? 0) + 
                ($results['posts_count'] ?? 0) + 
                ($results['tags_count'] ?? 0);

            return response()->json([
                'success' => true,
                'message' => 'Search results retrieved',
                'query' => $query,
                'type' => $type,
                'data' => $results
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid search data',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred during search',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * 🔍 Search Users
     */
    private function searchUsers(string $query, int $limit, $currentUser = null)
    {
        $users = User::where(function($q) use ($query) {
                $q->where('full_name', 'LIKE', "%{$query}%")
                  ->orWhere('email', 'LIKE', "%{$query}%")
                  ->orWhere('bio', 'LIKE', "%{$query}%");
            })
            ->select(['id', 'full_name', 'email', 'bio', 'image', 'created_at'])
            ->withCount(['followers', 'following', 'posts'])
            ->limit($limit)
            ->get();

        // Add follow information if user is authenticated
        if ($currentUser) {
            $users->each(function ($user) use ($currentUser) {
                $user->is_following = $currentUser->isFollowing($user);
                $user->is_followed_by = $user->isFollowing($currentUser);
            });
        }

        return $users;
    }

    /**
     * 🔍 Search Posts
     */
    private function searchPosts(string $query, int $limit, $currentUser = null)
    {
        $posts = Post::where(function($q) use ($query) {
                $q->where('title', 'LIKE', "%{$query}%")
                  ->orWhere('caption', 'LIKE', "%{$query}%");
            })
            ->with([
                'user:id,full_name,image',
                'tags:id,tag_name',
                'likes' => function ($q) {
                    $q->select('id', 'user_id', 'post_id');
                },
                'comments' => function ($q) {
                    $q->select('id', 'user_id', 'post_id', 'comment_text')
                      ->with('user:id,full_name,image')
                      ->latest()
                      ->limit(2);
                }
            ])
            ->withCount(['likes', 'comments'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        // Add like information if user is authenticated
        if ($currentUser) {
            $posts->each(function ($post) use ($currentUser) {
                $post->is_liked = $post->likes->contains('user_id', $currentUser->id);
            });
        }

        return $posts;
    }

    /**
     * 🔍 Search Tags
     */
    private function searchTags(string $query, int $limit)
    {
        return Tag::where('tag_name', 'LIKE', "%{$query}%")
            ->withCount('posts')
            ->orderBy('posts_count', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * 🔍 Search Posts by Tag
     */
    public function searchPostsByTag(Request $request, $tagId): JsonResponse
    {
        try {
            $request->validate([
                'query' => 'nullable|string|min:1|max:255',
                'limit' => 'nullable|integer|min:1|max:50',
            ]);

            $tag = Tag::findOrFail($tagId);
            $query = $request->input('query', '');
            $limit = $request->input('limit', 15);
            $user = $request->user();

            // Search posts within tag
            $posts = $tag->posts()
                ->where(function($q) use ($query) {
                    if (!empty($query)) {
                        $q->where('title', 'LIKE', "%{$query}%")
                          ->orWhere('caption', 'LIKE', "%{$query}%");
                    }
                })
                ->with([
                    'user:id,full_name,image',
                    'tags:id,tag_name',
                    'likes' => function ($q) {
                        $q->select('id', 'user_id', 'post_id');
                    }
                ])
                ->withCount(['likes', 'comments'])
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get();

            // Add like information if user is authenticated
            if ($user) {
                $posts->each(function ($post) use ($user) {
                    $post->is_liked = $post->likes->contains('user_id', $user->id);
                });
            }

            return response()->json([
                'success' => true,
                'message' => 'Tag posts search results',
                'tag' => [
                    'id' => $tag->id,
                    'name' => $tag->tag_name,
                    'posts_count' => $tag->posts()->count()
                ],
                'query' => $query,
                'data' => $posts,
                'total_results' => $posts->count()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred during tag search',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * 🔍 Advanced Search with Filters
     */
    public function advancedSearch(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'query' => 'required|string|min:2|max:255',
                'filters' => 'nullable|array',
                'filters.users' => 'nullable|boolean',
                'filters.posts' => 'nullable|boolean',
                'filters.tags' => 'nullable|boolean',
                'filters.date_from' => 'nullable|date',
                'filters.date_to' => 'nullable|date|after_or_equal:date_from',
                'sort_by' => 'nullable|in:relevance,newest,oldest,popular',
                'page' => 'nullable|integer|min:1',
                'per_page' => 'nullable|integer|min:1|max:100',
            ]);

            $query = $request->input('query');
            $filters = $request->input('filters', []);
            $sortBy = $request->input('sort_by', 'relevance');
            $perPage = $request->input('per_page', 20);
            $user = $request->user();

            $results = [];

            // 🔍 Filter search by type
            $searchUsers = !isset($filters['users']) || $filters['users'] !== false;
            $searchPosts = !isset($filters['posts']) || $filters['posts'] !== false;
            $searchTags = !isset($filters['tags']) || $filters['tags'] !== false;

            // 👥 Search Users
            if ($searchUsers) {
                $usersQuery = User::where(function($q) use ($query) {
                    $q->where('full_name', 'LIKE', "%{$query}%")
                      ->orWhere('email', 'LIKE', "%{$query}%")
                      ->orWhere('bio', 'LIKE', "%{$query}%");
                });

                // Date filtering if provided
                if (!empty($filters['date_from'])) {
                    $usersQuery->where('created_at', '>=', $filters['date_from']);
                }
                if (!empty($filters['date_to'])) {
                    $usersQuery->where('created_at', '<=', $filters['date_to']);
                }

                $users = $usersQuery->select(['id', 'full_name', 'email', 'bio', 'image', 'created_at'])
                    ->withCount(['followers', 'following', 'posts'])
                    ->paginate($perPage);

                // Add follow information
                if ($user) {
                    $users->getCollection()->each(function ($userItem) use ($user) {
                        $userItem->is_following = $user->isFollowing($userItem);
                        $userItem->is_followed_by = $userItem->isFollowing($user);
                    });
                }

                $results['users'] = $users;
            }

            // 📝 Search Posts
            if ($searchPosts) {
                $postsQuery = Post::where(function($q) use ($query) {
                    $q->where('title', 'LIKE', "%{$query}%")
                      ->orWhere('caption', 'LIKE', "%{$query}%");
                });

                // Date filtering
                if (!empty($filters['date_from'])) {
                    $postsQuery->where('created_at', '>=', $filters['date_from']);
                }
                if (!empty($filters['date_to'])) {
                    $postsQuery->where('created_at', '<=', $filters['date_to']);
                }

                // Sorting
                switch ($sortBy) {
                    case 'newest':
                        $postsQuery->orderBy('created_at', 'desc');
                        break;
                    case 'oldest':
                        $postsQuery->orderBy('created_at', 'asc');
                        break;
                    case 'popular':
                        $postsQuery->withCount('likes')->orderBy('likes_count', 'desc');
                        break;
                    default: // relevance
                        $postsQuery->orderBy('created_at', 'desc');
                }

                $posts = $postsQuery->with([
                        'user:id,full_name,image',
                        'tags:id,tag_name',
                        'likes' => function ($q) {
                            $q->select('id', 'user_id', 'post_id');
                        }
                    ])
                    ->withCount(['likes', 'comments'])
                    ->paginate($perPage);

                // Add like information
                if ($user) {
                    $posts->getCollection()->each(function ($post) use ($user) {
                        $post->is_liked = $post->likes->contains('user_id', $user->id);
                    });
                }

                $results['posts'] = $posts;
            }

            // #️⃣ Search Tags
            if ($searchTags) {
                $tags = Tag::where('tag_name', 'LIKE', "%{$query}%")
                    ->withCount('posts')
                    ->orderBy('posts_count', 'desc')
                    ->paginate($perPage);

                $results['tags'] = $tags;
            }

            return response()->json([
                'success' => true,
                'message' => 'Advanced search results',
                'query' => $query,
                'filters' => $filters,
                'sort_by' => $sortBy,
                'data' => $results
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid search data',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred during advanced search',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * 🔍 Quick Search (for suggestions)
     */
    public function quickSearch(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'query' => 'required|string|min:1|max:100',
                'limit' => 'nullable|integer|min:1|max:20',
            ]);

            $query = $request->input('query');
            $limit = $request->input('limit', 5);

            $results = [];

            // Users (3 only)
            $users = User::where('full_name', 'LIKE', "%{$query}%")
                ->select(['id', 'full_name', 'image'])
                ->limit(3)
                ->get();
            $results['users'] = $users;

            // Posts (3 only)
            $posts = Post::where('title', 'LIKE', "%{$query}%")
                ->select(['id', 'title', 'user_id'])
                ->with('user:id,full_name,image')
                ->limit(3)
                ->get();
            $results['posts'] = $posts;

            // Tags (3 only)
            $tags = Tag::where('tag_name', 'LIKE', "%{$query}%")
                ->select(['id', 'tag_name'])
                ->limit(3)
                ->get();
            $results['tags'] = $tags;

            return response()->json([
                'success' => true,
                'message' => 'Quick search results',
                'query' => $query,
                'data' => $results
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred during quick search',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * 🎯 Top Search Suggestions
     */
    public function searchSuggestions(): JsonResponse
    {
        try {
            // Get trending tags
            $trendingTags = Tag::withCount('posts')
                ->orderBy('posts_count', 'desc')
                ->limit(5)
                ->get()
                ->map(function ($tag) {
                    return [
                        'type' => 'tag',
                        'id' => $tag->id,
                        'name' => $tag->tag_name,
                        'posts_count' => $tag->posts_count
                    ];
                });

            // Get popular users
            $popularUsers = User::withCount('followers')
                ->orderBy('followers_count', 'desc')
                ->limit(5)
                ->get()
                ->map(function ($user) {
                    return [
                        'type' => 'user',
                        'id' => $user->id,
                        'name' => $user->full_name,
                        'image' => $user->image,
                        'followers_count' => $user->followers_count
                    ];
                });

            // Get recent popular posts
            $recentPosts = Post::withCount('likes')
                ->orderBy('created_at', 'desc')
                ->orderBy('likes_count', 'desc')
                ->limit(5)
                ->get()
                ->map(function ($post) {
                    return [
                        'type' => 'post',
                        'id' => $post->id,
                        'title' => $post->title,
                        'likes_count' => $post->likes_count
                    ];
                });

            $suggestions = [
                'trending_tags' => $trendingTags,
                'popular_users' => $popularUsers,
                'recent_posts' => $recentPosts,
            ];

            return response()->json([
                'success' => true,
                'message' => 'Search suggestions retrieved',
                'data' => $suggestions
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while getting search suggestions',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}