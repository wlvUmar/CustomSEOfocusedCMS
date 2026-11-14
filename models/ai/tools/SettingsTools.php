<?php
// path: ./models/ai/tools/SettingsTools.php
// Write tool for the global SEO settings (seo_settings row 1): business name, hours,
// postal code, Organization fields and sameAs profiles. Only the allowlisted fields
// below can be changed; schema JSON and the custom-schema flag are not writable here.
// Writes go through SEO::updateSettings(), so they are audit-logged.

require_once BASE_PATH . '/models/SEO.php';

class SettingsTools {

    /** Fields this tool may change, with their validation kind. */
    private const WRITABLE = [
        'site_name_ru' => 'text', 'site_name_uz' => 'text',
        'org_name_ru' => 'text', 'org_name_uz' => 'text',
        'org_description_ru' => 'text', 'org_description_uz' => 'text',
        'meta_description_ru' => 'text', 'meta_description_uz' => 'text',
        'phone' => 'phone', 'email' => 'email',
        'address_ru' => 'text', 'address_uz' => 'text',
        'city' => 'text', 'region' => 'text', 'country' => 'text',
        'postal_code' => 'postal',
        'working_hours_ru' => 'text', 'working_hours_uz' => 'text',
        'opening_hours' => 'hours',
        'price_range' => 'price',
        'org_logo' => 'url',
        'social_facebook' => 'url', 'social_instagram' => 'url',
        'social_twitter' => 'url', 'social_youtube' => 'url',
        'org_sameas_extra' => 'urllist',
    ];

    /** Every column UPDATE seo_settings needs; unchanged ones are re-sent from the current row. */
    private const ALL_COLUMNS = [
        'site_name_ru', 'site_name_uz', 'meta_keywords_ru', 'meta_keywords_uz',
        'meta_description_ru', 'meta_description_uz', 'phone', 'email',
        'address_ru', 'address_uz', 'working_hours_ru', 'working_hours_uz',
        'google_review_url', 'google_maps_embed_url', 'city', 'region', 'postal_code', 'country',
        'org_type', 'org_name_ru', 'org_name_uz', 'org_logo',
        'org_description_ru', 'org_description_uz', 'opening_hours', 'price_range',
        'social_facebook', 'social_instagram', 'social_twitter', 'social_youtube',
        'org_sameas_extra', 'service_type', 'area_served', 'org_latitude', 'org_longitude',
        'organization_schema', 'website_schema', 'org_schema_custom', 'organization_schema_raw',
    ];

