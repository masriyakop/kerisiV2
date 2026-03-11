<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Category extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'description',
    ];

    /**
     * Get the posts for the category.
     */
    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class);
    }

    /**
     * Transform posts_count to Prisma-compatible _count format.
     */
    public function toArray(): array
    {
        $array = parent::toArray();

        if (isset($array['posts_count'])) {
            $array['_count'] = ['posts' => $array['posts_count']];
            unset($array['posts_count']);
        }

        return $array;
    }
}
