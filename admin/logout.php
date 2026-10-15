<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';

startAdminSession();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Metode permintaan tidak diizinkan.');
}

if (!verifyAdminCsrfToken()) {
    http_response_code(403);
    exit('Sesi formulir tidak valid.');
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $cookieParameters = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $cookieParameters['path'],
        $cookieParameters['domain'],
        $cookieParameters['secure'],
        $cookieParameters['httponly']
    );
}
session_destroy();

header('Location: login.php');
exit;
