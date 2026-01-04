<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
        protected $fillable = [
        'user_id',
        'actor_id',
        'type',
        'post_id',
        'comment_id',
        'is_read'
    ];

    protected $casts = [
        'is_read' => 'boolean'
    ];


    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function post()
    {
        return $this->belongsTo(Post::class)->withTrashed();
    }

    public function comment()
    {
        return $this->belongsTo(Comment::class)->withTrashed();
    }

    // نطاق للإشعارات غير المقروءة
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    // تحديد الإشعار كمقروء
    public function markAsRead()
    {
        $this->update(['is_read' => true]);
    }
}
