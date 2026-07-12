<?php

use App\Services\Frontend\FrontendContentStore;

/**
 * Frontend content helper — the single read point for the public
 * marketing site's editable copy, colors, and media.
 *
 *   {{ fc('home.hero.headline', 'The complete WhatsApp platform') }}
 *   style="background: {{ fc('theme.wa.deep', '#075E54') }}"
 *
 * The 2nd argument is the hardcoded default (today's shipped value), so
 * an un-edited install renders identically. Returns the published value
 * for visitors and the draft value for an admin in the live editor.
 *
 * Registered via AppServiceProvider::register() (require_once), so it is
 * available everywhere without a composer "files" autoload entry.
 */
if (! function_exists('fc')) {
    function fc(string $key, mixed $default = null): mixed
    {
        return app(FrontendContentStore::class)->get($key, $default);
    }
}

if (! function_exists('fc_editing')) {
    /** True when the current request is the admin live-editor preview. */
    function fc_editing(): bool
    {
        return app(FrontendContentStore::class)->editing();
    }
}

if (! function_exists('site_info')) {
    /**
     * Shared "site identity" values managed at /admin/site-settings and
     * stored under SystemSetting `site.*` keys (emails, phone, address,
     * social links). Read across the footer, contact, and about pages so
     * one edit updates them everywhere.
     *
     *   {{ site_info('email_support', 'team@wadesk.io') }}
     */
    function site_info(string $key, mixed $default = null): mixed
    {
        try {
            $val = \App\Models\SystemSetting::get('site.' . $key, null);
        } catch (\Throwable $e) {
            return $default;
        }
        return ($val === null || $val === '') ? $default : $val;
    }
}

if (! function_exists('theme_palette')) {
    /**
     * The dashboard's editable colour tokens (Tailwind v4 @theme vars). Each:
     *   css-var-suffix => [Label, default hex, group].
     * Admin overrides live in SystemSetting `theme.color.{suffix}` and are
     * injected by theme_css() into BOTH the user + admin layouts, so changing
     * one recolours the whole app — no rebuild.
     */
    function theme_palette(): array
    {
        return [
            'wa-deep'      => ['Primary',           '#075E54', 'Brand'],
            'wa-teal'      => ['Primary (hover)',   '#128C7E', 'Brand'],
            'wa-green'     => ['Accent / success',  '#25D366', 'Brand'],
            'wa-mint'      => ['Soft accent fill',  '#DCF8C6', 'Brand'],
            'wa-bubble'    => ['Chat bubble',       '#E7FFDB', 'Brand'],
            'paper-0'      => ['Page background',   '#FBFAF6', 'Surfaces'],
            'paper-50'     => ['Card / muted bg',  '#F5F3EC', 'Surfaces'],
            'paper-100'    => ['Hover background',  '#EFEBE0', 'Surfaces'],
            'paper-200'    => ['Borders',          '#E5DFD0', 'Surfaces'],
            'ink-500'      => ['Muted text',       '#6B807C', 'Text'],
            'ink-700'      => ['Body text',        '#1F4540', 'Text'],
            'ink-900'      => ['Headings',         '#0B1F1C', 'Text'],
            'accent-coral' => ['Accent · coral',   '#E87A5D', 'Accents'],
            'accent-amber' => ['Accent · amber',   '#E5A04E', 'Accents'],
            'accent-plum'  => ['Accent · plum',    '#5B3D8A', 'Accents'],
            'accent-sky'   => ['Accent · sky',     '#3E7AA1', 'Accents'],
        ];
    }
}

if (! function_exists('theme_color')) {
    /** Effective hex for a token — admin override (theme.color.*) or shipped default. */
    function theme_color(string $suffix): string
    {
        $default = theme_palette()[$suffix][1] ?? '#000000';
        try {
            $val = \App\Models\SystemSetting::get('theme.color.' . $suffix, null);
        } catch (\Throwable $e) {
            return $default;
        }
        return is_string($val) && $val !== '' ? $val : $default;
    }
}

