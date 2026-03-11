<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePostRequest;
use App\Http\Requests\UpdatePostRequest;
use App\Http\Traits\ApiResponse;
use App\Models\Post;
use App\Services\SlugService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PostController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected SlugService $slugService,
    ) {}

    /**
     * List posts with pagination, search, filtering, and sorting.
     */
    public function index(Request $request): JsonResponse
    {
        $page    = (int) $request->input('page', 1);
        $limit   = (int) $request->input('limit', 10);
        $q       = $request->input('q');
        $status  = $request->input('status');
        $sortBy  = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc');

        $query = Post::query();

        if ($status) {
            $query->where('status', $status);
        }

        if ($q) {
            $query->where(function ($builder) use ($q) {
                $builder->where('title', 'like', "%{$q}%")
                    ->orWhere('content', 'like', "%{$q}%")
                    ->orWhere('slug', 'like', "%{$q}%");
            });
        }

        $total = $query->count();

        $rows = $query->with(['featuredImage', 'categories'])
            ->orderBy($sortBy, $sortDir)
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        return $this->sendOk($rows, [
            'page'       => $page,
            'limit'      => $limit,
            'total'      => $total,
            'totalPages' => (int) ceil($total / $limit),
        ]);
    }

    /**
     * Create a new post.
     */
    public function store(StorePostRequest $request): JsonResponse
    {
        $data = $request->validated();
        $slug = $this->slugService->uniqueSlug('posts', $data['title'], $data['slug'] ?? null);

        $post = Post::create([
            'title'             => $data['title'],
            'slug'              => $slug,
            'excerpt'           => $data['excerpt'] ?? null,
            'content'           => $data['content'] ?? null,
            'status'            => $data['status'] ?? 'draft',
            'featured_image_id' => $data['featured_image_id'] ?? null,
            'published_at'      => ($data['status'] ?? 'draft') === 'published' ? now() : null,
        ]);

        if (!empty($data['category_ids'])) {
            $post->categories()->sync($data['category_ids']);
        }

        $post->load(['featuredImage', 'categories']);

        return $this->sendOk($post);
    }

    /**
     * Show a single post.
     */
    public function show(int $id): JsonResponse
    {
        $post = Post::with(['featuredImage', 'categories'])->find($id);

        if (!$post) {
            return $this->sendError(404, 'NOT_FOUND', 'Post not found');
        }

        return $this->sendOk($post);
    }

    /**
     * Update an existing post.
     */
    public function update(UpdatePostRequest $request, int $id): JsonResponse
    {
        $post = Post::find($id);

        if (!$post) {
            return $this->sendError(404, 'NOT_FOUND', 'Post not found');
        }

        $data = $request->validated();
        $slug = $this->slugService->uniqueSlug('posts', $data['title'], $data['slug'] ?? null, $id);

        // Handle publishedAt based on status change
        $publishedAt = null;
        if (($data['status'] ?? $post->status) === 'published') {
            $publishedAt = $post->published_at ?? now();
        }

        $post->update([
            'title'             => $data['title'],
            'slug'              => $slug,
            'excerpt'           => $data['excerpt'] ?? null,
            'content'           => $data['content'] ?? null,
            'status'            => $data['status'] ?? $post->status,
            'featured_image_id' => $data['featured_image_id'] ?? null,
            'published_at'      => $publishedAt,
        ]);

        if (array_key_exists('category_ids', $data)) {
            $post->categories()->sync($data['category_ids'] ?? []);
        }

        $post->load(['featuredImage', 'categories']);

        return $this->sendOk($post);
    }

    /**
     * Delete a post.
     */
    public function destroy(int $id): JsonResponse
    {
        Post::where('id', $id)->delete();

        return $this->sendOk(['success' => true]);
    }
}
