<?php
declare(strict_types=1);

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Env;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Session;

function env(string $key, mixed $default = null): mixed
{
    return Env::get($key, $default);
}

function env_bool(string $key, bool $default = false): bool
{
    $value = Env::get($key);
    return $value === null ? $default : filter_var($value, FILTER_VALIDATE_BOOL);
}

function config(string $key, mixed $default = null): mixed
{
    static $config = null;
    $config ??= require BASE_PATH . '/config/app.php';
    $value = $config;
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }
    return $value;
}

/** HTML-escape for text and attribute contexts. Use on every dynamic value in views. */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
}

function url(string $path = '/', array $query = []): string
{
    $url = Request::basePath() . '/' . ltrim($path, '/');
    $query = array_filter($query, static fn ($v) => $v !== null && $v !== '');
    return $url . ($query ? '?' . http_build_query($query) : '');
}

/** Absolute URL for emails and other off-site links. */
function absolute_url(string $path = '/'): string
{
    return config('url') . '/' . ltrim($path, '/');
}

/** Public asset URL with a file-modified cache-buster. */
function asset(string $path): string
{
    $file = BASE_PATH . '/public/assets/' . ltrim($path, '/');
    $version = is_file($file) ? (string) filemtime($file) : '0';
    return url('assets/' . ltrim($path, '/')) . '?v=' . $version;
}

function csrf_token(): string
{
    return Csrf::token();
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(Csrf::token()) . '">';
}

function user(): ?array
{
    return Auth::user();
}

function can(string ...$permissions): bool
{
    return Gate::any(...$permissions);
}

function setting(string $key, ?string $default = null): ?string
{
    return App\Services\Settings::get($key, $default);
}

// ---------------------------------------------------------------------------
// Form state (old input + validation errors survive one redirect)
// ---------------------------------------------------------------------------
function form_state(): array
{
    static $state = null;
    if ($state === null) {
        $state = [
            'old'    => Session::pull('_old', []),
            'errors' => Session::pull('_errors', []),
        ];
    }
    return $state;
}

function old(string $key, mixed $default = ''): mixed
{
    return form_state()['old'][$key] ?? $default;
}

function error(string $key): ?string
{
    return form_state()['errors'][$key] ?? null;
}

function errors(): array
{
    return form_state()['errors'];
}

/** Redirect back to a form with errors and the submitted values (passwords never kept). */
function back_with_errors(array $errors, string $to, array $old = []): never
{
    $errors = array_filter($errors, static fn ($m) => $m !== null && $m !== '');
    unset($old['password'], $old['password_confirmation'], $old['current_password'], $old['_csrf']);
    Session::set('_errors', $errors);
    Session::set('_old', $old);
    App\Core\Response::redirect($to);
}

// ---------------------------------------------------------------------------
// Formatting
// ---------------------------------------------------------------------------
function fmt_date(?string $value, string $format = 'j M Y, H:i'): string
{
    return $value ? date($format, strtotime($value)) : '—';
}

function fmt_relative(?string $value): string
{
    if (!$value) {
        return '—';
    }
    $diff = time() - strtotime($value);
    $future = $diff < 0;
    $diff = abs($diff);
    $text = match (true) {
        $diff < 60     => 'just now',
        $diff < 3600   => intdiv($diff, 60) . ' min',
        $diff < 86400  => intdiv($diff, 3600) . ' h',
        $diff < 604800 => intdiv($diff, 86400) . ' d',
        default        => null,
    };
    if ($text === null) {
        return fmt_date($value, 'j M Y');
    }
    if ($text === 'just now') {
        return $text;
    }
    return $future ? 'in ' . $text : $text . ' ago';
}

function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $first = mb_substr($parts[0] ?? '', 0, 1);
    $last = count($parts) > 1 ? mb_substr(end($parts), 0, 1) : '';
    return mb_strtoupper($first . $last);
}

