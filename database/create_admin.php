<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config/database.php';

fwrite(STDOUT, 'Username admin: ');
$username = trim((string) fgets(STDIN));
if ($username === '' || strlen($username) > 100) {
    fwrite(STDERR, "Username wajib diisi dan maksimal 100 byte.\n");
    exit(1);
}

fwrite(STDOUT, 'Password admin: ');
$password = rtrim((string) fgets(STDIN), "\r\n");
if (strlen($password) < 12) {
    fwrite(STDERR, "Password harus minimal 12 karakter.\n");
    exit(1);
}

try {
    $connection = getDatabaseConnection();
    $statement = $connection->prepare(
        'INSERT INTO admins (username, password_hash) VALUES (:username, :password_hash)'
    );
    $statement->execute([
        'username' => $username,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
    ]);
} catch (PDOException $exception) {
    if ($exception->getCode() === '23000') {
        fwrite(STDERR, "Username admin tersebut sudah digunakan.\n");
        exit(1);
    }

    error_log('Admin provisioning database error: ' . $exception->getMessage());
    fwrite(STDERR, "Akun admin tidak dapat dibuat. Periksa konfigurasi database.\n");
    exit(1);
}

fwrite(STDOUT, "Akun admin berhasil dibuat.\n");
