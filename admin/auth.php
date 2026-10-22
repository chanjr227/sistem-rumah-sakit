<?php

declare(strict_types=1);

function startAdminSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_set_cookie_params([
        'httponly' => true,
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Strict',
    ]);
    session_start();
}

function adminCsrfToken(): string
{
    startAdminSession();
    if (!isset($_SESSION['admin_csrf_token'])) {
        $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['admin_csrf_token'];
}

function verifyAdminCsrfToken(): bool
{
    $submittedToken = $_POST['csrf_token'] ?? '';
    return is_string($submittedToken)
        && isset($_SESSION['admin_csrf_token'])
        && hash_equals($_SESSION['admin_csrf_token'], $submittedToken);
}


function requireAdminLogin(): void
{
    startAdminSession();

    if (
        empty($_SESSION['admin_authenticated']) ||
        ($_SESSION['account_role'] ?? '') !== 'admin'
    ) {
        header('Location: /rumah_sakit/admin/login.php');
        exit;
    }
}

function requireDoctorLogin(): void
{
    startAdminSession();

    if (
        empty($_SESSION['admin_authenticated']) ||
        ($_SESSION['account_role'] ?? '') !== 'dokter' ||
        empty($_SESSION['doctor_id'])
    ) {
        header('Location: /rumah_sakit/admin/login.php');
        exit;
    }
}


function escapeHtml(string $value): string
{
    return htmlspecialchars(
        $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}
