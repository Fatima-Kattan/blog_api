<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $notifications = Notification::where('user_id', Auth::id()) //auth()->id()
            ->latest()
            ->get();

        return response()->json([
            'data' => $notifications
        ], 200);
    }
    
    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $notification = Notification::where('user_id', Auth::id())
            ->where('id', $id)
            ->first();

        return response()->json([
            'data' => $notification
        ], 200);
    }

    // ✅ تعليم إشعار كمقروء
    public function markAsRead($id)
    {
        $notification = Notification::where('user_id', Auth::id())
            ->where('id', $id)
            ->first();

        $notification->update(['is_read' => true]);

        return response()->json([
            'message' => 'Notification marked as read',
            'data' => $notification
        ], 200);
    }
    // تعليم الاشعار كغير مقروء

    public function markAsUnread($id)
    {
        $notification = Notification::where('user_id', Auth::id())
            ->where('id', $id)
            ->first();

        $notification->update(['is_read' => false]);

        return response()->json([
            'message' => 'Notification marked as unread',
            'data'    => $notification
        ], 200);
    }

    //جعل جميع الاشعارات مقروءة دفعة واحدة 
    public function markAllAsRead()
    {
        Notification::where('user_id', Auth::id())
            ->update(['is_read' => true]);

        return response()->json([
            'message' => 'All notifications marked as read'
        ], 200);
    }


    //عدد الاشعارات الغير مقروءة    
    public function unreadCount()
    {    
        $unreadCount = Notification::where('user_id',Auth::id())
            ->where('is_read', false)
            ->count(); 

            return response()->json([
            'unread_count' => $unreadCount
        ], 200);
        
    }

    /**
     * Remove the specified resource from storage.
     */
    // ✅ حذف إشعار
    public function destroy($id)
    {
        $notification = Notification::where('user_id', Auth::id())
            ->where('id', $id)
            ->first();

        $notification->delete();

        return response()->json([
            'message' => 'Notification deleted successfully'
        ], 200);
    }
}
