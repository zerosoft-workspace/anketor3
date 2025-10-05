<?php
function config(string $key, $default = null)
{
    $config = $GLOBALS['config'] ?? [];
    $segments = explode('.', $key);
    $value = $config;

    foreach ($segments as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }
        $value = $value[$segment];
    }

    return $value;
}

function base_url(string $path = ''): string
{
    $base = rtrim(config('app.base_url', ''), '/');
    return $base . '/' . ltrim($path, '/');
}

function asset_url(string $path): string
{
    return base_url('assets/' . ltrim($path, '/'));
}

function redirect(string $path)
{
    header('Location: ' . $path);
    exit;
}

function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function get_flash(): ?array
{
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }

    return null;
}

function old(string $key, $default = '')
{
    $old = $_SESSION['old'] ?? [];
    return htmlspecialchars($old[$key] ?? $default, ENT_QUOTES, 'UTF-8');
}

function save_old(array $data): void
{
    $_SESSION['old'] = $data;
}

function clear_old(): void
{
    unset($_SESSION['old']);
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

function verify_csrf(?string $token): bool
{
    return $token && hash_equals($_SESSION['_csrf_token'] ?? '', $token);
}

function request_method(): string
{
    return $_SERVER['REQUEST_METHOD'] ?? 'GET';
}

function is_post(): bool
{
    return request_method() === 'POST';
}

function sanitize_text(string $text): string
{
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

function format_date($value, string $format = 'd.m.Y')
{
    if (!$value) {
        return '';
    }

    $date = $value instanceof DateTimeInterface ? $value : new DateTime($value);
    return $date->format($format);
}

function ensure_authenticated(): void
{
    if (!current_user_id()) {
        redirect('index.php');
    }
}

function active_nav(string $filename): string
{
    $current = basename($_SERVER['SCRIPT_NAME'] ?? '');
    return $current === $filename ? 'is-active' : '';
}

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function average_from_questions(array $questions): string
{
    $values = [];
    foreach ($questions as $question) {
        if (($question['type'] ?? '') === 'rating' && !empty($question['average'])) {
            $values[] = (float)$question['average'];
        }
    }

    return average_from_values($values);
}

function average_from_values(array $values): string
{
    if (empty($values)) {
        return '-';
    }

    return number_format(array_sum($values) / count($values), 2);
}
