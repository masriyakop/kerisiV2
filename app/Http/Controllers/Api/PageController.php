<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePageRequest;
use App\Http\Requests\UpdatePageRequest;
use App\Http\Traits\ApiResponse;
use App\Models\Page;
use App\Services\SlugService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PageController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected SlugService $slugService,
    ) {}

    /**
     * List pages with pagination, search, filtering, and sorting.
     */
    public function index(Request $request): JsonResponse
    {
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 10);
        $q = $request->input('q');
        $status = $request->input('status');
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc');

        $query = Page::query();

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

        $rows = $query->with('featuredImage')
            ->orderBy($sortBy, $sortDir)
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        return $this->sendOk($rows, [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'totalPages' => (int) ceil($total / $limit),
        ]);
    }

    /**
     * Create a new page.
     */
    public function store(StorePageRequest $request): JsonResponse
    {
        $data = $request->validated();
        $slug = $this->slugService->uniqueSlug('pages', $data['title'], $data['slug'] ?? null);

        $page = Page::create([
            'title' => $data['title'],
            'slug' => $slug,
            'content' => $data['content'] ?? null,
            'status' => $data['status'] ?? 'draft',
            'featured_image_id' => $data['featured_image_id'] ?? null,
            'published_at' => ($data['status'] ?? 'draft') === 'published' ? now() : null,
        ]);

        $page->load('featuredImage');

        return $this->sendOk($page);
    }

    /**
     * Show a single page.
     */
    public function show(int $id): JsonResponse
    {
        $page = Page::with('featuredImage')->find($id);

        if (! $page) {
            return $this->sendError(404, 'NOT_FOUND', 'Page not found');
        }

        return $this->sendOk($page);
    }

    /**
     * Update an existing page.
     */
    public function update(UpdatePageRequest $request, int $id): JsonResponse
    {
        $page = Page::find($id);

        if (! $page) {
            return $this->sendError(404, 'NOT_FOUND', 'Page not found');
        }

        $data = $request->validated();
        $slug = $this->slugService->uniqueSlug('pages', $data['title'], $data['slug'] ?? null, $id);

        // Handle publishedAt based on status change
        $publishedAt = null;
        if (($data['status'] ?? $page->status) === 'published') {
            $publishedAt = $page->published_at ?? now();
        }

        $page->update([
            'title' => $data['title'],
            'slug' => $slug,
            'content' => $data['content'] ?? null,
            'status' => $data['status'] ?? $page->status,
            'featured_image_id' => $data['featured_image_id'] ?? null,
            'published_at' => $publishedAt,
        ]);

        $page->load('featuredImage');

        return $this->sendOk($page);
    }

    /**
     * Delete a page.
     */
    public function destroy(int $id): JsonResponse
    {
        Page::where('id', $id)->delete();

        return $this->sendOk(['success' => true]);
    }
}