if (! function_exists('theme_css')) {
    /**
     * <style> overriding the @theme colour vars at :root — injected LAST in the
     * <head> of the user + admin layouts so an admin's colour choices win over
     * the compiled defaults and recolour every Tailwind utility live.
     * Emits only genuine, hex-valid overrides (un-edited install renders identical).
     */
    function theme_css(): string
    {
        try {
            $rows = [];
            foreach (theme_palette() as $suffix => $meta) {
                $default = $meta[1] ?? '';
                $val = \App\Models\SystemSetting::get('theme.color.' . $suffix, null);
                if (is_string($val) && $val !== ''
                    && strtolower($val) !== strtolower($default)
                    && preg_match('/^#[0-9A-Fa-f]{3,8}$/', $val)) {
                    $rows[] = '--color-' . $suffix . ':' . $val;
                }
            }
            return $rows ? '<style id="wa-theme-overrides">:root{' . implode(';', $rows) . '}</style>' : '';
        } catch (\Throwable $e) {
            return '';
        }
    }
}

if (! function_exists('auth_cfg')) {
    /**
     * Admin-editable content for the auth pages (login / register / forgot),
     * managed at /admin/settings/auth-pages and stored under SystemSetting
     * `auth.{page}.{key}` keys (eyebrow, heading, heading_accent, subheading,
     * accent color, media_url, media_type).
     *
     *   {{ auth_cfg('login', 'heading', 'One place for every') }}
     */
    function auth_cfg(string $page, string $key, mixed $default = null): mixed
    {
        try {
            $val = \App\Models\SystemSetting::get('auth.' . $page . '.' . $key, null);
        } catch (\Throwable $e) {
            return $default;
        }
        return ($val === null || $val === '') ? $default : $val;
    }
}

if (! function_exists('brand_name')) {
    /**
     * The admin-configured platform name (/admin/settings/general).
     * Use anywhere a user-visible string needs the product name so the
     * whole app re-brands from one setting:  {{ brand_name() }}
     */
    function brand_name(): string
    {
        return \App\Support\Brand::name();
    }
}

if (! function_exists('brand_email')) {
    /**
     * A role-based contact address that re-brands with the platform instead of
     * a hard-coded "@wadesk.io". Resolution, first non-empty wins:
     *   site.email_{prefix}  →  site.email_support  →  {prefix}@{site domain}
     * The domain falls back to the configured site domain, else the request
     * host (minus www/app), else the app URL host — never a literal wadesk.io.
     *
     *   {{ brand_email('privacy') }}   {{ brand_email('billing') }}
     */
    function brand_email(string $prefix = 'support'): string
    {
        $prefix = strtolower(trim($prefix)) ?: 'support';

        $specific = site_info('email_' . $prefix);
        if (is_string($specific) && $specific !== '') return $specific;

        $support = site_info('email_support');
        if (is_string($support) && $support !== '') return $support;

        // Derive {prefix}@{domain} from the site's own host — never wadesk.io.
        $domain = (string) (site_info('domain') ?: '');
        if ($domain === '') {
            try { $domain = (string) (request()->getHost() ?: ''); } catch (\Throwable $e) { $domain = ''; }
        }
        if ($domain === '') {
            $domain = (string) (parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'example.com');
        }
        $domain = preg_replace('/^(www|app)\./i', '', $domain);
        return $prefix . '@' . $domain;
    }
}

if (! function_exists('wa_tz')) {
    /**
     * The display timezone for the current viewer. Timestamps are stored in
     * the app timezone (UTC by default), but every user-facing time — inbox,
     * analytics, reports — must render in the workspace's local zone or India
     * users see UTC (5h30 behind). Resolution order, first non-empty wins:
     *   explicit workspace tz  →  current workspace  →  signed-in user
     *   →  admin `default_timezone` setting  →  config('app.timezone').
     * A bad/unknown zone string is skipped so setTimezone() never throws.
     */
    function wa_tz($ws = null): string
    {
        $candidates = [];
        if (is_string($ws) && trim($ws) !== '') {
            $candidates[] = $ws;                       // explicit tz string passed in
        } elseif (is_object($ws)) {
            $candidates[] = $ws->timezone ?? null;     // a Workspace / Campaign row
        }
        try {
            $u = auth()->user();
            $candidates[] = optional($u?->currentWorkspace)->timezone;
            $candidates[] = $u?->timezone ?? null;
        } catch (\Throwable $e) { /* no auth context (queue/CLI) */ }
        try {
            $candidates[] = \App\Models\SystemSetting::get('default_timezone', null);
        } catch (\Throwable $e) { /* settings table unavailable */ }
        $candidates[] = config('app.timezone');

        foreach ($candidates as $tz) {
            if (is_string($tz) && trim($tz) !== '') {
                try { new \DateTimeZone(trim($tz)); return trim($tz); } catch (\Throwable $e) { /* next */ }
            }
        }
        return 'UTC';
    }
}

