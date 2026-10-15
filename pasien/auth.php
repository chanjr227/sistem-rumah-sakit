<?php

declare(strict_types=1);

function startPatientSession(): void
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

function patientCsrfToken(): string
{
    startPatientSession();
    if (!isset($_SESSION['patient_csrf_token'])) {
        $_SESSION['patient_csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['patient_csrf_token'];
}

function verifyPatientCsrfToken(): bool
{
    $submittedToken = $_POST['csrf_token'] ?? '';
    return is_string($submittedToken)
        && isset($_SESSION['patient_csrf_token'])
        && hash_equals($_SESSION['patient_csrf_token'], $submittedToken);
}

function requirePatientLogin(): void
{
    startPatientSession();
    if (!isset($_SESSION['patient_id'])) {
        header('Location: login.php');
        exit;
    }
}

function escapePatientHtml(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
