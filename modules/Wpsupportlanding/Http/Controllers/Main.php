<?php

namespace Modules\Wpsupportlanding\Http\Controllers;

use Akaunting\Module\Facade as Module;
use Illuminate\Routing\Controller;
use Modules\Blog\Models\Blog;

class Main extends Controller
{
    public function blog()
    {
        $posts = Blog::query()
            ->where('status', 'published')
            ->orderByDesc('created_at')
            ->paginate(9);

        return view('wpsupportlanding::landing.blog', [
            'hasBlog' => Module::has('blog'),
            'posts' => $posts,
        ]);
    }

    public function blog_post(string $slug)
    {
        $post = Blog::query()
            ->where('slug', $slug)
            ->where('status', 'published')
            ->firstOrFail();

        return view('wpsupportlanding::landing.blog_post', [
            'hasBlog' => Module::has('blog'),
            'post' => $post,
        ]);
    }
}