if (! function_exists('wa_local')) {
    /**
     * Convert a stored timestamp (Carbon, or a string treated as app-tz) to the
     * viewer's display timezone (see wa_tz()). Returns a Carbon you can ->format()
     * for display, or null when $when is empty. Pass an explicit tz / workspace /
     * campaign as the 2nd arg to pin the zone (e.g. a campaign's own timezone).
     *
     *   {{ wa_local($row->sent_at)?->format('M d, H:i') ?? '—' }}
     */
    function wa_local($when, $tzOrWs = null): ?\Illuminate\Support\Carbon
    {
        if (empty($when)) return null;
        $appTz     = (string) (config('app.timezone') ?: 'UTC');
        $displayTz = wa_tz($tzOrWs);
        $c = $when instanceof \Carbon\CarbonInterface
            ? \Illuminate\Support\Carbon::instance($when)
            : \Illuminate\Support\Carbon::parse((string) $when, $appTz);
        try {
            return $c->copy()->setTimezone($displayTz);
        } catch (\Throwable $e) {
            return $c;
        }
    }
}

if (! function_exists('fc_page')) {
    /**
     * Which editable marketing page is being rendered right now, derived
     * from the route name. Returns 'home' | 'features' | 'pricing', or null
     * when the current request isn't one of those (so callers fall back to
     * a global, page-agnostic key).
     */
    function fc_page(): ?string
    {
        $name = optional(request()->route())->getName();
        return match ($name) {
            'frontend.home'     => 'home',
            'frontend.features' => 'features',
            'frontend.pricing'  => 'pricing',
            'frontend.about'    => 'about',
            'frontend.contact'  => 'contact',
            default             => null,
        };
    }
}

if (! function_exists('fc_skey')) {
    /**
     * Page-scope a content key. On a known page, "faq.headline" becomes
     * "home.faq.headline" so the same shared component edited on a
     * different page keeps an independent value. Off-page → unscoped.
     */
    function fc_skey(string $key): string
    {
        $p = fc_page();
        return $p ? "{$p}.{$key}" : $key;
    }
}

if (! function_exists('fcp')) {
    /** Page-scoped read: fc() against the page-scoped key. */
    function fcp(string $key, mixed $default = null): mixed
    {
        return app(FrontendContentStore::class)->get(fc_skey($key), $default);
    }
}

if (! function_exists('fc_section_order')) {
    /**
     * The order to render a page's sections in. Reads the admin's saved
     * order (`<page>.__order`) and intersects it with the sections that
     * actually exist ($available, in their default order). Any section not
     * in the saved order is appended in its default position, so adding a
     * new section later can never make it vanish. No saved order → default.
     *
     * @param  string    $page
     * @param  string[]  $available  default-ordered slugs present on the page
     * @return string[]
     */
    function fc_section_order(string $page, array $available): array
    {
        $saved = app(FrontendContentStore::class)->get("{$page}.__order", []);
        if (! is_array($saved) || empty($saved)) {
            return $available;
        }
        $ordered = [];
        foreach ($saved as $slug) {
            if (in_array($slug, $available, true) && ! in_array($slug, $ordered, true)) {
                $ordered[] = $slug;
            }
        }
        foreach ($available as $slug) {
            if (! in_array($slug, $ordered, true)) {
                $ordered[] = $slug;
            }
        }
        return $ordered;
    }
}

