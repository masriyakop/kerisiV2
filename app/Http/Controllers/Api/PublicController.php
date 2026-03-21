<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Page;
use App\Services\SettingService;
use Illuminate\Http\JsonResponse;

class PublicController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected SettingService $settingService,
    ) {}

    /**
     * Return public site settings and storefront menu.
     */
    public function site(): JsonResponse
    {
        $keys = [
            'siteTitle',
            'tagline',
            'webfrontTitle',
            'webfrontTagline',
            'metaDescription',
            'footerText',
            'siteIconUrl',
            'webfrontLogoUrl',
            'sidebarLogoUrl',
            'faviconUrl',
            'storefrontMenu',
        ];

        $settings = [];
        foreach ($keys as $key) {
            $settings[$key] = $this->settingService->get($key, '');
        }

        // Parse storefront menu
        $storefrontMenu = [];
        if (! empty($settings['storefrontMenu'])) {
            try {
                $parsed = json_decode($settings['storefrontMenu'], true);
                $storefrontMenu = $this->normalizeStorefrontMenuItems($parsed ?? []);
            } catch (\Throwable) {
                $storefrontMenu = [];
            }
        }

        return $this->sendOk([
            'siteTitle' => $settings['siteTitle'] ?? '',
            'tagline' => $settings['tagline'] ?? '',
            'webfrontTitle' => $settings['webfrontTitle'] ?: ($settings['siteTitle'] ?? ''),
            'webfrontTagline' => $settings['webfrontTagline'] ?: ($settings['tagline'] ?? ''),
            'metaDescription' => $settings['metaDescription'] ?? '',
            'footerText' => $settings['footerText'] ?? '',
            'siteIconUrl' => $settings['siteIconUrl'] ?? '',
            'webfrontLogoUrl' => $settings['webfrontLogoUrl'] ?: ($settings['siteIconUrl'] ?? ''),
            'sidebarLogoUrl' => $settings['sidebarLogoUrl'] ?? '',
            'faviconUrl' => $settings['faviconUrl'] ?? '',
            'storefrontMenu' => $storefrontMenu,
        ]);
    }

    /**
     * Find a published page by slug.
     */
    public function pageBySlug(string $slug): JsonResponse
    {
        $slug = trim($slug);

        if (! $slug) {
            return $this->sendError(404, 'NOT_FOUND', 'Page not found');
        }

        $page = Page::where('slug', $slug)
            ->where('status', 'published')
            ->with('featuredImage')
            ->first();

        if (! $page) {
            return $this->sendError(404, 'NOT_FOUND', 'Page not found');
        }

        return $this->sendOk($page);
    }

    /**
     * Resolve the frontpage: check frontpageId setting, then "home" slug, then latest published.
     */
    public function frontpage(): JsonResponse
    {
        $frontPageIdValue = $this->settingService->get('frontPageId');
        $frontPageId = $this->parseFrontPageId($frontPageIdValue);

        // 1. Try the configured front page ID
        if ($frontPageId !== null) {
            $selected = Page::where('id', $frontPageId)
                ->where('status', 'published')
                ->with('featuredImage')
                ->first();

            if ($selected) {
                return $this->sendOk($selected, ['source' => 'frontPageId']);
            }
        }

        // 2. Try the "home" slug
        $homeSlug = Page::where('slug', 'home')
            ->where('status', 'published')
            ->with('featuredImage')
            ->first();

        if ($homeSlug) {
            return $this->sendOk($homeSlug, ['source' => 'home-slug']);
        }

        // 3. Fall back to the latest published page
        $latest = Page::where('status', 'published')
            ->with('featuredImage')
            ->orderBy('updated_at', 'desc')
            ->first();

        if ($latest) {
            return $this->sendOk($latest, ['source' => 'latest']);
        }

        return $this->sendError(404, 'NOT_FOUND', 'No published page yet');
    }

    /**
     * Parse frontPageId setting value to integer or null.
     */
    protected function parseFrontPageId(?string $value): ?int
    {
        if (! $value || $value === 'null') {
            return null;
        }

        $parsed = (int) $value;

        return $parsed > 0 ? $parsed : null;
    }

    /**
     * Normalize storefront menu items with ID assignment logic.
     */
    protected function normalizeStorefrontMenuItems(array $input): array
    {
        $withIds = [];
        foreach ($input as $index => $item) {
            $withIds[] = [
                'id' => ! empty(trim($item['id'] ?? '')) ? trim($item['id']) : 'menu_'.($index + 1),
                'label' => $item['label'] ?? '',
                'href' => $item['href'] ?? '',
                'parentId' => $item['parentId'] ?? null,
                'openInNewTab' => $item['openInNewTab'] ?? false,
            ];
        }

        $idSet = array_map(fn ($item) => $item['id'], $withIds);

        return array_map(function ($item) use ($idSet) {
            $parentId = $item['parentId'];
            if ($parentId && in_array($parentId, $idSet) && $parentId !== $item['id']) {
                $item['parentId'] = $parentId;
            } else {
                $item['parentId'] = null;
            }

            return $item;
        }, $withIds);
    }
}
