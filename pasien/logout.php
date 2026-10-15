<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';

startPatientSession();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Metode permintaan tidak diizinkan.');
}

if (!verifyPatientCsrfToken()) {
    http_response_code(403);
    exit('Sesi formulir tidak valid.');
}

unset(
    $_SESSION['patient_id'],
    $_SESSION['patient_name'],
    $_SESSION['patient_email'],
    $_SESSION['patient_csrf_token']
);

session_regenerate_id(true);
header('Location: login.php');
exit;