if (! function_exists('node_token')) {
    /**
     * The single shared secret between Laravel and the Node bridge, used both
     * to AUTHENTICATE inbound Node→Laravel calls and to SIGN outbound
     * Laravel→Node calls. Resolution order (first non-empty wins):
     *   1. SystemSetting 'node_webhook_token'   — set by admin at
     *      /admin/settings/wadesk-message (encrypted at rest)
     *   2. SystemSetting 'baileys_callback_token' — legacy key, kept for
     *      installs that set it before the rename
     *   3. .env NODE_WEBHOOK_TOKEN               — what the installer writes
     *
     * IMPORTANT: the Node bridge validates against its OWN process.env
     * NODE_WEBHOOK_TOKEN, so whatever value the admin sets here must also be
     * set in node/.env (they must match on both sides).
     */
    function node_token(): string
    {
        try {
            $v = (string) \App\Models\SystemSetting::get('node_webhook_token', '');
            if ($v === '') {
                $v = (string) \App\Models\SystemSetting::get('baileys_callback_token', '');
            }
        } catch (\Throwable $e) {
            $v = '';
        }
        return $v !== '' ? $v : (string) env('NODE_WEBHOOK_TOKEN', '');
    }
}

if (! function_exists('wd_node_url')) {
    /**
     * Base URL of the Node bridge. DB `baileys_server_url` (set by admin at
     * /admin/settings/wadesk-message) is authoritative; falls back to the
     * .env SERVER_URL the installer writes. Mirrors node_token() so the whole
     * app reads the same place — no DB-vs-env drift. Returned without a
     * trailing slash.
     */
    function wd_node_url(): string
    {
        try {
            $v = (string) \App\Models\SystemSetting::get('baileys_server_url', '');
        } catch (\Throwable $e) {
            $v = '';
        }
        if ($v === '') {
            $v = (string) env('SERVER_URL', '');
        }
        return rtrim($v, '/');
    }
}

if (! function_exists('wd_base')) {
    /**
     * The URL sub-folder this install is served from — "/public" when the
     * web root is the project root and the app is reached at example.com/public/,
     * or "" when the web root already points at public/ (clean install).
     *
     * Computed purely from $_SERVER (the physical location of index.php vs the
     * web server's document root), so it can NEVER be wrong because of a cached
     * config, a stale APP_URL, or a host that miscomputes Laravel's base path.
     * This is the single source of truth used by AppServiceProvider (to force
     * the URL root) and by the layouts (to feed the client-side AJAX prefix).
     *
     *   <a href="{{ wd_base() }}/admin">   →   /public/admin  (or /admin at root)
     */
    function wd_base(): string
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        $docRoot   = rtrim(str_replace('\\', '/', (string) ($_SERVER['DOCUMENT_ROOT'] ?? '')), '/');
        $scriptDir = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_FILENAME'] ?? ''))), '/');

        $base = '';
        if ($docRoot !== '' && $scriptDir !== '' && str_starts_with($scriptDir, $docRoot)) {
            // e.g. scriptDir=/home/u/wadesk/public, docRoot=/home/u/wadesk → "/public"
            $base = rtrim(substr($scriptDir, strlen($docRoot)), '/');
        }

        if ($base === '') {
            // Fallback: directory of the script in the URL (e.g. /public/index.php).
            $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
            $dir    = rtrim(dirname($script), '/');
            $base   = ($dir === '/' || $dir === '.') ? '' : $dir;
        }

        // Normalize: convert any Windows backslashes first (PHP's dirname()
        // returns "\" for a root path on Windows, e.g. dirname('/index.php')),
        // then strip slashes and collapse to "" or "/sub" — NEVER a bare "/" or
        // anything with a "\" (both produce broken "//x" / "/\/x" URLs).
        $base = '/' . trim(str_replace('\\', '/', $base), '/');
        if ($base === '/') {
            $base = '';
        }

        // Guard against a ROOT .htaccess that rewrites "/x" -> "/public/x"
        // INTERNALLY (full app sitting in the web root). The filesystem then
        // reports the front controller under "/public", so the checks above
        // derive "/public" — but the BROWSER never used that prefix (the address
        // bar shows the domain root), so baking "/public" into every link would
        // be wrong (e.g. Dashboard -> /public/dashboard). Only keep the
        // sub-folder when the LIVE request URI actually starts with it. CLI /
        // queue has no REQUEST_URI → leave the filesystem result untouched.
        if ($base !== '' && isset($_SERVER['REQUEST_URI'])) {
            $uriPath = strtok('/' . ltrim(str_replace('\\', '/', (string) $_SERVER['REQUEST_URI']), '/'), '?');
            if ($uriPath !== $base && ! str_starts_with((string) $uriPath, $base . '/')) {
                $base = ''; // internal rewrite — the browser isn't on /public
            }
        }

        return $cached = $base;
    }
}

