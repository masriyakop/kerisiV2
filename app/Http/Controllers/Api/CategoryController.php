<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Traits\ApiResponse;
use App\Models\Category;
use App\Services\SlugService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected SlugService $slugService,
    ) {}

    /**
     * List categories with pagination, search, sorting, and post counts.
     */
    public function index(Request $request): JsonResponse
    {
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 10);
        $q = $request->input('q');
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc');

        $query = Category::query();

        if ($q) {
            $query->where(function ($builder) use ($q) {
                $builder->where('name', 'like', "%{$q}%")
                    ->orWhere('slug', 'like', "%{$q}%");
            });
        }

        $total = $query->count();

        $rows = $query->withCount('posts')
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
     * Create a new category.
     */
    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $data = $request->validated();
        $slug = $this->slugService->uniqueSlug('categories', $data['name'], $data['slug'] ?? null);

        $category = Category::create([
            'name' => $data['name'],
            'slug' => $slug,
            'description' => $data['description'] ?? null,
        ]);

        $category->loadCount('posts');

        return $this->sendOk($category);
    }

    /**
     * Show a single category with post count.
     */
    public function show(int $id): JsonResponse
    {
        $category = Category::withCount('posts')->find($id);

        if (! $category) {
            return $this->sendError(404, 'NOT_FOUND', 'Category not found');
        }

        return $this->sendOk($category);
    }

    /**
     * Update an existing category.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $category = Category::find($id);

        if (! $category) {
            return $this->sendError(404, 'NOT_FOUND', 'Category not found');
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255',
            'description' => 'nullable|string',
        ]);

        $slug = $this->slugService->uniqueSlug('categories', $data['name'], $data['slug'] ?? null, $id);

        $category->update([
            'name' => $data['name'],
            'slug' => $slug,
            'description' => $data['description'] ?? null,
        ]);

        $category->loadCount('posts');

        return $this->sendOk($category);
    }

    /**
     * Delete a category.
     */
    public function destroy(int $id): JsonResponse
    {
        $category = Category::find($id);

        if ($category) {
            $category->posts()->detach();
            $category->delete();
        }

        return $this->sendOk(['success' => true]);
    }
}
