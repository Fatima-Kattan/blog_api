<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Follow extends Model
{
    protected $fillable = [
        'follower_id',
        'following_id',
        'status'
    ];

    public $timestamps = true;

    public function follower()
    {
        return $this->belongsTo(User::class, 'follower_id');
    }

    public function following()
    {
        return $this->belongsTo(User::class, 'following_id');
    }

    // نطاق للمتابعات المقبولة
    public function scopeAccepted($query)
    {
        return $query->where('status', 'accepted');
    }

    // نطاق للمتابعات المعلقة
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}