if (! function_exists('fc_section_visible')) {
    /**
     * Should a composition page render this section? False only when an
     * admin hid it via the live editor. Defaults to visible, so an
     * un-edited install shows every section exactly as shipped.
     */
    function fc_section_visible(string $page, string $slug): bool
    {
        return \App\Services\Frontend\FrontendRegistry::visible($page, $slug);
    }
}

if (! function_exists('app_font_catalog')) {
    /** Curated Google-font catalog for the admin font-family picker. */
    function app_font_catalog(): array
    {
        return [
            'inter'        => ['label' => 'Inter',              'stack' => "'Inter', system-ui, sans-serif",             'g' => 'Inter:wght@400;500;600;700'],
            'roboto'       => ['label' => 'Roboto',             'stack' => "'Roboto', system-ui, sans-serif",            'g' => 'Roboto:wght@400;500;700'],
            'open-sans'    => ['label' => 'Open Sans',          'stack' => "'Open Sans', system-ui, sans-serif",         'g' => 'Open+Sans:wght@400;500;600;700'],
            'poppins'      => ['label' => 'Poppins',            'stack' => "'Poppins', system-ui, sans-serif",           'g' => 'Poppins:wght@400;500;600;700'],
            'lato'         => ['label' => 'Lato',               'stack' => "'Lato', system-ui, sans-serif",              'g' => 'Lato:wght@400;700'],
            'montserrat'   => ['label' => 'Montserrat',         'stack' => "'Montserrat', system-ui, sans-serif",        'g' => 'Montserrat:wght@400;500;600;700'],
            'nunito'       => ['label' => 'Nunito',             'stack' => "'Nunito', system-ui, sans-serif",            'g' => 'Nunito:wght@400;600;700'],
            'work-sans'    => ['label' => 'Work Sans',          'stack' => "'Work Sans', system-ui, sans-serif",         'g' => 'Work+Sans:wght@400;500;600;700'],
            'dm-sans'      => ['label' => 'DM Sans',            'stack' => "'DM Sans', system-ui, sans-serif",           'g' => 'DM+Sans:wght@400;500;700'],
            'rubik'        => ['label' => 'Rubik',              'stack' => "'Rubik', system-ui, sans-serif",             'g' => 'Rubik:wght@400;500;600;700'],
            'manrope'      => ['label' => 'Manrope',            'stack' => "'Manrope', system-ui, sans-serif",           'g' => 'Manrope:wght@400;500;600;700'],
            'plus-jakarta' => ['label' => 'Plus Jakarta Sans', 'stack' => "'Plus Jakarta Sans', system-ui, sans-serif", 'g' => 'Plus+Jakarta+Sans:wght@400;500;600;700'],
        ];
    }
}

if (! function_exists('app_font')) {
    /**
     * Resolve the admin-selected UI font (general settings → font_family).
     * Returns ['key','label','stack','url']. Empty key = theme default, so no
     * Google font is loaded and no CSS override is emitted.
     */
    function app_font(): array
    {
        try {
            $key = (string) \App\Models\SystemSetting::get('font_family', '');
        } catch (\Throwable $e) {
            $key = '';
        }

        $catalog = app_font_catalog();
        if ($key === '' || ! isset($catalog[$key])) {
            return ['key' => '', 'label' => 'Theme default', 'stack' => '', 'url' => ''];
        }

        $f = $catalog[$key];
        return [
            'key'   => $key,
            'label' => $f['label'],
            'stack' => $f['stack'],
            'url'   => 'https://fonts.googleapis.com/css2?family=' . $f['g'] . '&display=swap',
        ];
    }
}

