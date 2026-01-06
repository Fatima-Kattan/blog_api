<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'caption',
        'images'
    ];

    protected $casts = [
        'images' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    protected $attributes = [
        'images' => '[]' // مصفوفة فارغة JSON
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * الإعجابات (اللايكات) على المنشور
     */
    public function likes()
    {
        return $this->hasMany(Like::class);
    }
    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'post_tag')->withTimestamps();
    }
    public function comments()
    {
        return $this->hasMany(Comment::class);
    }
    public function follows()
    {
        return $this->hasMany(Follow::class);
    }
        // عدد الإعجابات
    public function likesCount()
    {
        return $this->likes()->count();
    }

    // عدد التعليقات
    public function commentsCount()
    {
        return $this->comments()->count();
    }
}


    