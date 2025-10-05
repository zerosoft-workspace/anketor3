<?php
function generate_token(int $length = 32): string
{
    return bin2hex(random_bytes($length));
}

function hash_email(string $email): string
{
    $salt = config('security.token_salt', '');
    return hash('sha256', strtolower(trim($email)) . $salt);
}

function signed_token(string $token): string
{
    $salt = config('security.token_salt', '');
    return hash('sha256', $token . $salt);
}

function guard_csrf(): void
{
    if (!verify_csrf($_POST['_token'] ?? null)) {
        http_response_code(419);
        exit('Invalid CSRF token');
    }
}
