<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

class PageSeeder extends Seeder
{
    /**
     * Seed a default published home page for the public Webfront.
     */
    public function run(): void
    {
        Page::query()->firstOrCreate(
            ['slug' => 'home'],
            [
                'title' => 'Welcome',
                'content' => <<<'MD'
# Welcome

This is your public **Webfront** home page. Edit it in the admin under **Pages**, or set another page as the front page in **Webfront settings**.

[Go to admin login](/admin/login)
MD,
                'status' => 'published',
                'published_at' => now(),
            ],
        );
    }
}