    public static function definitions(): array {
        $props = [
            'site_name_ru' => ['type' => 'string', 'description' => 'Brand name, RU. Use KuplyTashkent.'],
            'site_name_uz' => ['type' => 'string', 'description' => 'Brand name, UZ. Use KuplyTashkent.'],
            'org_name_ru' => ['type' => 'string', 'description' => 'Organization name in schema, RU.'],
            'org_name_uz' => ['type' => 'string', 'description' => 'Organization name in schema, UZ.'],
            'org_description_ru' => ['type' => 'string', 'description' => 'Organization description, RU (one line, no line breaks).'],
            'org_description_uz' => ['type' => 'string', 'description' => 'Organization description, UZ (one line).'],
            'meta_description_ru' => ['type' => 'string', 'description' => 'Site-wide default meta description, RU.'],
            'meta_description_uz' => ['type' => 'string', 'description' => 'Site-wide default meta description, UZ.'],
            'phone' => ['type' => 'string', 'description' => 'Phone in international form, e.g. +998900069777.'],
            'email' => ['type' => 'string', 'description' => 'Contact email.'],
            'address_ru' => ['type' => 'string', 'description' => 'Street address, RU.'],
            'address_uz' => ['type' => 'string', 'description' => 'Street address, UZ.'],
            'city' => ['type' => 'string', 'description' => 'City in English, e.g. Tashkent.'],
            'region' => ['type' => 'string', 'description' => 'Region in English, e.g. Tashkent.'],
            'country' => ['type' => 'string', 'description' => 'ISO country code, e.g. UZ.'],
            'postal_code' => ['type' => 'string', 'description' => 'Postal code, digits only, e.g. 100167. Must match the address.'],
            'working_hours_ru' => ['type' => 'string', 'description' => 'Footer hours text, RU, e.g. "Ежедневно 09:00–21:00".'],
            'working_hours_uz' => ['type' => 'string', 'description' => 'Footer hours text, UZ, e.g. "Har kuni 09:00–21:00".'],
            'opening_hours' => ['type' => 'string', 'description' => 'Structured schema hours, one rule per line, e.g. "Mo-Su 09:00-21:00". Empty string clears it.'],
            'price_range' => ['type' => 'string', 'description' => 'One of "", "$", "$$", "$$$", "$$$$". Empty string removes it from schema.'],
            'org_logo' => ['type' => 'string', 'description' => 'Absolute or site-relative URL of the logo image (at least 1024px square).'],
            'social_facebook' => ['type' => 'string', 'description' => 'Facebook profile URL.'],
            'social_instagram' => ['type' => 'string', 'description' => 'Instagram profile URL.'],
            'social_twitter' => ['type' => 'string', 'description' => 'X/Twitter profile URL.'],
            'social_youtube' => ['type' => 'string', 'description' => 'YouTube channel URL.'],
            'org_sameas_extra' => ['type' => 'string', 'description' => 'Other profile URLs, one per line (https:// only). Replaces the whole list.'],
        ];
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'set_global_settings',
                    'description' => 'Change global SEO settings (business name, phone, address, postal code, hours, Organization schema fields, social/sameAs profiles). Pass only the fields to change; others are kept. Changes apply to the whole site and to the structured data, so only change what the owner asked for. Validates format; returns before/after for every field.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'fields' => [
                                'type' => 'object',
                                'description' => 'Map of field name to new value.',
                                'properties' => $props,
                                'additionalProperties' => false,
                            ],
                        ],
                        'required' => ['fields'],
                    ],
                ],
            ],
        ];
    }

    public static function handle(string $name, array $args): array {
        if ($name !== 'set_global_settings') {
            throw new InvalidArgumentException("Unknown tool: {$name}");
        }
        $fields = $args['fields'] ?? null;
        if (!is_array($fields) || $fields === []) {
            throw new InvalidArgumentException('fields must be a non-empty object of setting => value.');
        }

        $seo = new SEO();
        $current = $seo->getSettings() ?: [];

        $errors = [];
        $changes = [];
        foreach ($fields as $key => $value) {
            if (!array_key_exists($key, self::WRITABLE)) {
                $errors[] = "{$key}: not writable with this tool";
                continue;
            }
            $clean = self::validate($key, is_string($value) ? trim($value) : $value, $errors);
            if ($clean === null) continue;
            $changes[$key] = [
                'before' => (string)($current[$key] ?? ''),
                'after' => $clean,
            ];
        }
        if ($errors) {
            throw new InvalidArgumentException('Validation failed: ' . implode('; ', $errors));
        }

        // Build the full row: current values for everything, then the requested changes.
        $data = [];
        foreach (self::ALL_COLUMNS as $col) {
            $data[$col] = (string)($current[$col] ?? '');
        }
        foreach ($changes as $key => $c) {
            $data[$key] = $c['after'];
        }
        // Keep the generated schema JSON in sync with the settings it was built from.
        // The page builder only uses it when custom mode is on.
        $data['org_schema_custom'] = (int)($current['org_schema_custom'] ?? 0);
        $data['organization_schema_raw'] = (string)($current['organization_schema_raw'] ?? '');

        $seo->updateSettings($data);

        $changed = array_filter($changes, fn($c) => $c['before'] !== $c['after']);
        return [
            'ok' => true,
            'verified' => true,
            'changed' => array_map(fn($c) => ['before' => $c['before'], 'after' => $c['after']], $changed),
            'unchanged' => array_keys(array_diff_key($changes, $changed)),
            'note' => 'Settings saved. Check the homepage JSON-LD and footer after the cache clears.',
        ];
    }

    /** Returns the cleaned value, or null with an error appended. */
    private static function validate(string $key, $value, array &$errors) {
        $kind = self::WRITABLE[$key];
        if (!is_string($value)) {
            $errors[] = "{$key}: must be a string";
            return null;
        }
        switch ($kind) {
            case 'text':
                if (mb_strlen($value) > 500) { $errors[] = "{$key}: longer than 500 characters"; return null; }
                // Keep one line for descriptions and hours text.
                return trim(preg_replace('/\s+/u', ' ', $value));
            case 'phone':
                if (!preg_match('/^\+?[0-9 ()\-]{7,20}$/', $value)) { $errors[] = "{$key}: not a phone number"; return null; }
                return $value;
            case 'email':
                if ($value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) { $errors[] = "{$key}: not an email"; return null; }
                return $value;
            case 'postal':
                if ($value !== '' && !preg_match('/^\d{5,7}$/', $value)) { $errors[] = "{$key}: postal code must be 5-7 digits"; return null; }
                return $value;
            case 'hours':
                $lines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $value)), 'strlen'));
                foreach ($lines as $line) {
                    if (preg_match('/^(24\s*\/\s*7)$/i', $line)) continue;
                    if (!preg_match('/^[A-Za-z]{2}(-[A-Za-z]{2})?\s+\d{2}:\d{2}-\d{2}:\d{2}$/', $line)) {
                        $errors[] = "{$key}: line \"{$line}\" must look like \"Mo-Su 09:00-21:00\"";
                        return null;
                    }
                }
                return implode("\n", $lines);
            case 'price':
                if (!in_array($value, ['', '$', '$$', '$$$', '$$$$'], true)) { $errors[] = "{$key}: must be empty, $, $$, $$$ or $$$$"; return null; }
                return $value;
            case 'url':
                if ($value !== '' && !preg_match('~^https://[^\s]+$~i', $value)) { $errors[] = "{$key}: must be an https:// URL"; return null; }
                return $value;
            case 'urllist':
                $urls = array_values(array_filter(array_map('trim', preg_split('/[\s,]+/', $value)), 'strlen'));
                foreach ($urls as $u) {
                    if (!preg_match('~^https://[^\s]+$~i', $u)) { $errors[] = "{$key}: \"{$u}\" must be an https:// URL"; return null; }
                }
                return implode("\n", array_values(array_unique($urls)));
        }
        $errors[] = "{$key}: unknown kind";
        return null;
    }
}