// ---------------------------------------------------------------------------
// Icons — inline SVG (24px grid, stroke). Decorative by default: pair with text.
// ---------------------------------------------------------------------------
function icon(string $name, string $class = 'h-5 w-5', ?string $label = null): string
{
    static $paths = [
        'home'          => '<path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M9 22V12h6v10"/>',
        'dashboard'     => '<rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/>',
        'ticket'        => '<path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"/><path d="M13 5v2M13 11v2M13 17v2"/>',
        'inbox'         => '<path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/>',
        'plus'          => '<path d="M12 5v14M5 12h14"/>',
        'bell'          => '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>',
        'chart'         => '<path d="M3 3v18h18"/><path d="M18 17V9M13 17V5M8 17v-3"/>',
        'shield'        => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
        'shield-check'  => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/>',
        'users'         => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
        'user'          => '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'user-check'    => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="m16 11 2 2 4-4"/>',
        'settings'      => '<path d="M4 21v-7M4 10V3M12 21v-9M12 8V3M20 21v-5M20 12V3M1 14h6M9 8h6M17 16h6"/>',
        'logout'        => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5M21 12H9"/>',
        'menu'          => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        'more'          => '<circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/>',
        'x'             => '<path d="M18 6 6 18M6 6l12 12"/>',
        'check'         => '<path d="M20 6 9 17l-5-5"/>',
        'check-circle'  => '<circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/>',
        'alert-triangle'=> '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4M12 17h.01"/>',
        'alert-circle'  => '<circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/>',
        'info'          => '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/>',
        'clock'         => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
        'chevron-right' => '<path d="m9 18 6-6-6-6"/>',
        'chevron-left'  => '<path d="m15 18-6-6 6-6"/>',
        'chevron-down'  => '<path d="m6 9 6 6 6-6"/>',
        'search'        => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
        'filter'        => '<path d="M22 3H2l8 9.46V19l4 2v-8.54L22 3z"/>',
        'paperclip'     => '<path d="m21.44 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l8.57-8.57A4 4 0 1 1 18 8.84l-8.59 8.57a2 2 0 0 1-2.83-2.83l8.49-8.48"/>',
        'eye'           => '<path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/>',
        'eye-off'       => '<path d="M9.88 9.88a3 3 0 1 0 4.24 4.24M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68M6.61 6.61A13.53 13.53 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61M2 2l20 20"/>',
        'lock'          => '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
        'unlock'        => '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 9.9-1"/>',
        'key'           => '<circle cx="7.5" cy="15.5" r="5.5"/><path d="m21 2-9.6 9.6M15.5 7.5l3 3L22 7l-3-3"/>',
        'flame'         => '<path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.07-2.14-.22-4.05 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.15.43-2.29 1-3a2.5 2.5 0 0 0 2.5 2.5z"/>',
        'arrow-up'      => '<path d="M12 19V5M5 12l7-7 7 7"/>',
        'arrow-down'    => '<path d="M12 5v14M19 12l-7 7-7-7"/>',
        'minus'         => '<path d="M5 12h14"/>',
        'monitor'       => '<rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/>',
        'app'           => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/>',
        'wifi'          => '<path d="M5 13a10 10 0 0 1 14 0M8.5 16.5a5 5 0 0 1 7 0M2 8.82a15 15 0 0 1 20 0M12 20h.01"/>',
        'help'          => '<circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3M12 17h.01"/>',
        'tag'           => '<path d="M12 2H2v10l9.29 9.29a1 1 0 0 0 1.41 0l8.6-8.6a1 1 0 0 0 0-1.41z"/><circle cx="7" cy="7" r="1.5"/>',
        'download'      => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m7 10 5 5 5-5M12 15V3"/>',
        'file'          => '<path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><path d="M14 2v6h6"/>',
        'building'      => '<rect x="4" y="2" width="16" height="20" rx="2"/><path d="M9 22v-4h6v4M8 6h.01M12 6h.01M16 6h.01M8 10h.01M12 10h.01M16 10h.01M8 14h.01M12 14h.01M16 14h.01"/>',
        'map-pin'       => '<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/>',
        'edit'          => '<path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/>',
        'refresh'       => '<path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8M21 3v5h-5M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16M8 16H3v5"/>',
        'circle-dot'    => '<circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="1"/>',
        'play-circle'   => '<circle cx="12" cy="12" r="10"/><path d="m10 8 6 4-6 4z"/>',
        'pause-circle'  => '<circle cx="12" cy="12" r="10"/><path d="M10 15V9M14 15V9"/>',
        'rotate-ccw'    => '<path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/>',
        'archive'       => '<rect x="2" y="3" width="20" height="5" rx="1"/><path d="M4 8v11a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8M10 12h4"/>',
    ];
    $inner = $paths[$name] ?? $paths['help'];
    $a11y = $label === null
        ? 'aria-hidden="true" focusable="false"'
        : 'role="img" aria-label="' . e($label) . '"';
    return '<svg class="' . e($class) . '" ' . $a11y . ' xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' . $inner . '</svg>';
}

// ---------------------------------------------------------------------------
// Form field accessibility: ties inputs to their hint/error text.
// ---------------------------------------------------------------------------
/** aria attributes for an input. $hint = true when a "{name}-hint" element exists. */
function field_attrs(string $name, bool $hint = false): string
{
    $describedBy = [];
    if ($hint) {
        $describedBy[] = $name . '-hint';
    }
    if (error($name)) {
        $describedBy[] = $name . '-error';
    }
    $attrs = 'id="' . e($name) . '" name="' . e($name) . '"';
    if (error($name)) {
        $attrs .= ' aria-invalid="true"';
    }
    if ($describedBy) {
        $attrs .= ' aria-describedby="' . e(implode(' ', $describedBy)) . '"';
    }
    return $attrs;
}

function field_error(string $name): string
{
    $message = error($name);
    if (!$message) {
        return '';
    }
    return '<p class="field-error" id="' . e($name) . '-error">' . icon('alert-circle', 'mt-0.5 h-4 w-4 shrink-0') . '<span>' . e($message) . '</span></p>';
}

/** For single-line fields (titles, names, locations): collapse line breaks and runs of spaces. */
function one_line(string $value): string
{
    return trim((string) preg_replace('/\s+/u', ' ', $value));
}

/**
 * Only same-site relative paths are safe redirect targets. Rejects "//host",
 * "/\host" (browsers treat both as another site) and anything with a scheme.
 */
function safe_path(mixed $path, string $fallback = '/'): string
{
    if (!is_string($path) || $path === '' || $path[0] !== '/' || preg_match('#^/[/\\\\]#', $path) || preg_match('/[\x00-\x1F]/', $path)) {
        return $fallback;
    }
    return $path;
}
