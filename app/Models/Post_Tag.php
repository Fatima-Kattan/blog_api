<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Post_Tag extends Model
{
        protected $fillable = [
        'post_id',
        'tag_id',
        'quantity'
    ];

    public function activity()
    {
        return $this->belongsTo(Post::class);
    }

    public function item()
    {
        return $this->belongsTo(Tag::class);
    }
}
