<?php

namespace App\Http\Controllers;

use App\Models\Follow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FollowController extends Controller
{
    // عرض كل المتابعات (اختياري)
    public function index()
    {
        return response()->json(Follow::all());
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

}