<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class SettingService
{
    /**
     * Default setting keys and their default values.
     */
    protected array $defaults = [
        'siteTitle' => '',
        'tagline' => '',
        'webfrontTitle' => '',
        'webfrontTagline' => '',
        'titleFormat' => '%page% | %site%',
        'metaDescription' => '',
        'siteIconUrl' => '',
        'webfrontLogoUrl' => '',
        'sidebarLogoUrl' => '',
        'faviconUrl' => '',
        'language' => 'en',
        'timezone' => 'UTC',
        'footerText' => '',
        'frontPageId' => null,
        'storefrontMenu' => null,
        'adminMenuPrefs' => null,
    ];

    /**
     * Legacy aliases that may exist in old databases.
     *
     * @var array<string, array<int, string>>
     */
    protected array $aliases = [
        'siteTitle' => ['siteTitle', 'site_title'],
        'tagline' => ['tagline'],
        'webfrontTitle' => ['webfrontTitle', 'webfront_title'],
        'webfrontTagline' => ['webfrontTagline', 'webfront_tagline'],
        'titleFormat' => ['titleFormat', 'title_format'],
        'metaDescription' => ['metaDescription', 'meta_description'],
        'siteIconUrl' => ['siteIconUrl', 'site_icon_url'],
        'webfrontLogoUrl' => ['webfrontLogoUrl', 'webfront_logo_url'],
        'sidebarLogoUrl' => ['sidebarLogoUrl', 'sidebar_logo_url'],
        'faviconUrl' => ['faviconUrl', 'favicon_url'],
        'language' => ['language'],
        'timezone' => ['timezone'],
        'footerText' => ['footerText', 'footer_text'],
        'frontPageId' => ['frontPageId', 'frontpageId', 'frontpage_id'],
        'storefrontMenu' => ['storefrontMenu', 'storefront_menu'],
        'adminMenuPrefs' => ['adminMenuPrefs', 'admin_menu_prefs'],
    ];

    /**
     * Retrieve all settings as a key-value array, applying defaults for missing keys.
     *
     * @return array<string, mixed>
     */
    public function getAll(): array
    {
        $rows = DB::table('settings')->pluck('value', 'key')->toArray();

        $result = [];
        foreach ($this->defaults as $key => $default) {
            $result[$key] = $this->resolveValueByAlias($key, $rows, $default);
        }

        return $result;
    }

    /**
     * Update multiple settings at once, upserting each key within a transaction.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(array $data): void
    {
        DB::transaction(function () use ($data) {
            foreach ($data as $key => $value) {
                $stringValue = $this->serializeValue($value);

                DB::table('settings')->updateOrInsert(
                    ['key' => $key],
                    ['value' => $stringValue]
                );

                // Remove legacy alias keys to prevent shadowing
                $aliasList = $this->aliases[$key] ?? [];
                foreach ($aliasList as $alias) {
                    if ($alias !== $key) {
                        DB::table('settings')->where('key', $alias)->delete();
                    }
                }
            }
        });
    }

    /**
     * Retrieve a single setting value.
     *
     * @param  mixed|null  $default
     */
    public function get(string $key, $default = null): ?string
    {
        $keys = $this->aliases[$key] ?? [$key];
        foreach ($keys as $candidate) {
            $row = DB::table('settings')->where('key', $candidate)->first();
            if ($row) {
                return $row->value;
            }
        }

        return $default;
    }

    /**
     * Set a single setting value.
     */
    public function set(string $key, string $value): void
    {
        DB::table('settings')->updateOrInsert(
            ['key' => $key],
            ['value' => $value]
        );
    }

    /**
     * Serialize a value for storage in the settings table.
     *
     * @param  mixed  $value
     */
    protected function serializeValue($value): string
    {
        if (is_null($value)) {
            return 'null';
        }

        if (is_array($value)) {
            return json_encode($value);
        }

        return (string) $value;
    }

    /**
     * Resolve a canonical setting key from DB rows and legacy aliases.
     *
     * @param  array<string, mixed>  $rows
     * @param  mixed  $default
     * @return mixed
     */
    protected function resolveValueByAlias(string $key, array $rows, $default)
    {
        $candidates = $this->aliases[$key] ?? [$key];

        foreach ($candidates as $candidate) {
            if (! array_key_exists($candidate, $rows)) {
                continue;
            }

            $value = $rows[$candidate];

            if ($key === 'frontPageId') {
                if ($value === null || $value === '' || $value === 'null') {
                    return null;
                }

                return (int) $value;
            }

            if ($value === 'null') {
                return null;
            }

            return $value;
        }

        return $default;
    }
}
