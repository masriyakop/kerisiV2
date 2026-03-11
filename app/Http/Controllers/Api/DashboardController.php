<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Media;
use App\Models\Page;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    use ApiResponse;

    /**
     * Return summary counts and recent posts/pages.
     */
    public function summary(): JsonResponse
    {
        $counts = [
            'posts' => Post::count(),
            'pages' => Page::count(),
            'media' => Media::count(),
            'users' => User::count(),
        ];

        $recentPosts = Post::orderBy('updated_at', 'desc')->take(5)->get();
        $recentPages = Page::orderBy('updated_at', 'desc')->take(5)->get();

        return $this->sendOk([
            'counts' => $counts,
            'recent' => [
                'posts' => $recentPosts,
                'pages' => $recentPages,
            ],
        ]);
    }
}
