<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../config/database.php';

requireAdminLogin();
startAdminSession();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Location: akun.php');
    exit;
}

$token = $_POST['csrf_token'] ?? '';
$sessionToken = $_SESSION['admin_csrf_token'] ?? '';

if (
    !is_string($token)
    || !is_string($sessionToken)
    || $sessionToken === ''
    || !hash_equals($sessionToken, $token)
) {
    $_SESSION['akun_flash_error'] =
        'Token keamanan tidak valid. Silakan coba kembali.';
    header('Location: akun.php');
    exit;
}

$accountId = filter_var(
    $_POST['id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if ($accountId === false || $accountId === null) {
    $_SESSION['akun_flash_error'] = 'ID akun tidak valid.';
    header('Location: akun.php');
    exit;
}

$currentAdminId = (int) ($_SESSION['admin_id'] ?? 0);

if ((int) $accountId === $currentAdminId) {
    $_SESSION['akun_flash_error'] =
        'Akun yang sedang Anda gunakan tidak dapat dihapus.';
    header('Location: akun.php');
    exit;
}

try {
    $connection = getDatabaseConnection();

    $check = $connection->prepare(
        'SELECT id FROM admins WHERE id = :id LIMIT 1'
    );
    $check->execute(['id' => $accountId]);

    if (!$check->fetchColumn()) {
        $_SESSION['akun_flash_error'] = 'Akun tidak ditemukan.';
        header('Location: akun.php');
        exit;
    }

    $delete = $connection->prepare(
        'DELETE FROM admins WHERE id = :id'
    );
    $delete->execute(['id' => $accountId]);

    $_SESSION['akun_flash_success'] = 'Akun berhasil dihapus.';
} catch (PDOException $exception) {
    error_log('Delete account error: ' . $exception->getMessage());

    $_SESSION['akun_flash_error'] =
        'Akun gagal dihapus. Periksa relasi database atau coba lagi.';
}

header('Location: akun.php');
exit;
