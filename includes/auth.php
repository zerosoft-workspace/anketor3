<?php
function attempt_login(Database $db, string $email, string $password): bool
{
    $user = $db->fetch('SELECT * FROM users WHERE email = ?', [$email]);
    if (!$user) {
        return false;
    }

    if (!password_verify($password, $user['password_hash'])) {
        return false;
    }

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['name'];

    $db->execute('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$user['id']]);
    return true;
}

function logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function current_user_id(): ?int
{
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}

function current_user_name(): string
{
    return $_SESSION['user_name'] ?? '';
}

function require_login(): void
{
    if (!current_user_id()) {
        redirect('index.php');
    }
}
