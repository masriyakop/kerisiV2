<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Media;
use App\Models\Page;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MediaController extends Controller
{
    use ApiResponse;

    /**
     * List all media, ordered by most recent first.
     */
    public function index(): JsonResponse
    {
        $items = Media::orderBy('created_at', 'desc')->get();

        return $this->sendOk($items);
    }

    /**
     * Upload a new media file.
     */
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:png,jpg,jpeg,gif,webp,svg,ico|max:5120',
        ]);

        $file = $request->file('file');

        // Sanitize filename: lowercase, replace non-alphanumeric chars with dashes
        $originalName = $file->getClientOriginalName();
        $safeBase = preg_replace('/-+/', '-', preg_replace('/[^a-z0-9.\-_]/', '-', strtolower($originalName)));
        $ext = pathinfo($safeBase, PATHINFO_EXTENSION);
        $name = pathinfo($safeBase, PATHINFO_FILENAME);
        $filename = $name.'-'.time().'.'.$ext;

        $file->storeAs('uploads', $filename, 'public');

        $record = Media::create([
            'filename' => $filename,
            'original_name' => $originalName,
            'title' => pathinfo($originalName, PATHINFO_FILENAME),
            'caption' => null,
            'description' => null,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'width' => null,
            'height' => null,
            'alt_text' => null,
            'path' => storage_path('app/public/uploads/'.$filename),
            'url' => '/storage/uploads/'.$filename,
        ]);

        return $this->sendOk($record);
    }

    /**
     * Update media metadata only (title, alt_text, caption, description).
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $media = Media::find($id);

        if (! $media) {
            return $this->sendError(404, 'NOT_FOUND', 'Media not found');
        }

        $data = $request->validate([
            'title' => 'nullable|string|max:255',
            'alt_text' => 'nullable|string|max:255',
            'caption' => 'nullable|string',
            'description' => 'nullable|string',
        ]);

        $media->update([
            'title' => $this->asNullable($data['title'] ?? ''),
            'alt_text' => $this->asNullable($data['alt_text'] ?? ''),
            'caption' => $this->asNullable($data['caption'] ?? ''),
            'description' => $this->asNullable($data['description'] ?? ''),
        ]);

        return $this->sendOk($media);
    }

    /**
     * Delete a media record and its file, unless in use.
     */
    public function destroy(int $id): JsonResponse
    {
        $media = Media::find($id);

        if (! $media) {
            return $this->sendError(404, 'NOT_FOUND', 'Media not found');
        }

        // Check if used as featured image in any post
        $linkedPost = Post::where('featured_image_id', $id)->first();
        if ($linkedPost) {
            return $this->sendError(409, 'MEDIA_IN_USE', 'Media is in use by at least one post');
        }

        // Check if used as featured image in any page
        $linkedPage = Page::where('featured_image_id', $id)->first();
        if ($linkedPage) {
            return $this->sendError(409, 'MEDIA_IN_USE', 'Media is in use by at least one page');
        }

        $media->delete();

        // Delete the physical file from the public disk
        $storagePath = 'uploads/'.$media->filename;
        if (Storage::disk('public')->exists($storagePath)) {
            Storage::disk('public')->delete($storagePath);
        }

        return $this->sendOk(['success' => true]);
    }

    /**
     * Convert empty strings to null.
     */
    protected function asNullable(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }
}
