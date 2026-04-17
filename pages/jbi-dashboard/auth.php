<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/db_store.php';

function ensure_auth_schema(): void
{
    // Auth schema is managed by the main application.
    // Keep this no-op for compatibility with legacy jbi-dashboard code.
}

function users_count(): int
{
    ensure_auth_schema();
    $st = db()->query('SELECT COUNT(*) AS c FROM users');

    return (int) ($st->fetch_assoc()['c'] ?? 0);
}

function user_find_by_username(string $username): ?array
{
    ensure_auth_schema();
    $st = db()->prepare('SELECT id, username, password_hash FROM users WHERE username = ? LIMIT 1');
    $st->bind_param('s', $username);
    $st->execute();
    $result = $st->get_result();
    $row = $result->fetch_assoc();

    return is_array($row) ? $row : null;
}

function register_first_user(string $username, string $password): bool
{
    $username = trim($username);
    if ($username === '' || strlen($password) < 6) {
        return false;
    }

    if (users_count() > 0) {
        return false;
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $uid = insert_user_record($username, $hash);
    if ($uid <= 0) {
        return false;
    }

    $_SESSION['auth_user_id'] = $uid;
    $_SESSION['auth_username'] = $username;

    return true;
}

function insert_user_record(string $username, string $hash): int
{
    try {
        $st = db()->prepare('INSERT INTO users (username, password_hash) VALUES (?, ?)');
        $st->bind_param('ss', $username, $hash);
        $st->execute();

        return (int) db()->insert_id;
    } catch (Throwable $e) {
        // MySQL 1467: auto-increment read failed; fallback to manual id.
        $msg = $e->getMessage();
        if (strpos($msg, '1467') === false) {
            throw $e;
        }

        $nextSt = db()->query('SELECT COALESCE(MAX(id), 0) + 1 AS next_id FROM users');
        $nextRow = $nextSt->fetch_assoc();
        $nextId = (int) ($nextRow['next_id'] ?? 0);
        if ($nextId <= 0) {
            return 0;
        }

        $stManual = db()->prepare('INSERT INTO users (id, username, password_hash) VALUES (?, ?, ?)');
        $stManual->bind_param('iss', $nextId, $username, $hash);
        $stManual->execute();

        return $nextId;
    }
}

function login_user(string $username, string $password): bool
{
    $username = trim($username);
    if ($username === '' || $password === '') {
        return false;
    }

    $user = user_find_by_username($username);
    if ($user === null) {
        return false;
    }

    if (!password_verify($password, (string) $user['password_hash'])) {
        return false;
    }

    $_SESSION['auth_user_id'] = (int) $user['id'];
    $_SESSION['auth_username'] = (string) $user['username'];

    return true;
}

function is_logged_in(): bool
{
    return isset($_SESSION['user_id'], $_SESSION['username']) || isset($_SESSION['auth_user_id'], $_SESSION['auth_username']);
}

function current_username(): string
{
    if (!empty($_SESSION['username'])) {
        return (string) $_SESSION['username'];
    }
    return (string) ($_SESSION['auth_username'] ?? '');
}

function require_login_or_redirect(): void
{
    if (!is_logged_in()) {
        $loginUrl = defined('BASE_URL') ? BASE_URL . '/login.php' : 'login.php';
        header('Location: ' . $loginUrl, true, 302);
        exit;
    }
}

function require_login_or_401(): void
{
    if (!is_logged_in()) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Unauthorized'], JSON_UNESCAPED_SLASHES);
        exit;
    }
}

function logout_user(): void
{
    unset($_SESSION['user_id'], $_SESSION['username'], $_SESSION['auth_user_id'], $_SESSION['auth_username']);
}
