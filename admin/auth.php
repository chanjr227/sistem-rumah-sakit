<?php

declare(strict_types=1);

/**
 * Memulai sesi pengguna.
 */
function startAdminSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_set_cookie_params([
        'httponly' => true,
        'secure' => isset($_SERVER['HTTPS'])
            && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Strict',
    ]);

    session_start();
}

/**
 * Membuat token CSRF.
 */
function adminCsrfToken(): string
{
    startAdminSession();

    if (!isset($_SESSION['admin_csrf_token'])) {
        $_SESSION['admin_csrf_token'] = bin2hex(
            random_bytes(32)
        );
    }

    return $_SESSION['admin_csrf_token'];
}

/**
 * Memeriksa token CSRF.
 */
function verifyAdminCsrfToken(): bool
{
    startAdminSession();

    $submittedToken = $_POST['csrf_token'] ?? '';

    return is_string($submittedToken)
        && isset($_SESSION['admin_csrf_token'])
        && hash_equals(
            $_SESSION['admin_csrf_token'],
            $submittedToken
        );
}

/**
 * Memeriksa apakah pengguna sudah login.
 */
function isUserLoggedIn(): bool
{
    startAdminSession();

    return !empty($_SESSION['admin_authenticated'])
        && isset($_SESSION['account_role'])
        && in_array(
            $_SESSION['account_role'],
            ['admin', 'dokter', 'perawat', 'farmasi'],
            true
        );
}

/**
 * Memeriksa role pengguna.
 *
 * Admin dapat diberikan akses lintas modul jika diperlukan.
 * Sesuaikan kebijakan ini dengan kebutuhan rumah sakit.
 */
function requireRole(array $allowedRoles): void
{
    startAdminSession();

    if (!isUserLoggedIn()) {
        header('Location: /rumah_sakit/admin/login.php');
        exit;
    }

    $currentRole = (string) $_SESSION['account_role'];

    if (
        $currentRole !== 'admin'
        && !in_array($currentRole, $allowedRoles, true)
    ) {
        http_response_code(403);

        exit('403 - Anda tidak memiliki izin untuk mengakses halaman ini.');
    }
}

/**
 * Akses khusus Admin.
 */
function requireAdminLogin(): void
{
    startAdminSession();

    if (
        empty($_SESSION['admin_authenticated'])
        || ($_SESSION['account_role'] ?? '') !== 'admin'
    ) {
        header('Location: /rumah_sakit/admin/login.php');
        exit;
    }
}

/**
 * Akses khusus Dokter.
 */
function requireDoctorLogin(): void
{
    requireRole(['dokter']);

    if (empty($_SESSION['doctor_id'])) {
        http_response_code(403);
        exit('Akun dokter belum terhubung dengan data dokter.');
    }
}

/**
 * Akses khusus Perawat.
 */
function requireNurseLogin(): void
{
    requireRole(['perawat']);
}

/**
 * Akses khusus Farmasi.
 */
function requirePharmacyLogin(): void
{
    requireRole(['farmasi']);
}

/**
 * Escape teks untuk ditampilkan di HTML.
 */
function escapeHtml(string $value): string
{
    return htmlspecialchars(
        $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}
