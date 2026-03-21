<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SlugService
{
    /**
     * Generate a unique slug for the given table.
     *
     * @param  string  $table  The database table to check uniqueness against.
     * @param  string  $title  The title to derive the slug from.
     * @param  string|null  $requestedSlug  An explicit slug to use instead of deriving from title.
     * @param  int|null  $excludeId  An ID to exclude from the uniqueness check (for updates).
     */
    public function uniqueSlug(string $table, string $title, ?string $requestedSlug = null, ?int $excludeId = null): string
    {
        $base = Str::slug($requestedSlug ?: $title);

        if ($base === '') {
            $base = 'item';
        }

        // Limit slug length to 80 characters
        $base = Str::limit($base, 80, '');

        $slug = $base;
        $counter = 1;

        while (true) {
            $query = DB::table($table)->where('slug', $slug);

            if ($excludeId !== null) {
                $query->where('id', '!=', $excludeId);
            }

            if (! $query->exists()) {
                return $slug;
            }

            $counter++;
            $slug = "{$base}-{$counter}";
        }
    }
}
