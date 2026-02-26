<?php

namespace Modules\Blog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Storage;

class Blog extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function getFeaturedImageAttribute($value)
    {
        if (!$value) {
            return null;
        }
        
        if (str_starts_with($value, 'http')) {
            return $value;
        }
        
        return config('app.url') . Storage::url($value);
    }


   
    public function clone()
    {
        $clone = $this->replicate();
        //Reset the slug, by adding the id to the title
        $clone->title = $this->title . ' - ' . __('Copy');
        $clone->slug = $this->slug . '_'.__('copy');
        $clone->save();
        return $clone;
    }

    protected static function booted()
    {
        static::saving(function ($blog) {
            // Calculate read time before saving
            $blog->read_time = static::calculateReadTime($blog->content);
        });

        static::updating(function ($blog) {
            // Calculate read time before updating
            $blog->read_time = static::calculateReadTime($blog->content); 
        });
    }

    /**
     * Calculate estimated reading time in minutes
     * Average adult reads 200-250 words per minute
     * We'll use 225 words per minute as average
     */
    protected static function calculateReadTime($content)
    {
        // Strip HTML tags and count words
        $wordCount = str_word_count(strip_tags($content));
        
        // Calculate minutes rounded up
        $minutes = ceil($wordCount / 200);
        
        // Ensure minimum 1 minute
        return max(1, $minutes);
    }
} 