if (! function_exists('legal_url')) {
    /**
     * Resolve a legal/policy link from the admin Privacy settings
     * (/admin/settings/privacy). If the admin set a custom URL there it wins;
     * otherwise we fall back to the built-in /legal/* page. Use this EVERYWHERE
     * Terms / Privacy / Cookies links appear (checkout, auth, footer, …) so the
     * links are never hardcoded:  href="{{ legal_url('terms') }}"
     *
     * @param string $which terms | privacy | cookies (anything else → /legal/{which})
     */
    function legal_url(string $which): string
    {
        $map = [
            'terms'   => 'privacy_terms_url',
            'privacy' => 'privacy_policy_url',
            'cookies' => 'privacy_cookies_policy_url',
        ];
        $configured = '';
        if (isset($map[$which])) {
            try {
                $configured = trim((string) \App\Models\SystemSetting::get($map[$which], ''));
            } catch (\Throwable $e) {
                $configured = '';
            }
        }
        return $configured !== '' ? $configured : url('/legal/' . $which);
    }
}

if (! function_exists('cloud_storage')) {
    /** The cloud-media storage manager (singleton). */
    function cloud_storage(): \App\Services\CloudStorageManager
    {
        return app(\App\Services\CloudStorageManager::class);
    }
}

if (! function_exists('media_disk')) {
    /**
     * Disk name that client media should use: the configured cloud provider
     * when set up + enabled, otherwise the local `public` disk. Lazy + tolerant
     * — never throws on the hot path, falls back to local.
     */
    function media_disk(): string
    {
        try {
            return cloud_storage()->diskName();
        } catch (\Throwable $e) {
            return \App\Services\CloudStorageManager::LOCAL_DISK;
        }
    }
}

if (! function_exists('media_storage')) {
    /** Storage disk instance client media should use (cloud or local). */
    function media_storage(): \Illuminate\Contracts\Filesystem\Filesystem
    {
        try {
            return cloud_storage()->disk();
        } catch (\Throwable $e) {
            return \Illuminate\Support\Facades\Storage::disk(\App\Services\CloudStorageManager::LOCAL_DISK);
        }
    }
}

if (! function_exists('app_default_country')) {
    /**
     * Platform-wide default country for every phone-input picker.
     *
     * Admin sets this once in /admin/settings/general → "Default country".
     * Reading it through ONE helper keeps the blade defaults
     * (`country_code` hidden inputs) AND the JS flag-picker init
     * (intl-tel-input `initialCountry`) in sync with whatever the admin
     * saved — so when the platform is white-labeled for an Indonesian
     * customer the "+91 / 🇮🇳" default flips to "+62 / 🇮🇩" without
     * touching 14 files individually.
     *
     * Returns: ['code' => '+62', 'iso' => 'id']
     * Falls back to ['code' => '+91', 'iso' => 'in'] when nothing saved.
     */
    function app_default_country(): array
    {
        $code = trim((string) \App\Models\SystemSetting::get('default_country_code', '+91')) ?: '+91';
        $iso  = strtolower(trim((string) \App\Models\SystemSetting::get('default_country_iso', 'in'))) ?: 'in';
        if (! str_starts_with($code, '+')) $code = '+' . preg_replace('/\D+/', '', $code);
        if (! preg_match('/^[a-z]{2}$/', $iso)) $iso = 'in';
        return ['code' => $code, 'iso' => $iso];
    }
}

