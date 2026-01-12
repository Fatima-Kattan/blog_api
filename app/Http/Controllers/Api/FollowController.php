<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Follow;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FollowController extends Controller
{
    public function index()
    {
        $userId = Auth::id();

        $followers = Follow::where('following_id', $userId)->with('follower')->get();
        $followings = Follow::where('follower_id', $userId)->with('following')->get();

        return response()->json([
            'followers_count' => $followers->count(),
            'followers' => $followers,
            'followings_count' => $followings->count(),
            'followings' => $followings,
        ]);
    }

    // عمل متابعة جديدة
    public function store(Request $request)
    {
        $request->validate([
            'following_id' => 'required|exists:users,id',
        ]);

        $exists = Follow::where('follower_id', Auth::id())
            ->where('following_id', $request->following_id)
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'Already following this user'], 400);
        }

        $follow = Follow::create([
            'follower_id' => Auth::id(),
            'following_id' => $request->following_id,
            'status' => 'accepted', // ثابتة على accepted
        ]);
        Notification::create([
            'user_id'    => $request->following_id, // المستلم (المتابَع)
            'actor_id'   => Auth::id(),             // الفاعل (المتابِع)
            'type'       => 'follow',
            'post_id'    => null,
            'comment_id' => null,
            'is_read'    => false,
        ]);

        return response()->json($follow, 201);
    }


    // إلغاء متابعة
    public function destroy($id)
    {
        $follow = Follow::where('follower_id', Auth::id())
            ->where('following_id', $id)
            ->first();

        if (!$follow) {
            return response()->json(['message' => 'Not following this user'], 404);
        }

        $follow->delete();

        return response()->json(['message' => 'Unfollowed successfully']);
    }


    // عرض قائمة المتابعين لمستخدم معين
    public function followers($userId)
    {
        $followers = Follow::where('following_id', $userId)->with('follower')->get();
        $count = $followers->count();

        return response()->json([
            'count' => $count,
            'data' => $followers
        ]);
    }



    // عرض قائمة اللي المستخدم متابعن
    public function followings($userId)
    {
        $followings = Follow::where('follower_id', $userId)->with('following')->get();
        $count = $followings->count();

        return response()->json([
            'count' => $count,
            'data' => $followings
        ]);
    }

    //عرض الاشخاص اللي لا اتابعهم
    public function notFollowings($userId)
    {
        // IDs الأشخاص اللي المستخدم متابعن
        $followingsIds = Follow::where('follower_id', $userId)
            ->pluck('following_id');

        // كل المستخدمين ما عدا اللي متابعن
        $notFollowings = User::whereNotIn('id', $followingsIds)
            ->where('id', '!=', $userId) // استثناء نفسه
            ->get();

        $count = $notFollowings->count();

        return response()->json([
            'count' => $count,
            'data' => $notFollowings
        ]);
    }
}
