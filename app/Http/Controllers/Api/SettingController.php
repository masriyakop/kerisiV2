<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Services\SettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SettingController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected SettingService $settingService,
    ) {}

    /**
     * Return all settings.
     */
    public function index(): JsonResponse
    {
        $settings = $this->settingService->getAll();

        return $this->sendOk($settings);
    }

    /**
     * Update settings.
     */
    public function update(Request $request): JsonResponse
    {
        // The CamelCaseMiddleware converts incoming keys to snake_case,
        // but settings keys must be stored as camelCase. Convert them back.
        $raw = $request->all();
        $data = [];
        foreach ($raw as $key => $value) {
            $data[Str::camel($key)] = $value;
        }

        $this->settingService->update($data);

        return $this->sendOk($data);
    }

    /**
     * Get admin menu preferences.
     */
    public function adminMenuPrefs(): JsonResponse
    {
        $value = $this->settingService->get('adminMenuPrefs');

        if ($value) {
            return $this->sendOk(json_decode($value, true));
        }

        return $this->sendOk(null);
    }

    /**
     * Update admin menu preferences.
     */
    public function updateAdminMenuPrefs(Request $request): JsonResponse
    {
        $prefs = $request->all();

        $this->settingService->set('adminMenuPrefs', json_encode($prefs));

        return $this->sendOk($prefs);
    }

    /**
     * Get storefront menu.
     */
    public function storefrontMenu(): JsonResponse
    {
        $value = $this->settingService->get('storefrontMenu');

        if (!$value) {
            return $this->sendOk([]);
        }

        try {
            $items = json_decode($value, true);
            $normalized = $this->normalizeStorefrontMenuItems($items ?? []);

            return $this->sendOk($normalized);
        } catch (\Throwable) {
            return $this->sendOk([]);
        }
    }

    /**
     * Update storefront menu.
     */
    public function updateStorefrontMenu(Request $request): JsonResponse
    {
        $items = $this->normalizeStorefrontMenuItems($request->all());

        $this->settingService->set('storefrontMenu', json_encode($items));

        return $this->sendOk($items);
    }

    /**
     * Normalize storefront menu items with ID assignment logic.
     */
    protected function normalizeStorefrontMenuItems(array $input): array
    {
        // Assign IDs to items that don't have one
        $withIds = [];
        foreach ($input as $index => $item) {
            $withIds[] = [
                'id'           => !empty(trim($item['id'] ?? '')) ? trim($item['id']) : 'menu_' . ($index + 1),
                'label'        => $item['label'] ?? '',
                'href'         => $item['href'] ?? '',
                'parentId'     => $item['parentId'] ?? null,
                'openInNewTab' => $item['openInNewTab'] ?? false,
            ];
        }

        // Build set of valid IDs
        $idSet = array_map(fn ($item) => $item['id'], $withIds);

        // Validate parentId references
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