if (! function_exists('wa_template_languages')) {
    /**
     * The full set of WhatsApp message-template language codes Meta
     * accepts, as `code => Language label`, ordered common-first.
     *
     * Verified against Meta's official "Supported Languages for template
     * messages" list (graph v23). The codes are EXACTLY what Meta expects
     * in the create-template `language` field and the send-time
     * `language.code` — e.g. Indonesian is `id` (NOT `id_ID`), Arabic is
     * `ar` (NOT `ar_AR`), Malay is `ms`. Getting these wrong makes Meta
     * reject the template with error 132001 "language not supported".
     *
     * One source of truth for every template language dropdown (create +
     * edit) so the list can never drift between the two forms.
     */
    function wa_template_languages(): array
    {
        return [
            // ---- most commonly used first ----
            'en_US' => 'English (US)',
            'en_GB' => 'English (UK)',
            'en'    => 'English',
            'id'    => 'Indonesian',
            'ms'    => 'Malay',
            'es'    => 'Spanish',
            'es_ES' => 'Spanish (Spain)',
            'es_MX' => 'Spanish (Mexico)',
            'es_AR' => 'Spanish (Argentina)',
            'pt_BR' => 'Portuguese (Brazil)',
            'pt_PT' => 'Portuguese (Portugal)',
            'fr'    => 'French',
            'de'    => 'German',
            'it'    => 'Italian',
            'nl'    => 'Dutch',
            'ar'    => 'Arabic',
            'hi'    => 'Hindi',
            'ur'    => 'Urdu',
            'bn'    => 'Bengali',
            'ta'    => 'Tamil',
            'te'    => 'Telugu',
            'th'    => 'Thai',
            'vi'    => 'Vietnamese',
            'fil'   => 'Filipino',
            'ja'    => 'Japanese',
            'ko'    => 'Korean',
            'zh_CN' => 'Chinese (China)',
            'zh_HK' => 'Chinese (Hong Kong)',
            'zh_TW' => 'Chinese (Taiwan)',
            'ru'    => 'Russian',
            'tr'    => 'Turkish',
            // ---- the rest, alphabetical by language ----
            'af'    => 'Afrikaans',
            'sq'    => 'Albanian',
            'az'    => 'Azerbaijani',
            'bg'    => 'Bulgarian',
            'ca'    => 'Catalan',
            'hr'    => 'Croatian',
            'cs'    => 'Czech',
            'da'    => 'Danish',
            'et'    => 'Estonian',
            'fi'    => 'Finnish',
            'ka'    => 'Georgian',
            'el'    => 'Greek',
            'gu'    => 'Gujarati',
            'ha'    => 'Hausa',
            'he'    => 'Hebrew',
            'hu'    => 'Hungarian',
            'ga'    => 'Irish',
            'kn'    => 'Kannada',
            'kk'    => 'Kazakh',
            'rw_RW' => 'Kinyarwanda',
            'ky_KG' => 'Kyrgyz (Kyrgyzstan)',
            'lo'    => 'Lao',
            'lv'    => 'Latvian',
            'lt'    => 'Lithuanian',
            'mk'    => 'Macedonian',
            'ml'    => 'Malayalam',
            'mr'    => 'Marathi',
            'nb'    => 'Norwegian',
            'fa'    => 'Persian',
            'pl'    => 'Polish',
            'pa'    => 'Punjabi',
            'ro'    => 'Romanian',
            'sr'    => 'Serbian',
            'sk'    => 'Slovak',
            'sl'    => 'Slovenian',
            'sw'    => 'Swahili',
            'sv'    => 'Swedish',
            'uk'    => 'Ukrainian',
            'uz'    => 'Uzbek',
            'zu'    => 'Zulu',
        ];
    }
}

if (! function_exists('media_url')) {
    /**
     * Public URL for a stored media path, resolved from the active disk —
     * the cloud provider's URL/CDN when enabled, else the local /storage URL.
     * Drop-in replacement for asset('storage/'.$p) and Storage::url($p).
     * Pass the bare object path (no leading "storage/").
     */
    function media_url(?string $path): string
    {
        $path = ltrim((string) $path, '/');
        if ($path === '') {
            return '';
        }
        // Tolerate callers that still pass a "storage/…"-prefixed path.
        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, 8);
        }
        try {
            return media_storage()->url($path);
        } catch (\Throwable $e) {
            return asset('storage/' . $path);
        }
    }
}
